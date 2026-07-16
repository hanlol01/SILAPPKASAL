<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Investigation;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForCase(CaseRecord $case, User $actor, array $data): Recommendation
    {
        $this->authorizeAssignedRecommender($case, $actor);
        $this->ensureCaseCanReceiveRecommendation($case);
        $investigation = $this->validatedCompletedInvestigation($case, (int) $data['investigation_id']);

        return DB::transaction(function () use ($case, $actor, $data, $investigation): Recommendation {
            $case = CaseRecord::query()->with(['status', 'recommendation'])->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->ensureCaseCanReceiveRecommendation($case);
            $this->validatedCompletedInvestigation($case, $investigation->id);
            $status = $this->statusByName(RecommendationStatusEnum::Drafting);

            $recommendation = Recommendation::query()->create([
                'case_id' => $case->id,
                'investigation_id' => $investigation->id,
                'author_id' => $actor->id,
                'status_code' => $status->code,
                'conclusion' => $data['conclusion'],
                'recommended_actions' => $data['recommended_actions'],
                'sanction_recommendation' => $data['sanction_recommendation'] ?? null,
                'recovery_recommendation' => $data['recovery_recommendation'] ?? null,
                'prevention_recommendation' => $data['prevention_recommendation'] ?? null,
            ]);

            $this->recordStatusHistory($recommendation, null, $status->code, $actor);
            $recommendation = $recommendation->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecommendationCreated,
                $actor,
                $recommendation,
                [
                    'recommendation_id' => $recommendation->id,
                    'case_id' => $recommendation->case_id,
                    'investigation_id' => $recommendation->investigation_id,
                    'status_code' => $recommendation->status_code,
                ],
                afterChanges: ['status_code' => $recommendation->status_code],
            );

            $this->notificationService->recommendationCreated($recommendation);

            return $recommendation;
        });
    }

    /**
     * @return Collection<int, Recommendation>
     */
    public function listForCase(CaseRecord $case, User $user): Collection
    {
        if (! $this->canReadMetadata($user) && ! $this->isAssignedToCase($case, $user)) {
            throw $this->forbidden();
        }

        return Recommendation::query()
            ->where('case_id', $case->id)
            ->with(['case', 'status', 'author'])
            ->latest()
            ->get();
    }

    public function loadForUser(Recommendation $recommendation, User $user): Recommendation
    {
        $relations = ['case', 'status', 'author'];

        if ($this->canReadSensitive($recommendation, $user)) {
            $relations[] = 'statusHistories.fromStatus';
            $relations[] = 'statusHistories.toStatus';
            $relations[] = 'statusHistories.changedBy';
            $relations[] = 'returnedBy';
            $relations[] = 'approvedBy';
        }

        return $recommendation->load($relations);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Recommendation $recommendation, User $actor, array $data): Recommendation
    {
        return DB::transaction(function () use ($recommendation, $actor, $data): Recommendation {
            $recommendation = Recommendation::query()->with(['case.status', 'status'])->whereKey($recommendation->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedRecommender($recommendation->case, $actor);
            $this->ensureRecommendationEditable($recommendation);

            $before = $recommendation->only([
                'conclusion',
                'recommended_actions',
                'sanction_recommendation',
                'recovery_recommendation',
                'prevention_recommendation',
            ]);

            $recommendation->fill($data)->save();

            $after = $recommendation->only([
                'conclusion',
                'recommended_actions',
                'sanction_recommendation',
                'recovery_recommendation',
                'prevention_recommendation',
            ]);

            $recommendation = $recommendation->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecommendationUpdated,
                $actor,
                $recommendation,
                [
                    'recommendation_id' => $recommendation->id,
                    'case_id' => $recommendation->case_id,
                ],
                beforeChanges: array_intersect_key($before, $data),
                afterChanges: array_intersect_key($after, $data),
            );

            return $recommendation;
        });
    }

    public function submit(Recommendation $recommendation, User $actor): Recommendation
    {
        return DB::transaction(function () use ($recommendation, $actor): Recommendation {
            $recommendation = Recommendation::query()
                ->with(['case.status', 'status'])
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeAssignedRecommender($recommendation->case, $actor);
            $this->ensureRecommendationEditableForSubmission($recommendation);

            if ($recommendation->case?->status?->name !== CaseStatusEnum::Recommendation->value) {
                throw $this->unprocessable('Case must remain in recommendation status during submission');
            }

            $nextStatus = $this->statusByName(RecommendationStatusEnum::SubmittedToLeader);
            $fromStatusCode = $recommendation->status_code;
            $fromStatusName = $recommendation->status?->name;

            $recommendation->forceFill([
                'status_code' => $nextStatus->code,
                'submitted_at' => now(),
                'returned_by' => null,
                'returned_at' => null,
                'revision_note' => null,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            $this->recordStatusHistory($recommendation, $fromStatusCode, $nextStatus->code, $actor);
            $recommendation = $recommendation->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecommendationSubmitted,
                $actor,
                $recommendation,
                [
                    'recommendation_id' => $recommendation->id,
                    'case_id' => $recommendation->case_id,
                    'from_status' => $fromStatusName,
                    'to_status' => $nextStatus->name,
                ],
                beforeChanges: ['status_code' => $fromStatusCode],
                afterChanges: ['status_code' => $recommendation->status_code],
            );

            $this->notificationService->recommendationSubmittedToLeader($recommendation);

            return $recommendation;
        });
    }

    /**
     * @param array{action: string, revision_note?: string|null} $data
     */
    public function review(Recommendation $recommendation, User $actor, array $data): Recommendation
    {
        return DB::transaction(function () use ($recommendation, $actor, $data): Recommendation {
            $recommendation = Recommendation::query()
                ->with(['status'])
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $case = CaseRecord::query()
                ->with('status')
                ->whereKey($recommendation->case_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeRecommendationReviewer($actor);

            if ($recommendation->status?->name !== RecommendationStatusEnum::SubmittedToLeader->value) {
                throw $this->unprocessable('Recommendation is no longer awaiting leadership review');
            }

            if ($case->status?->name !== CaseStatusEnum::Recommendation->value) {
                throw $this->unprocessable('Case is no longer in recommendation status');
            }

            $fromStatusCode = $recommendation->status_code;
            $fromStatusName = $recommendation->status?->name;

            if ($data['action'] === 'approve') {
                $nextStatus = $this->statusByName(RecommendationStatusEnum::Accepted);
                $recommendation->forceFill([
                    'status_code' => $nextStatus->code,
                    'returned_by' => null,
                    'returned_at' => null,
                    'revision_note' => null,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ])->save();
                $this->recordStatusHistory($recommendation, $fromStatusCode, $nextStatus->code, $actor);
                $this->advanceCase($case, CaseStatusEnum::Decision, $actor);
                $auditAction = AuditAction::RecommendationApproved;
            } else {
                $revisionNote = trim((string) ($data['revision_note'] ?? ''));

                if (mb_strlen($revisionNote) < 10 || mb_strlen($revisionNote) > 5000) {
                    throw $this->unprocessable('A revision note between 10 and 5000 characters is required');
                }

                $nextStatus = $this->statusByName(RecommendationStatusEnum::Revised);
                $recommendation->forceFill([
                    'status_code' => $nextStatus->code,
                    'returned_by' => $actor->id,
                    'returned_at' => now(),
                    'revision_note' => $revisionNote,
                    'approved_by' => null,
                    'approved_at' => null,
                ])->save();
                $this->recordStatusHistory($recommendation, $fromStatusCode, $nextStatus->code, $actor);
                $auditAction = AuditAction::RecommendationReturnedForRevision;
            }

            $recommendation = $recommendation->load($this->detailRelations());
            $this->recordAudit(
                $auditAction,
                $actor,
                $recommendation,
                [
                    'recommendation_id' => $recommendation->id,
                    'case_id' => $recommendation->case_id,
                    'from_status' => $fromStatusName,
                    'to_status' => $nextStatus->name,
                ],
                beforeChanges: ['status_code' => $fromStatusCode],
                afterChanges: ['status_code' => $recommendation->status_code],
            );

            if ($data['action'] === 'approve') {
                $this->notificationService->recommendationApproved($recommendation);
            } else {
                $this->notificationService->recommendationReturnedForRevision($recommendation);
            }

            return $recommendation;
        });
    }

    public function updateStatus(Recommendation $recommendation, User $actor, string $requestedStatus): Recommendation
    {
        return DB::transaction(function () use ($recommendation, $actor, $requestedStatus): Recommendation {
            $recommendation = Recommendation::query()->with(['case.status', 'status'])->whereKey($recommendation->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedRecommender($recommendation->case, $actor);
            $this->ensureRecommendationOpen($recommendation);

            $nextStatus = $this->resolveStatus($requestedStatus);

            if ($nextStatus->name !== RecommendationStatusEnum::InternalReview->value) {
                throw $this->unprocessable('Recommendation lifecycle status requires its dedicated workflow action');
            }

            $allowedTransitions = $recommendation->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid recommendation status transition');
            }

            $fromStatusCode = $recommendation->status_code;
            $fromStatusName = $recommendation->status?->name;
            $recommendation->forceFill(['status_code' => $nextStatus->code])->save();

            $this->recordStatusHistory($recommendation, $fromStatusCode, $nextStatus->code, $actor);
            $recommendation = $recommendation->load($this->detailRelations());

            $this->recordAudit(
                AuditAction::RecommendationStatusChanged,
                $actor,
                $recommendation,
                [
                    'recommendation_id' => $recommendation->id,
                    'case_id' => $recommendation->case_id,
                    'from_status' => $fromStatusName,
                    'to_status' => $nextStatus->name,
                ],
                beforeChanges: ['status_code' => $fromStatusCode],
                afterChanges: ['status_code' => $recommendation->status_code],
            );

            $this->notificationService->recommendationStatusChanged($recommendation);

            return $recommendation;
        });
    }

    /**
     * @return array{current_status: array{code: string|null, name: string|null, description: string|null}, valid_transitions: list<array{code: string, name: string, description: string|null}>}
     */
    public function statusOptions(Recommendation $recommendation, User $user): array
    {
        $recommendation->loadMissing('status');

        $statuses = [];

        if ($this->canReadSensitive($recommendation, $user)) {
            $transitionNames = collect($recommendation->status?->valid_transitions ?? [])
                ->filter(fn (string $name): bool => $name === RecommendationStatusEnum::InternalReview->value)
                ->values()
                ->all();

            $statuses = RecommendationStatus::query()
                ->where('is_active', true)
                ->whereIn('name', $transitionNames)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (RecommendationStatus $status): array => [
                    'code' => $status->code,
                    'name' => $status->name,
                    'description' => $status->description,
                ])
                ->values()
                ->all();
        }

        return [
            'current_status' => [
                'code' => $recommendation->status?->code,
                'name' => $recommendation->status?->name,
                'description' => $recommendation->status?->description,
            ],
            'valid_transitions' => $statuses,
        ];
    }

    public function canReadSensitive(Recommendation $recommendation, User $user): bool
    {
        if ($user->is_active && $user->hasPermission('cases.review_recommendation') && $user->hasRole('super_admin')) {
            return true;
        }

        return $user->is_active
            && $user->hasPermission('cases.recommend')
            && $user->hasRole('satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $recommendation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    private function authorizeAssignedRecommender(CaseRecord $case, User $actor): void
    {
        if (! $actor->is_active || ! $actor->hasPermission('cases.recommend') || ! $actor->hasRole('satgas_ppks') || ! $this->isAssignedToCase($case, $actor)) {
            throw $this->forbidden();
        }
    }

    private function authorizeRecommendationReviewer(User $actor): void
    {
        if (! $actor->is_active || ! $actor->hasPermission('cases.review_recommendation') || ! $actor->hasRole('super_admin')) {
            throw $this->forbidden();
        }
    }

    private function advanceCase(CaseRecord $case, CaseStatusEnum $targetStatus, User $actor): void
    {
        $status = $this->caseStatusByName($targetStatus);
        $fromStatusCode = $case->status_code;
        $fromStatusName = $case->status?->name;

        $case->forceFill([
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'decision_at' => $targetStatus === CaseStatusEnum::Decision ? now() : $case->decision_at,
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
                'source' => 'recommendation_approval',
            ],
            beforeChanges: ['status_code' => $fromStatusCode],
            afterChanges: ['status_code' => $status->code],
        );
    }

    private function ensureCaseCanReceiveRecommendation(CaseRecord $case): void
    {
        $case->loadMissing(['status', 'recommendation']);

        if ($case->status?->name !== CaseStatusEnum::Recommendation->value) {
            throw $this->unprocessable('Case must be in recommendation status');
        }

        if ($case->recommendation()->exists()) {
            throw $this->unprocessable('Case already has a recommendation');
        }
    }

    private function validatedCompletedInvestigation(CaseRecord $case, int $investigationId): Investigation
    {
        $investigation = Investigation::query()
            ->with('status')
            ->whereKey($investigationId)
            ->where('case_id', $case->id)
            ->first();

        if (! $investigation) {
            throw $this->unprocessable('Investigation must belong to the case');
        }

        if ($investigation->status?->name !== InvestigationStatusEnum::Completed->value || ! $investigation->completed_at) {
            throw $this->unprocessable('Recommendation requires a completed investigation');
        }

        return $investigation;
    }

    private function ensureRecommendationEditable(Recommendation $recommendation): void
    {
        if (! in_array($recommendation->status?->name, [
            RecommendationStatusEnum::Drafting->value,
            RecommendationStatusEnum::InternalReview->value,
            RecommendationStatusEnum::Revised->value,
        ], true)) {
            throw $this->unprocessable('Recommendation cannot be edited in its current status');
        }
    }

    private function ensureRecommendationEditableForSubmission(Recommendation $recommendation): void
    {
        if (! in_array($recommendation->status?->name, [
            RecommendationStatusEnum::Drafting->value,
            RecommendationStatusEnum::InternalReview->value,
            RecommendationStatusEnum::Revised->value,
        ], true)) {
            throw $this->unprocessable('Recommendation cannot be submitted in its current status');
        }
    }

    private function ensureRecommendationOpen(Recommendation $recommendation): void
    {
        if (! in_array($recommendation->status?->name, [
            RecommendationStatusEnum::Drafting->value,
            RecommendationStatusEnum::Revised->value,
        ], true)) {
            throw $this->unprocessable('Recommendation status cannot be changed through the legacy endpoint');
        }
    }

    private function recordStatusHistory(Recommendation $recommendation, ?string $fromStatusCode, string $toStatusCode, User $actor): void
    {
        $recommendation->statusHistories()->create([
            'from_status_code' => $fromStatusCode,
            'to_status_code' => $toStatusCode,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    private function isAssignedToCase(CaseRecord $case, User $user): bool
    {
        return CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    private function canReadMetadata(User $user): bool
    {
        return ($user->hasPermission('cases.read.metadata') && ($user->hasRole('admin') || $user->hasRole('super_admin')))
            || ($user->hasPermission('cases.read.all') && $user->hasRole('super_admin'));
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $beforeChanges
     * @param array<string, mixed> $afterChanges
     */
    private function recordAudit(
        AuditAction $action,
        User $actor,
        Recommendation $subject,
        array $metadata = [],
        array $beforeChanges = [],
        array $afterChanges = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Recommendation,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $subject,
            metadata: $metadata,
            beforeChanges: $beforeChanges,
            afterChanges: $afterChanges,
        );
    }

    private function statusByName(RecommendationStatusEnum $status): RecommendationStatus
    {
        return RecommendationStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function caseStatusByName(CaseStatusEnum $status): CaseStatus
    {
        return CaseStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'case',
            'status',
            'author',
            'returnedBy',
            'approvedBy',
            'statusHistories.fromStatus',
            'statusHistories.toStatus',
            'statusHistories.changedBy',
        ];
    }

    private function resolveStatus(string $status): RecommendationStatus
    {
        $normalized = mb_strtolower(trim($status));

        return RecommendationStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first() ?? throw $this->unprocessable('Unknown recommendation status');
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
