<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus;
use App\Enums\DecisionStatus;
use App\Enums\RecommendationStatus;
use App\Enums\ReportStatus;
use App\Models\BreakGlassRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OversightProjection
{
    public const QUEUES = [
        'waiting_admin',
        'waiting_satgas',
        'waiting_leader',
        'emergency_access',
        'critical_security',
    ];

    public const URGENCIES = ['normal', 'attention', 'overdue'];

    public function __construct(
        private readonly BusinessDayClock $clock,
        private readonly AuditLogVisibilityScope $auditVisibility,
    ) {
    }

    /**
     * @return array{queues: array<string, int>, urgencies: array<string, int>, total: int, generated_at: string}
     */
    public function summary(User $user, CarbonImmutable $cutoff, ?string $urgency = null): array
    {
        $queues = array_fill_keys(self::QUEUES, 0);
        $urgencies = array_fill_keys(self::URGENCIES, 0);
        $total = 0;

        foreach ($this->rows($user, $cutoff) as $row) {
            $item = $this->present($row, $cutoff);

            if ($urgency && $item['urgency'] !== $urgency) {
                continue;
            }

            $queues[$item['queue']]++;
            $urgencies[$item['urgency']]++;
            $total++;
        }

        return [
            'queues' => $queues,
            'urgencies' => $urgencies,
            'total' => $total,
            'generated_at' => $cutoff->toJSON(),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}, cutoff: string}
     */
    public function paginate(
        User $user,
        CarbonImmutable $cutoff,
        ?string $queue,
        ?string $urgency,
        int $page,
        int $perPage,
    ): array {
        $offset = ($page - 1) * $perPage;
        $items = [];
        $total = 0;

        foreach ($this->rows($user, $cutoff, $queue) as $row) {
            $item = $this->present($row, $cutoff);

            if ($urgency && $item['urgency'] !== $urgency) {
                continue;
            }

            if ($total >= $offset && count($items) < $perPage) {
                $items[] = $item;
            }

            $total++;
        }

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'cutoff' => $cutoff->toJSON(),
        ];
    }

    /**
     * @return \Generator<int, object>
     */
    private function rows(User $user, CarbonImmutable $cutoff, ?string $queue = null): \Generator
    {
        $query = $this->query($user, $cutoff);

        if ($queue) {
            $query->where('queue_code', $queue);
        }

        foreach ($query
            ->orderByDesc('started_at')
            ->orderBy('queue_code')
            ->orderBy('reference')
            ->cursor() as $row) {
            yield $row;
        }
    }

    private function query(User $user, CarbonImmutable $cutoff): Builder
    {
        $union = $this->reportVerificationQuery()
            ->unionAll($this->caseAssignmentQuery())
            ->unionAll($this->satgasCaseQuery())
            ->unionAll($this->recommendationReviewQuery())
            ->unionAll($this->decisionHandoffQuery())
            ->unionAll($this->emergencyAccessQuery())
            ->unionAll($this->criticalSecurityQuery($user));

        return DB::query()
            ->fromSub($union, 'oversight_projection')
            ->where('started_at', '<=', $cutoff->format('Y-m-d H:i:s'));
    }

    private function reportVerificationQuery(): Builder
    {
        return DB::table('reports')
            ->selectRaw('? as queue_code, ? as work_type, registration_number as reference, status, submitted_at as started_at', [
                'waiting_admin',
                'report_verification',
            ])
            ->whereNull('deleted_at')
            ->whereIn('status', [ReportStatus::Submitted->value, ReportStatus::UnderReview->value]);
    }

    private function caseAssignmentQuery(): Builder
    {
        return DB::table('cases')
            ->join('case_statuses', 'case_statuses.code', '=', 'cases.status_code')
            ->selectRaw('? as queue_code, ? as work_type, cases.case_number as reference, case_statuses.name as status, cases.forwarded_at as started_at', [
                'waiting_admin',
                'case_assignment',
            ])
            ->whereNull('cases.deleted_at')
            ->where('case_statuses.name', CaseStatus::Forwarded->value)
            ->whereNotExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('case_assignments')
                ->whereColumn('case_assignments.case_id', 'cases.id')
                ->where('case_assignments.is_active', true));
    }

    private function satgasCaseQuery(): Builder
    {
        return DB::table('cases')
            ->join('case_statuses', 'case_statuses.code', '=', 'cases.status_code')
            ->leftJoin('recommendations', 'recommendations.case_id', '=', 'cases.id')
            ->leftJoin('recommendation_statuses', 'recommendation_statuses.code', '=', 'recommendations.status_code')
            ->selectRaw('? as queue_code, ? as work_type, cases.case_number as reference, case_statuses.name as status, COALESCE(cases.assessment_at, cases.investigation_started_at, cases.recommendation_at, cases.forwarded_at) as started_at', [
                'waiting_satgas',
                'satgas_case',
            ])
            ->whereNull('cases.deleted_at')
            ->whereExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('case_assignments')
                ->whereColumn('case_assignments.case_id', 'cases.id')
                ->where('case_assignments.is_active', true))
            ->where(function (Builder $query): void {
                $query->whereIn('case_statuses.name', [
                    CaseStatus::Forwarded->value,
                    CaseStatus::Assessment->value,
                    CaseStatus::Investigation->value,
                    CaseStatus::Mediation->value,
                ])->orWhere(function (Builder $query): void {
                    $query->where('case_statuses.name', CaseStatus::Recommendation->value)
                        ->where(function (Builder $query): void {
                            $query->whereNull('recommendations.id')
                                ->orWhereIn('recommendation_statuses.name', [
                                    RecommendationStatus::Drafting->value,
                                    RecommendationStatus::InternalReview->value,
                                    RecommendationStatus::Revised->value,
                                ]);
                        });
                });
            });
    }

    private function recommendationReviewQuery(): Builder
    {
        return DB::table('recommendations')
            ->join('recommendation_statuses', 'recommendation_statuses.code', '=', 'recommendations.status_code')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->selectRaw('? as queue_code, ? as work_type, cases.case_number as reference, recommendation_statuses.name as status, COALESCE(recommendations.submitted_at, recommendations.updated_at, recommendations.created_at) as started_at', [
                'waiting_leader',
                'recommendation_review',
            ])
            ->whereNull('cases.deleted_at')
            ->where('recommendation_statuses.name', RecommendationStatus::SubmittedToLeader->value);
    }

    private function decisionHandoffQuery(): Builder
    {
        return DB::table('recommendations')
            ->join('recommendation_statuses', 'recommendation_statuses.code', '=', 'recommendations.status_code')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->leftJoin('decisions', 'decisions.recommendation_id', '=', 'recommendations.id')
            ->leftJoin('decision_statuses', 'decision_statuses.code', '=', 'decisions.status_code')
            ->selectRaw('? as queue_code, ? as work_type, cases.case_number as reference, COALESCE(decision_statuses.name, ?) as status, COALESCE(recommendations.approved_at, recommendations.updated_at, recommendations.created_at) as started_at', [
                'waiting_admin',
                'decision_handoff',
                DecisionStatus::Draft->value,
            ])
            ->whereNull('cases.deleted_at')
            ->whereIn('recommendation_statuses.name', [
                RecommendationStatus::Accepted->value,
                RecommendationStatus::PartiallyAccepted->value,
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('decisions.id')
                    ->orWhere('decision_statuses.name', '!=', DecisionStatus::Finalized->value);
            });
    }

    private function emergencyAccessQuery(): Builder
    {
        return DB::table('break_glass_requests')
            ->join('reports', 'reports.id', '=', 'break_glass_requests.report_id')
            ->selectRaw('? as queue_code, ? as work_type, reports.registration_number as reference, break_glass_requests.status, break_glass_requests.requested_at as started_at', [
                'emergency_access',
                'emergency_access',
            ])
            ->where('break_glass_requests.status', BreakGlassRequest::STATUS_PENDING);
    }

    private function criticalSecurityQuery(User $user): Builder
    {
        return $this->auditVisibility->query($user)->toBase()
            ->selectRaw('? as queue_code, ? as work_type, CAST(public_id AS TEXT) as reference, result as status, created_at as started_at', [
                'critical_security',
                'critical_security',
            ])
            ->where('category', AuditCategory::Security->value)
            ->where('severity', AuditSeverity::Critical->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row, CarbonImmutable $cutoff): array
    {
        $workType = (string) $row->work_type;
        $thresholdDays = max(1, (int) config("audit.oversight.threshold_business_days.{$workType}", 5));
        $thresholdSeconds = $thresholdDays * 86400;
        $elapsedSeconds = $this->clock->elapsedSeconds((string) $row->started_at, $cutoff);
        $progress = $elapsedSeconds / $thresholdSeconds * 100;
        $urgency = $progress >= 100 ? 'overdue' : ($progress >= 75 ? 'attention' : 'normal');

        return [
            'queue' => (string) $row->queue_code,
            'work_type' => $workType,
            'reference' => (string) $row->reference,
            'status' => (string) $row->status,
            'started_at' => CarbonImmutable::parse($row->started_at)->toJSON(),
            'due_at' => $this->clock->dueAt((string) $row->started_at, $thresholdSeconds)->toJSON(),
            'elapsed_business_seconds' => $elapsedSeconds,
            'elapsed_business_days' => $elapsedSeconds / 86400,
            'threshold_business_days' => $thresholdDays,
            'progress_percent' => $progress,
            'urgency' => $urgency,
        ];
    }
}
