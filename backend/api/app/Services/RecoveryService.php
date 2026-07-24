<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Models\CaseAssignment;
use App\Models\Decision;
use App\Models\Recovery;
use App\Models\RecoveryMonitoring;
use App\Models\RecoveryStatus;
use App\Models\RecoveryType;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CaseCampusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class RecoveryService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
        private readonly CaseMutationGuard $caseMutationGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForDecision(Decision $decision, User $actor, array $data): Recovery
    {
        $decision->loadMissing('recommendation.case.status');
        $this->authorizeRecoveryManager($actor, $decision);
        $this->caseMutationGuard->assertMutable($decision->recommendation->case);
        $this->ensureDecisionCanReceiveRecovery($decision);
        $caseId = $decision->recommendation->case_id;

        return DB::transaction(function () use ($decision, $actor, $data, $caseId): Recovery {
            $case = $this->caseMutationGuard
                ->lockAndAssertMutable($caseId)
                ->load(['finalSummary', 'report.reporter:id,university_id']);
            $decision = Decision::query()
                ->with(['status', 'recommendation.case.status', 'recommendation.case.report.reporter:id,university_id'])
                ->whereKey($decision->id)
                ->lockForUpdate()
                ->firstOrFail();
            $decision->recommendation?->setRelation('case', $case);

            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeRecoveryManager($actor, $decision);
            $this->ensureDecisionCanReceiveRecovery($decision);
            $recoveryType = $this->activeRecoveryType($data['recovery_type_code']);
            $status = $this->statusByName(RecoveryStatusEnum::Planned);

            $recovery = Recovery::query()->create([
                'decision_id' => $decision->id,
                'recovery_type_code' => $recoveryType->code,
                'status_code' => $status->code,
                'created_by' => $actor->id,
                'recovery_plan' => $data['recovery_plan'],
                'support_needs' => $data['support_needs'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recordStatusHistory($recovery, null, $status->code, $actor);
            $recovery = $recovery->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecoveryCreated,
                $actor,
                $recovery,
                [
                    'recovery_id' => $recovery->id,
                    'decision_id' => $recovery->decision_id,
                    'case_id' => $decision->recommendation?->case_id,
                    'status_code' => $recovery->status_code,
                    'recovery_type_code' => $recovery->recovery_type_code,
                ],
                afterChanges: [
                    'status_code' => $recovery->status_code,
                    'recovery_type_code' => $recovery->recovery_type_code,
                    'recovery_plan' => $recovery->recovery_plan,
                    'support_needs' => $recovery->support_needs,
                    'notes' => $recovery->notes,
                ],
            );

            $this->notificationService->recoveryCreated($recovery);

            return $recovery;
        });
    }

    /**
     * @return Collection<int, Recovery>
     */
    public function listForDecision(Decision $decision, User $user): Collection
    {
        $decision->loadMissing('recommendation.case.status');

        if (! $this->canReadRecovery($user, $decision)) {
            throw $this->forbidden();
        }

        return Recovery::query()
            ->where('decision_id', $decision->id)
            ->with($this->detailRelations())
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    public function loadForUser(Recovery $recovery, User $user): Recovery
    {
        $recovery->loadMissing('decision.recommendation.case.status');

        if (! $this->canReadRecovery($user, $recovery->decision)) {
            throw $this->forbidden();
        }

        return $recovery->load($this->detailRelations());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Recovery $recovery, User $actor, array $data): Recovery
    {
        $recovery->loadMissing('decision.recommendation:id,case_id');
        $caseId = $recovery->decision->recommendation->case_id;

        return DB::transaction(function () use ($recovery, $actor, $data, $caseId): Recovery {
            $lockedCase = $this->caseMutationGuard->lockAndAssertMutable($caseId);
            $recovery = Recovery::query()->with(['status', 'decision.recommendation.case.report.reporter:id,university_id'])->whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            $recovery->decision->recommendation->setRelation('case', $lockedCase);
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeRecoveryManager($actor, $recovery);
            $this->ensureRecoveryOpen($recovery);

            if (isset($data['recovery_type_code'])) {
                $data['recovery_type_code'] = $this->activeRecoveryType($data['recovery_type_code'])->code;
            }

            $before = $recovery->only([
                'recovery_type_code',
                'recovery_plan',
                'support_needs',
                'notes',
            ]);

            $recovery->fill($data)->save();

            $after = $recovery->only([
                'recovery_type_code',
                'recovery_plan',
                'support_needs',
                'notes',
            ]);

            $recovery = $recovery->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecoveryUpdated,
                $actor,
                $recovery,
                [
                    'recovery_id' => $recovery->id,
                    'decision_id' => $recovery->decision_id,
                    'case_id' => $recovery->decision?->recommendation?->case_id,
                    'recovery_type_code' => $recovery->recovery_type_code,
                ],
                beforeChanges: array_intersect_key($before, $data),
                afterChanges: array_intersect_key($after, $data),
            );

            return $recovery;
        });
    }

    /** @param array{status: string, discontinuation_reason?: string|null} $data */
    public function updateStatus(Recovery $recovery, User $actor, array $data): Recovery
    {
        $recovery->loadMissing('decision.recommendation:id,case_id');
        $caseId = $recovery->decision->recommendation->case_id;

        return DB::transaction(function () use ($recovery, $actor, $data, $caseId): Recovery {
            $lockedCase = $this->caseMutationGuard->lockAndAssertMutable($caseId);
            $recovery = Recovery::query()->with(['status', 'decision.recommendation.case.report.reporter:id,university_id'])->whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            $recovery->decision->recommendation->setRelation('case', $lockedCase);
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeRecoveryManager($actor, $recovery);
            $this->ensureRecoveryOpen($recovery);

            $nextStatus = $this->resolveStatus($data['status']);
            $allowedTransitions = $recovery->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid recovery status transition');
            }

            if ($nextStatus->name === RecoveryStatusEnum::Completed->value && ! $recovery->monitorings()->exists()) {
                throw $this->unprocessableCode(ApiErrorCode::RecoveryMonitoringRequired);
            }

            if (
                $nextStatus->name === RecoveryStatusEnum::Discontinued->value
                && blank($data['discontinuation_reason'] ?? null)
            ) {
                throw $this->unprocessableCode(ApiErrorCode::RecoveryDiscontinuationReasonRequired);
            }

            $fromStatusCode = $recovery->status_code;
            $fromStatusName = $recovery->status?->name;
            $timestamps = [
                'status_code' => $nextStatus->code,
            ];

            if ($nextStatus->name === RecoveryStatusEnum::Ongoing->value) {
                $timestamps['started_at'] = $recovery->started_at ?? now();
            }

            if ($nextStatus->name === RecoveryStatusEnum::Completed->value) {
                $timestamps['completed_at'] = now();
            }

            if ($nextStatus->name === RecoveryStatusEnum::Discontinued->value) {
                $timestamps['discontinued_at'] = now();
                $timestamps['discontinuation_reason'] = trim((string) $data['discontinuation_reason']);
            }

            $recovery->forceFill($timestamps)->save();
            $this->recordStatusHistory($recovery, $fromStatusCode, $nextStatus->code, $actor);
            $recovery = $recovery->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecoveryStatusChanged,
                $actor,
                $recovery,
                [
                    'recovery_id' => $recovery->id,
                    'decision_id' => $recovery->decision_id,
                    'case_id' => $recovery->decision?->recommendation?->case_id,
                    'from_status' => $fromStatusName,
                    'to_status' => $nextStatus->name,
                    'recovery_type_code' => $recovery->recovery_type_code,
                ],
                beforeChanges: ['status_code' => $fromStatusCode],
                afterChanges: ['status_code' => $recovery->status_code],
            );

            if ($nextStatus->name === RecoveryStatusEnum::Discontinued->value) {
                $this->recordAudit(
                    AuditAction::RecoveryDiscontinued,
                    $actor,
                    $recovery,
                    [
                        'case_number' => $recovery->decision?->recommendation?->case?->case_number,
                        'recovery_type_code' => $recovery->recovery_type_code,
                        'status_code' => $recovery->status_code,
                        'recovery_terminal_type' => RecoveryStatusEnum::Discontinued->value,
                        'result' => 'succeeded',
                    ],
                    beforeChanges: ['status_code' => $fromStatusCode],
                    afterChanges: ['status_code' => $recovery->status_code],
                );
            }

            $this->notificationService->recoveryStatusChanged($recovery);

            return $recovery;
        });
    }

    /**
     * @return array{current_status: array{code: string|null, name: string|null, description: string|null}, valid_transitions: list<array{code: string, name: string, description: string|null, soft_warning: string|null, allowed: bool, reason_code: string|null}>}
     */
    public function statusOptions(Recovery $recovery, User $user): array
    {
        $recovery->loadMissing([
            'status',
            'decision.recommendation.case.status',
            'decision.recommendation.case.report.reporter:id,university_id',
        ]);

        $statuses = [];

        if (
            $this->canManageRecovery($user, $recovery)
            && ! $recovery->decision->recommendation->case->isOperationallyTerminal()
        ) {
            $transitionNames = $recovery->status?->valid_transitions ?? [];

            $statuses = RecoveryStatus::query()
                ->where('is_active', true)
                ->whereIn('name', $transitionNames)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (RecoveryStatus $status): array => [
                    'code' => $status->code,
                    'name' => $status->name,
                    'description' => $status->description,
                    'soft_warning' => $this->monitoringDurationWarning($recovery, $status),
                    'allowed' => $status->name !== RecoveryStatusEnum::Completed->value || $recovery->monitorings()->exists(),
                    'reason_code' => $status->name === RecoveryStatusEnum::Completed->value && ! $recovery->monitorings()->exists()
                        ? ApiErrorCode::RecoveryMonitoringRequired
                        : null,
                ])
                ->values()
                ->all();
        }

        return [
            'current_status' => [
                'code' => $recovery->status?->code,
                'name' => $recovery->status?->name,
                'description' => $recovery->status?->description,
            ],
            'valid_transitions' => $statuses,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMonitoring(Recovery $recovery, User $actor, array $data): RecoveryMonitoring
    {
        $recovery->loadMissing('decision.recommendation:id,case_id');
        $caseId = $recovery->decision->recommendation->case_id;

        return DB::transaction(function () use ($recovery, $actor, $data, $caseId): RecoveryMonitoring {
            $lockedCase = $this->caseMutationGuard->lockAndAssertMutable($caseId);
            $recovery = Recovery::query()->with(['status', 'decision.recommendation.case'])->whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            $recovery->decision->recommendation->setRelation('case', $lockedCase);
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();

            if (! $this->isAssignedToDecisionCase($recovery->decision, $actor)) {
                throw $this->forbidden();
            }

            if ($recovery->status?->name !== RecoveryStatusEnum::Ongoing->value) {
                throw $this->unprocessable('Monitoring can only be created for ongoing recovery');
            }

            $monitoring = RecoveryMonitoring::query()->create([
                'recovery_id' => $recovery->id,
                'monitor_id' => $actor->id,
                'monitoring_date' => $data['monitoring_date'],
                'condition_summary' => $data['condition_summary'],
                'follow_up_plan' => $data['follow_up_plan'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->load(['monitor', 'recovery.decision.recommendation.case']);

            $this->auditLogService->record(
                action: AuditAction::RecoveryMonitoringCreated,
                category: AuditCategory::Recovery,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $monitoring,
                metadata: [
                    'recovery_monitoring_id' => $monitoring->id,
                    'recovery_id' => $monitoring->recovery_id,
                    'decision_id' => $monitoring->recovery?->decision_id,
                    'case_id' => $monitoring->recovery?->decision?->recommendation?->case_id,
                ],
                afterChanges: [
                    'monitoring_date' => $monitoring->monitoring_date?->toDateString(),
                    'condition_summary' => $monitoring->condition_summary,
                    'follow_up_plan' => $monitoring->follow_up_plan,
                    'notes' => $monitoring->notes,
                ],
            );

            $this->notificationService->recoveryMonitoringCreated($recovery, $actor);

            return $monitoring;
        });
    }

    /**
     * @return Collection<int, RecoveryMonitoring>
     */
    public function listMonitoring(Recovery $recovery, User $user): Collection
    {
        $recovery = $this->loadForUser($recovery, $user);

        return $recovery->monitorings()
            ->with('monitor')
            ->latest('monitoring_date')
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    private function ensureDecisionCanReceiveRecovery(Decision $decision): void
    {
        $decision->loadMissing(['status', 'recommendation.case.status', 'recommendation.case.finalSummary']);

        if ($decision->status?->name !== DecisionStatusEnum::Finalized->value) {
            throw $this->unprocessable('Recovery requires a finalized decision');
        }

        if ($decision->recommendation?->case?->trashed()) {
            throw $this->unprocessable('Recovery cannot be created for a deleted case');
        }

        if ($decision->recommendation?->case?->status?->name === CaseStatusEnum::Closed->value) {
            throw $this->unprocessable('Recovery cannot be created for a closed case');
        }

        if ($decision->recommendation?->case?->status?->name !== CaseStatusEnum::Recovery->value) {
            throw $this->unprocessable('Case must be in recovery status before creating Recovery');
        }

        if ($decision->recommendation?->case?->finalSummary?->isPublished()) {
            throw $this->unprocessableCode(ApiErrorCode::FinalSummaryImmutable);
        }
    }

    private function ensureRecoveryOpen(Recovery $recovery): void
    {
        if (in_array($recovery->status?->name, RecoveryStatusEnum::terminalValues(), true)) {
            throw $this->unprocessable('Terminal recoveries cannot be changed');
        }
    }

    private function activeRecoveryType(string $code): RecoveryType
    {
        return RecoveryType::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first() ?? throw $this->unprocessable('Unknown recovery type');
    }

    private function statusByName(RecoveryStatusEnum $status): RecoveryStatus
    {
        return RecoveryStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveStatus(string $status): RecoveryStatus
    {
        $normalized = mb_strtolower(trim($status));

        return RecoveryStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first() ?? throw $this->unprocessable('Unknown recovery status');
    }

    private function recordStatusHistory(Recovery $recovery, ?string $fromStatusCode, string $toStatusCode, User $actor): void
    {
        $recovery->statusHistories()->create([
            'from_status_code' => $fromStatusCode,
            'to_status_code' => $toStatusCode,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $beforeChanges
     * @param  array<string, mixed>  $afterChanges
     */
    private function recordAudit(
        AuditAction $action,
        User $actor,
        Recovery $recovery,
        array $metadata = [],
        array $beforeChanges = [],
        array $afterChanges = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Recovery,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $recovery,
            metadata: $metadata,
            beforeChanges: $beforeChanges,
            afterChanges: $afterChanges,
        );
    }

    private function monitoringDurationWarning(Recovery $recovery, RecoveryStatus $status): ?string
    {
        if ($status->name !== RecoveryStatusEnum::Completed->value || ! $recovery->started_at) {
            return null;
        }

        if ($recovery->started_at->copy()->addMonthsNoOverflow(3)->isPast()) {
            return null;
        }

        return 'SOP recommends 3-6 months of monitoring before completing recovery. This is advisory and does not block completion.';
    }

    private function authorizeRecoveryManager(User $actor, Decision|Recovery $subject): void
    {
        if (! $this->canManageRecovery($actor, $subject)) {
            throw $this->forbidden();
        }
    }

    private function canManageRecovery(User $user, Decision|Recovery $subject): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.monitor')
            && $user->hasRole('admin')
            && $this->campusScope->sameCampus($user, $subject);
    }

    private function canReadRecovery(User $user, Decision $decision): bool
    {
        return $this->canManageRecovery($user, $decision)
            || ($user->is_active && $user->hasRole('super_admin') && $user->hasPermission('cases.read.all'))
            || $this->isAssignedToDecisionCase($decision, $user);
    }

    public function canReadSensitive(Recovery $recovery, User $user): bool
    {
        $recovery->loadMissing('decision.recommendation.case.report.reporter:id,university_id');

        return $this->canManageRecovery($user, $recovery)
            || $this->campusScope->canSensitiveOversight($user)
            || $this->isAssignedToDecisionCase($recovery->decision, $user);
    }

    private function isAssignedToDecisionCase(Decision $decision, User $user): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.monitor')
            && $user->hasRole('satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $decision->recommendation?->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'decision.recommendation.case.status',
            'recoveryType',
            'status',
            'creator',
            'statusHistories.fromStatus',
            'statusHistories.toStatus',
            'statusHistories.changedBy',
            'monitorings.monitor',
        ];
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

    private function unprocessableCode(string $errorCode): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __("api.errors.{$errorCode}"),
            'error_code' => $errorCode,
            'errors' => null,
        ], 422));
    }
}
