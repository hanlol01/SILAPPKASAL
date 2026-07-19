<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Recommendation;
use App\Models\User;
use App\Support\CaseCampusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DecisionService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForRecommendation(Recommendation $recommendation, User $actor, array $data): Decision
    {
        $this->authorizeDecisionRecorder($actor, $recommendation);
        $this->ensureRecommendationCanReceiveDecision($recommendation);

        return DB::transaction(function () use ($recommendation, $actor, $data): Decision {
            $recommendation = Recommendation::query()
                ->with(['case.status', 'case.report.reporter:id,university_id', 'status', 'decision'])
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeDecisionRecorder($actor, $recommendation);
            $this->ensureRecommendationCanReceiveDecision($recommendation);
            $status = $this->statusByName(DecisionStatusEnum::Draft);

            $decision = Decision::query()->create([
                'recommendation_id' => $recommendation->id,
                'recorder_id' => $actor->id,
                'status_code' => $status->code,
                'outcome_code' => $data['outcome_code'],
                'decision_number' => $data['decision_number'] ?? null,
                'decision_date' => $data['decision_date'],
                'decision_summary' => $data['decision_summary'],
                'decision_content' => $data['decision_content'],
                'recorded_at' => now(),
            ]);

            $this->recordStatusHistory($decision, null, $status->code, $actor);
            $decision = $decision->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::DecisionCreated,
                $actor,
                $decision,
                [
                    'decision_id' => $decision->id,
                    'recommendation_id' => $decision->recommendation_id,
                    'case_id' => $recommendation->case_id,
                    'status_code' => $decision->status_code,
                    'outcome_code' => $decision->outcome_code,
                ],
                afterChanges: [
                    'status_code' => $decision->status_code,
                    'outcome_code' => $decision->outcome_code,
                    'decision_summary' => $decision->decision_summary,
                    'decision_content' => $decision->decision_content,
                ],
            );

            $this->notificationService->decisionCreated($decision);

            return $decision;
        });
    }

    /**
     * @return Collection<int, Decision>
     */
    public function listForRecommendation(Recommendation $recommendation, User $user): Collection
    {
        $recommendation->loadMissing('case');

        if (! $this->canReadDecision($user, $recommendation)) {
            throw $this->forbidden();
        }

        return Decision::query()
            ->where('recommendation_id', $recommendation->id)
            ->with($this->detailRelations())
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    public function loadForUser(Decision $decision, User $user): Decision
    {
        $decision->loadMissing('recommendation.case');

        if (! $this->canReadDecision($user, $decision->recommendation)) {
            throw $this->forbidden();
        }

        return $decision->load($this->detailRelations());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Decision $decision, User $actor, array $data): Decision
    {
        return DB::transaction(function () use ($decision, $actor, $data): Decision {
            $decision = Decision::query()->with(['status', 'recommendation.case.report.reporter:id,university_id'])->whereKey($decision->id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeDecisionRecorder($actor, $decision->recommendation);

            $this->ensureDecisionEditable($decision);

            $before = $decision->only([
                'outcome_code',
                'decision_number',
                'decision_date',
                'decision_summary',
                'decision_content',
            ]);

            $decision->fill($data)->save();

            $after = $decision->only([
                'outcome_code',
                'decision_number',
                'decision_date',
                'decision_summary',
                'decision_content',
            ]);

            $decision = $decision->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::DecisionUpdated,
                $actor,
                $decision,
                [
                    'decision_id' => $decision->id,
                    'recommendation_id' => $decision->recommendation_id,
                    'case_id' => $decision->recommendation?->case_id,
                ],
                beforeChanges: array_intersect_key($before, $data),
                afterChanges: array_intersect_key($after, $data),
            );

            return $decision;
        });
    }

    public function updateStatus(Decision $decision, User $actor, string $requestedStatus): Decision
    {
        return DB::transaction(function () use ($decision, $actor, $requestedStatus): Decision {
            $decision = Decision::query()->with(['status', 'recommendation.case.report.reporter:id,university_id'])->whereKey($decision->id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeDecisionRecorder($actor, $decision->recommendation);

            $this->ensureDecisionOpen($decision);

            $nextStatus = $this->resolveStatus($requestedStatus);
            $allowedTransitions = $decision->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid decision status transition');
            }

            $case = null;

            if ($nextStatus->name === DecisionStatusEnum::Finalized->value) {
                $recommendation = Recommendation::query()
                    ->whereKey($decision->recommendation_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $case = CaseRecord::query()
                    ->with('status')
                    ->whereKey($recommendation->case_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($case->status?->name !== CaseStatusEnum::Decision->value) {
                    throw $this->unprocessable('Case must be in decision status before finalization');
                }
            }

            $fromStatusCode = $decision->status_code;
            $fromStatusName = $decision->status?->name;
            $decision->forceFill([
                'status_code' => $nextStatus->code,
                'finalized_at' => $nextStatus->name === DecisionStatusEnum::Finalized->value ? now() : $decision->finalized_at,
            ])->save();

            $this->recordStatusHistory($decision, $fromStatusCode, $nextStatus->code, $actor);

            if ($case) {
                $this->advanceCaseToDecided($case, $actor);
            }

            $decision = $decision->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::DecisionStatusChanged,
                $actor,
                $decision,
                [
                    'decision_id' => $decision->id,
                    'recommendation_id' => $decision->recommendation_id,
                    'case_id' => $decision->recommendation?->case_id,
                    'from_status' => $fromStatusName,
                    'to_status' => $nextStatus->name,
                ],
                beforeChanges: ['status_code' => $fromStatusCode],
                afterChanges: ['status_code' => $decision->status_code],
            );

            if ($nextStatus->name === DecisionStatusEnum::Finalized->value) {
                $this->notificationService->decisionFinalized($decision);
            } else {
                $this->notificationService->decisionStatusChanged($decision);
            }

            return $decision;
        });
    }

    /**
     * @return array{current_status: array{code: string|null, name: string|null, description: string|null}, valid_transitions: list<array{code: string, name: string, description: string|null}>}
     */
    public function statusOptions(Decision $decision, User $user): array
    {
        $decision->loadMissing(['status', 'recommendation.case.report.reporter:id,university_id']);

        $statuses = [];

        if ($this->canManageDecision($user, $decision->recommendation)) {
            $transitionNames = $decision->status?->valid_transitions ?? [];

            $statuses = DecisionStatus::query()
                ->where('is_active', true)
                ->whereIn('name', $transitionNames)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (DecisionStatus $status): array => [
                    'code' => $status->code,
                    'name' => $status->name,
                    'description' => $status->description,
                ])
                ->values()
                ->all();
        }

        return [
            'current_status' => [
                'code' => $decision->status?->code,
                'name' => $decision->status?->name,
                'description' => $decision->status?->description,
            ],
            'valid_transitions' => $statuses,
        ];
    }

    private function ensureRecommendationCanReceiveDecision(Recommendation $recommendation): void
    {
        $recommendation->loadMissing(['case.status', 'status', 'decision']);

        if ($recommendation->status?->name !== RecommendationStatusEnum::Accepted->value) {
            throw $this->unprocessable('Decision requires an approved recommendation');
        }

        if ($recommendation->case?->status?->name !== CaseStatusEnum::Decision->value) {
            throw $this->unprocessable('Case must be in decision status');
        }

        if ($recommendation->decision()->exists()) {
            throw $this->unprocessable('Recommendation already has a decision');
        }
    }

    private function ensureDecisionEditable(Decision $decision): void
    {
        if ($decision->status?->name !== DecisionStatusEnum::Draft->value) {
            throw $this->unprocessable('Decision cannot be edited in its current status');
        }
    }

    private function ensureDecisionOpen(Decision $decision): void
    {
        if ($decision->status?->name === DecisionStatusEnum::Finalized->value) {
            throw $this->unprocessable('Finalized decisions cannot transition');
        }
    }

    private function authorizeDecisionRecorder(User $actor, Recommendation $recommendation): void
    {
        if (! $this->canManageDecision($actor, $recommendation)) {
            throw $this->forbidden();
        }
    }

    private function canManageDecision(User $user, Recommendation $recommendation): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.record_decision')
            && $user->hasRole('admin')
            && $this->campusScope->sameCampus($user, $recommendation);
    }

    private function canReadDecision(User $user, Recommendation $recommendation): bool
    {
        return $this->canManageDecision($user, $recommendation)
            || ($user->is_active && $user->hasPermission('cases.read.all') && $user->hasRole('super_admin'))
            || $this->isAssignedToRecommendationCase($recommendation, $user);
    }

    public function canReadSensitive(Decision $decision, User $user): bool
    {
        $decision->loadMissing('recommendation.case.report.reporter:id,university_id');

        return $this->canManageDecision($user, $decision->recommendation)
            || $this->campusScope->canSensitiveOversight($user)
            || $this->isAssignedToRecommendationCase($decision->recommendation, $user);
    }

    private function isAssignedToRecommendationCase(Recommendation $recommendation, User $user): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.read.assigned')
            && $user->hasRole('satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $recommendation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    private function statusByName(DecisionStatusEnum $status): DecisionStatus
    {
        return DecisionStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function advanceCaseToDecided(CaseRecord $case, User $actor): void
    {
        $status = CaseStatus::query()
            ->where('name', CaseStatusEnum::Decided->value)
            ->where('is_active', true)
            ->firstOrFail();
        $fromStatusCode = $case->status_code;
        $fromStatusName = $case->status?->name;

        $case->forceFill([
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'decision_at' => now(),
        ])->save();

        $this->auditLogService->record(
            action: AuditAction::CaseStatusChanged,
            category: AuditCategory::Case,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $case,
            metadata: [
                'case_id' => $case->id,
                'from_status' => $fromStatusName,
                'to_status' => $status->name,
                'source' => 'decision_finalization',
            ],
            beforeChanges: ['status_code' => $fromStatusCode],
            afterChanges: ['status_code' => $status->code],
        );
    }

    private function resolveStatus(string $status): DecisionStatus
    {
        $normalized = mb_strtolower(trim($status));

        return DecisionStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first() ?? throw $this->unprocessable('Unknown decision status');
    }

    private function recordStatusHistory(Decision $decision, ?string $fromStatusCode, string $toStatusCode, User $actor): void
    {
        $decision->statusHistories()->create([
            'from_status_code' => $fromStatusCode,
            'to_status_code' => $toStatusCode,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $beforeChanges
     * @param array<string, mixed> $afterChanges
     */
    private function recordAudit(
        AuditAction $action,
        User $actor,
        Decision $decision,
        array $metadata = [],
        array $beforeChanges = [],
        array $afterChanges = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Decision,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $decision,
            metadata: $metadata,
            beforeChanges: $beforeChanges,
            afterChanges: $afterChanges,
        );
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'recommendation.case',
            'status',
            'recorder',
            'statusHistories.fromStatus',
            'statusHistories.toStatus',
            'statusHistories.changedBy',
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
}
