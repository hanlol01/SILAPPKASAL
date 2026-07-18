<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CaseService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Records the risk and priority assessment for a case that is currently
     * in the assessment status. Master data validity is enforced by the form
     * request; this method enforces the case-status invariant transactionally.
     *
     * @param array<string, mixed> $data
     */
    public function recordAssessment(CaseRecord $case, User $actor, array $data): CaseRecord
    {
        return DB::transaction(function () use ($case, $actor, $data): CaseRecord {
            $case = CaseRecord::query()->with('status')->whereKey($case->id)->lockForUpdate()->firstOrFail();

            if ($case->status?->name === CaseStatusEnum::Closed->value) {
                throw $this->unprocessable('Closed cases cannot receive an assessment');
            }

            if ($case->status?->name !== CaseStatusEnum::Assessment->value) {
                throw $this->unprocessable('Case must be in assessment status to record an assessment');
            }

            $beforeRiskLevelCode = $case->risk_level_code;
            $beforePriorityCode = $case->priority_code;

            $case->forceFill([
                'risk_level_code' => $data['risk_level_code'],
                'priority_code' => $data['priority_level_code'],
            ])->save();

            $this->auditLogService->record(
                action: \App\Enums\AuditAction::CaseAssessmentRecorded,
                category: \App\Enums\AuditCategory::Case,
                severity: \App\Enums\AuditSeverity::Info,
                actor: $actor,
                subject: $case,
                metadata: [
                    'case_id' => $case->id,
                    'case_number' => $case->case_number,
                    'status_code' => $case->status_code,
                    'risk_level_code' => $case->risk_level_code,
                    'priority_code' => $case->priority_code,
                ],
                beforeChanges: [
                    'risk_level_code' => $beforeRiskLevelCode,
                    'priority_code' => $beforePriorityCode,
                ],
                afterChanges: [
                    'risk_level_code' => $case->risk_level_code,
                    'priority_code' => $case->priority_code,
                ],
            );

            $this->notificationService->caseAssessmentRecorded($case);

            return $case->load(['status', 'riskLevel', 'priorityLevel', 'activeAssignments.satgas']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function forwardReport(Report $report, User $actor, array $data): CaseRecord
    {
        $this->authorizeForward($report, $actor);
        $satgasIds = $this->validatedSatgasIds($data['satgas_ids'], (int) $data['lead_satgas_id']);

        return DB::transaction(function () use ($report, $actor, $satgasIds, $data): CaseRecord {
            $report = Report::query()
                ->whereKey($report->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureForwardable($report);
            $status = $this->statusByName(CaseStatusEnum::Forwarded);
            $forwardedAt = now();

            $case = CaseRecord::query()->create([
                'report_id' => $report->id,
                'registration_number' => $report->registration_number,
                'case_number' => $this->generateCaseNumber($forwardedAt),
                'status_code' => $status->code,
                'risk_level_code' => null,
                'priority_code' => $report->priority,
                'current_stage' => $status->workflow_stage ?? 2,
                'forwarded_at' => $forwardedAt,
            ]);

            $this->createAssignments($case, $actor, $satgasIds, (int) $data['lead_satgas_id']);

            $report->forceFill([
                'status' => ReportStatus::Forwarded->value,
                'forwarded_at' => $forwardedAt,
            ])->save();

            $this->notificationService->caseAssigned($case, $satgasIds);

            return $this->loadForUser($case->load('report'), $actor);
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, CaseRecord>
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = CaseRecord::query()
            ->with(['status', 'riskLevel', 'priorityLevel', 'activeAssignments.satgas'])
            ->latest('forwarded_at');

        if ($this->canReadMetadata($user)) {
            // Metadata readers see all non-deleted cases.
        } elseif ($this->canReadAssigned($user)) {
            $query
                ->with('reportSensitive')
                ->whereHas('activeAssignments', fn (Builder $query): Builder => $query->where('satgas_id', $user->id));
        } else {
            throw $this->forbidden();
        }

        if (! empty($filters['status'])) {
            $status = $this->resolveStatus((string) $filters['status']);
            $query->where('status_code', $status->code);
        }

        if (! empty($filters['quick_filter'])) {
            match ($filters['quick_filter']) {
                'active' => $query->whereNull('cases.closed_at'),
                'pending_decision' => $query->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('recommendations')
                        ->join('decisions', 'decisions.recommendation_id', '=', 'recommendations.id')
                        ->whereColumn('recommendations.case_id', 'cases.id')
                        ->whereNull('decisions.finalized_at');
                }),
                'with_evidence' => $query->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('investigations')
                        ->join('evidences', 'evidences.investigation_id', '=', 'investigations.id')
                        ->whereColumn('investigations.case_id', 'cases.id')
                        ->whereNull('evidences.deleted_at');
                }),
            };
        }

        if (! empty($filters['risk_level'])) {
            $query->where('risk_level_code', $filters['risk_level']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority_code', $filters['priority']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function loadForUser(CaseRecord $case, User $user): CaseRecord
    {
        $relations = ['status', 'riskLevel', 'priorityLevel', 'activeAssignments.satgas'];

        if ($this->canReadAssigned($user) && $this->isAssignedTo($case, $user)) {
            $relations[] = 'reportSensitive';
        } else {
            $relations[] = 'report';
        }

        return $case->load($relations);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignSatgas(CaseRecord $case, User $actor, array $data): CaseRecord
    {
        $satgasIds = $this->validatedSatgasIds($data['satgas_ids'], (int) $data['lead_satgas_id']);

        return DB::transaction(function () use ($case, $actor, $satgasIds, $data): CaseRecord {
            $case = CaseRecord::query()->with('status')->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $previousActiveSatgasIds = $case->activeAssignments()
                ->pluck('satgas_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($case->status?->name === CaseStatusEnum::Closed->value) {
                throw $this->unprocessable('Closed cases cannot be reassigned');
            }

            $case->activeAssignments()
                ->whereNotIn('satgas_id', $satgasIds)
                ->update([
                    'is_active' => false,
                    'is_lead' => false,
                    'unassigned_at' => now(),
                ]);

            foreach ($satgasIds as $satgasId) {
                $assignment = $case->activeAssignments()
                    ->where('satgas_id', $satgasId)
                    ->first();

                if (! $assignment) {
                    $assignment = $case->assignments()->make([
                        'satgas_id' => $satgasId,
                        'assigned_at' => now(),
                    ]);
                }

                $assignment->fill([
                    'assigned_by' => $actor->id,
                    'is_lead' => $satgasId === (int) $data['lead_satgas_id'],
                    'is_active' => true,
                    'unassigned_at' => null,
                ])->save();
            }

            $case->activeAssignments()
                ->where('satgas_id', '!=', (int) $data['lead_satgas_id'])
                ->update(['is_lead' => false]);

            $newlyAssignedSatgasIds = array_values(array_diff($satgasIds, $previousActiveSatgasIds));

            if ($newlyAssignedSatgasIds !== []) {
                $this->notificationService->caseAssigned($case, $newlyAssignedSatgasIds);
            }

            return $this->loadForUser($case, $actor);
        });
    }

    public function updateStatus(CaseRecord $case, User $actor, string $requestedStatus): CaseRecord
    {
        return DB::transaction(function () use ($case, $requestedStatus): CaseRecord {
            $case = CaseRecord::query()->with('status')->whereKey($case->id)->lockForUpdate()->firstOrFail();

            if ($case->status?->name === CaseStatusEnum::Closed->value) {
                throw $this->unprocessable('Closed cases cannot transition to another status');
            }

            $nextStatus = $this->resolveStatus($requestedStatus);
            $this->ensureNotLifecycleControlledTransition($case->status?->name, $nextStatus->name);
            $allowedTransitions = $case->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid case status transition');
            }

            $case->forceFill([
                'status_code' => $nextStatus->code,
                'current_stage' => $nextStatus->workflow_stage,
                ...$this->timestampForStatus($nextStatus->name),
            ])->save();

            $this->notificationService->caseStatusChanged($case);

            return $case->load(['status', 'riskLevel', 'priorityLevel', 'activeAssignments.satgas']);
        });
    }

    /**
     * @param list<int|string> $satgasIds
     * @return list<int>
     */
    private function validatedSatgasIds(array $satgasIds, int $leadSatgasId): array
    {
        $ids = array_values(array_unique(array_map('intval', $satgasIds)));

        if ($ids === [] || ! in_array($leadSatgasId, $ids, true)) {
            throw $this->unprocessable('Lead Satgas must be included in satgas_ids');
        }

        $validCount = User::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'satgas_ppks'))
            ->count();

        if ($validCount !== count($ids)) {
            throw $this->unprocessable('All assignees must be active Satgas PPKS users');
        }

        return $ids;
    }

    /**
     * @param list<int> $satgasIds
     */
    private function createAssignments(CaseRecord $case, User $actor, array $satgasIds, int $leadSatgasId): void
    {
        foreach ($satgasIds as $satgasId) {
            $case->assignments()->create([
                'satgas_id' => $satgasId,
                'assigned_by' => $actor->id,
                'is_lead' => $satgasId === $leadSatgasId,
                'is_active' => true,
                'assigned_at' => now(),
            ]);
        }
    }

    private function authorizeForward(Report $report, User $actor): void
    {
        if (! $actor->hasPermission('reports.forward') || ! ($actor->hasRole('admin') || $actor->hasRole('super_admin'))) {
            throw $this->forbidden();
        }

        $this->ensureForwardable($report);
    }

    private function ensureForwardable(Report $report): void
    {
        if ($report->trashed()) {
            throw $this->unprocessable('Soft-deleted reports cannot be forwarded');
        }

        if ($report->case()->exists()) {
            throw $this->unprocessable('Report has already been forwarded to a case');
        }

        if (! in_array($report->status, [ReportStatus::Submitted->value, ReportStatus::UnderReview->value], true)) {
            throw $this->unprocessable('Report status is not eligible for forwarding');
        }
    }

    private function generateCaseNumber(\DateTimeInterface $forwardedAt): string
    {
        $attempts = 0;

        beginning:
        $attempts++;
        $date = $forwardedAt->format('Ymd');
        $prefix = "CASE-{$date}-";
        $nextNumber = CaseRecord::query()
            ->where('case_number', 'like', "{$prefix}%")
            ->count() + 1;
        $caseNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        if (CaseRecord::query()->where('case_number', $caseNumber)->exists() && $attempts < 5) {
            goto beginning;
        }

        return $caseNumber;
    }

    private function statusByName(CaseStatusEnum $status): CaseStatus
    {
        return CaseStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveStatus(string $status): CaseStatus
    {
        $normalized = mb_strtolower(trim($status));

        return CaseStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first() ?? throw $this->unprocessable('Unknown case status');
    }

    private function ensureNotLifecycleControlledTransition(?string $fromStatus, string $toStatus): void
    {
        $controlledTransitions = [
            CaseStatusEnum::Recommendation->value.'>'.CaseStatusEnum::Decision->value,
            CaseStatusEnum::Decision->value.'>'.CaseStatusEnum::Decided->value,
        ];

        if (in_array($fromStatus.'>'.$toStatus, $controlledTransitions, true)) {
            throw $this->unprocessable('This case transition is controlled by its workflow action');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function timestampForStatus(string $status): array
    {
        return match ($status) {
            CaseStatusEnum::Assessment->value => ['assessment_at' => now()],
            CaseStatusEnum::Investigation->value => ['investigation_started_at' => now()],
            CaseStatusEnum::Recommendation->value => ['recommendation_at' => now()],
            CaseStatusEnum::Decision->value, CaseStatusEnum::Decided->value => ['decision_at' => now()],
            CaseStatusEnum::Closed->value => ['closed_at' => now()],
            CaseStatusEnum::Escalated->value => ['escalated_at' => now()],
            default => [],
        };
    }

    private function canReadMetadata(User $user): bool
    {
        return ($user->hasPermission('cases.read.metadata') && ($user->hasRole('admin') || $user->hasRole('super_admin')))
            || ($user->hasPermission('cases.read.all') && $user->hasRole('super_admin'));
    }

    private function canReadAssigned(User $user): bool
    {
        return $user->hasPermission('cases.read.assigned') && $user->hasRole('satgas_ppks');
    }

    private function isAssignedTo(CaseRecord $case, User $user): bool
    {
        return CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action',
            'errors' => null,
        ], 403));
    }

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }
}
