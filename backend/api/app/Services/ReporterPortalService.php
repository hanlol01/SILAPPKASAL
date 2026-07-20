<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus;
use App\Enums\InvestigationStatus;
use App\Enums\RecommendationStatus;
use App\Enums\RecoveryStatus;
use App\Enums\ReporterSafeStatus;
use App\Models\Decision;
use App\Models\Investigation;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Recovery;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReporterPortalService
{
    /**
     * @return array{total_reports: int, active_reports: int, completed_reports: int, unread_notifications: int}
     */
    public function summary(User $user): array
    {
        $base = $this->ownedReportsQuery($user);
        $completed = (clone $base)
            ->whereHas('case.status', fn ($query) => $query->where('name', CaseStatusEnum::Closed->value))
            ->count();
        $total = (clone $base)->count();

        return [
            'total_reports' => $total,
            'active_reports' => $total - $completed,
            'completed_reports' => $completed,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Report>
     */
    public function reports(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->ownedReportsQuery($user)
            ->with(['category', 'case.status'])
            ->latest('submitted_at')
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Report $report): Report => $this->withPortalStatus($report));
    }

    public function findReport(User $user, string $registrationNumber): ?Report
    {
        $report = $this->ownedReportsQuery($user)
            ->with([
                'category',
                'locationType',
                'campusStatus',
                'relation',
                'reporter.faculty',
                'reporter.studyProgram',
                'case.status',
            ])
            ->where('registration_number', $registrationNumber)
            ->first();

        return $report ? $this->withPortalStatus($report) : null;
    }

    /**
     * Reporter-safe handling progress for an owned report.
     *
     * @return array<string, mixed>|null
     */
    public function handlingProgress(User $user, string $registrationNumber): ?array
    {
        $report = $this->ownedReportsQuery($user)
            ->withCount('evidenceSubmissions')
            ->with([
                'case.status',
                'case.investigation' => fn ($query) => $query->withCount(['activities', 'evidences']),
                'case.investigation.status',
                'case.recommendation.status',
                'case.recommendation.decision.status',
                'case.recommendation.decision.recoveries' => fn ($query) => $query
                    ->withCount('monitorings')
                    ->withMax('monitorings', 'monitoring_date'),
                'case.recommendation.decision.recoveries.status',
            ])
            ->where('registration_number', $registrationNumber)
            ->first();

        if (! $report) {
            return null;
        }

        $case = $report->case;
        $investigation = $case?->investigation;
        $recommendation = $case?->recommendation;
        $decision = $recommendation?->decision;
        $recoveries = $decision?->recoveries ?? new Collection();
        $caseCompleted = $case?->status?->name === CaseStatusEnum::Closed->value;

        return [
            'registration_number' => $report->registration_number,
            'case' => [
                'available' => $case !== null,
                'state' => $case === null ? 'not_started' : ($caseCompleted ? 'completed' : 'ongoing'),
            ],
            'investigation' => $this->investigationProgress($case !== null, $investigation),
            'recommendation' => $this->recommendationProgress($case !== null, $recommendation),
            'decision' => $this->decisionProgress($case !== null, $decision),
            'recovery' => $this->recoveryProgress($case !== null, $recoveries),
            'monitoring' => [
                'count' => (int) $recoveries->sum('monitorings_count'),
                'latest_at' => $recoveries->max('monitorings_max_monitoring_date'),
            ],
            'evidence' => [
                'reporter_supporting_file_count' => (int) $report->evidence_submissions_count,
                'internal_evidence_count' => (int) ($investigation?->evidences_count ?? 0),
            ],
            'final_summary' => null,
        ];
    }

    /**
     * Reporter-safe progress timeline for an owned report.
     *
     * Returns only privacy-safe stage codes, safe timestamps, and the
     * completion state. Internal workflow status codes, staff identities,
     * assignments, and narrative content must never be added to this payload.
     *
     * @return array{registration_number: string, portal_status: string, is_completed: bool, events: list<array{stage: string, occurred_at: string|null}>}|null
     */
    public function reportTimeline(User $user, string $registrationNumber): ?array
    {
        $report = $this->ownedReportsQuery($user)
            ->with('case.status')
            ->where('registration_number', $registrationNumber)
            ->first();

        if (! $report) {
            return null;
        }

        $report = $this->withPortalStatus($report);
        $case = $report->case;
        $isCompleted = $case?->status?->name === CaseStatusEnum::Closed->value;

        $events = [];

        if ($report->submitted_at) {
            $events[] = ['stage' => 'laporan_dikirim', 'occurred_at' => $report->submitted_at];
        }

        if ($report->reviewed_at) {
            $events[] = ['stage' => 'laporan_ditinjau', 'occurred_at' => $report->reviewed_at];
        }

        if ($report->forwarded_at) {
            $events[] = ['stage' => 'proses_penanganan', 'occurred_at' => $report->forwarded_at];
        }

        if ($isCompleted && $case?->closed_at) {
            $events[] = ['stage' => 'selesai', 'occurred_at' => $case->closed_at];
        }

        $stageOrder = array_flip(['laporan_dikirim', 'laporan_ditinjau', 'proses_penanganan', 'selesai']);
        usort($events, static function (array $a, array $b) use ($stageOrder): int {
            $timeComparison = $a['occurred_at'] <=> $b['occurred_at'];

            return $timeComparison !== 0
                ? $timeComparison
                : ($stageOrder[$a['stage']] ?? PHP_INT_MAX) <=> ($stageOrder[$b['stage']] ?? PHP_INT_MAX);
        });

        return [
            'registration_number' => $report->registration_number,
            'portal_status' => (string) $report->portal_status,
            'is_completed' => $isCompleted,
            'events' => array_map(static fn (array $event): array => [
                'stage' => $event['stage'],
                'occurred_at' => $event['occurred_at']?->toJSON(),
            ], $events),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Report>
     */
    private function ownedReportsQuery(User $user)
    {
        return Report::query()
            ->where('reporter_id', $user->id)
            ->whereNotNull('reporter_id');
    }

    private function withPortalStatus(Report $report): Report
    {
        $report->setAttribute('portal_status', $this->portalStatus($report));

        return $report;
    }

    private function portalStatus(Report $report): string
    {
        return ReporterSafeStatus::forReport($report)->value;
    }

    /** @return array<string, mixed> */
    private function investigationProgress(bool $caseAvailable, ?Investigation $investigation): array
    {
        if (! $caseAvailable || $investigation === null) {
            return [
                'state' => $caseAvailable ? 'not_started' : 'unavailable',
                'started_at' => null,
                'completed_at' => null,
                'activity_count' => 0,
            ];
        }

        return [
            'state' => $investigation->status?->name === InvestigationStatus::Completed->value
                ? 'completed'
                : 'ongoing',
            'started_at' => $investigation->started_at?->toJSON(),
            'completed_at' => $investigation->completed_at?->toJSON(),
            'activity_count' => (int) ($investigation->activities_count ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function recommendationProgress(bool $caseAvailable, ?Recommendation $recommendation): array
    {
        if (! $caseAvailable || $recommendation === null) {
            return [
                'state' => $caseAvailable ? 'not_started' : 'unavailable',
                'submitted_at' => null,
                'reviewed_at' => null,
                'approved_at' => null,
            ];
        }

        $state = match ($recommendation->status?->name) {
            RecommendationStatus::SubmittedForReview->value,
            RecommendationStatus::SubmittedToLeader->value => 'waiting',
            RecommendationStatus::Accepted->value,
            RecommendationStatus::PartiallyAccepted->value,
            RecommendationStatus::Rejected->value => 'completed',
            default => 'ongoing',
        };

        return [
            'state' => $state,
            'submitted_at' => $recommendation->submitted_at?->toJSON(),
            'reviewed_at' => ($recommendation->approved_at ?? $recommendation->returned_at)?->toJSON(),
            'approved_at' => in_array($recommendation->status?->name, [
                RecommendationStatus::Accepted->value,
                RecommendationStatus::PartiallyAccepted->value,
            ], true) ? $recommendation->approved_at?->toJSON() : null,
        ];
    }

    /** @return array<string, mixed> */
    private function decisionProgress(bool $caseAvailable, ?Decision $decision): array
    {
        if (! $caseAvailable || $decision === null) {
            return [
                'state' => $caseAvailable ? 'not_started' : 'unavailable',
                'decision_date' => null,
                'finalized_at' => null,
            ];
        }

        return [
            'state' => $decision->status?->name === DecisionStatus::Finalized->value
                ? 'completed'
                : 'ongoing',
            'decision_date' => $decision->decision_date?->toDateString(),
            'finalized_at' => $decision->finalized_at?->toJSON(),
        ];
    }

    /**
     * @param Collection<int, Recovery> $recoveries
     * @return array<string, mixed>
     */
    private function recoveryProgress(bool $caseAvailable, Collection $recoveries): array
    {
        if (! $caseAvailable || $recoveries->isEmpty()) {
            return [
                'state' => $caseAvailable ? 'not_started' : 'unavailable',
                'started_at' => null,
                'completed_at' => null,
                'discontinued_at' => null,
            ];
        }

        $states = $recoveries->map(fn (Recovery $recovery) => $recovery->status?->name);
        $state = match (true) {
            $states->contains(RecoveryStatus::Ongoing->value) => 'ongoing',
            $states->contains(RecoveryStatus::Planned->value) => 'not_started',
            $states->contains(RecoveryStatus::Completed->value) => 'completed',
            default => 'discontinued',
        };

        return [
            'state' => $state,
            'started_at' => $recoveries->min('started_at')?->toJSON(),
            'completed_at' => $recoveries->max('completed_at')?->toJSON(),
            'discontinued_at' => $recoveries->max('discontinued_at')?->toJSON(),
        ];
    }
}
