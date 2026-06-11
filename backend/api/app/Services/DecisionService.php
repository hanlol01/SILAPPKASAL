<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseAssignment;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DecisionService
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForRecommendation(Recommendation $recommendation, User $actor, array $data): Decision
    {
        $this->authorizeDecisionRecorder($actor);
        $this->ensureRecommendationCanReceiveDecision($recommendation);

        return DB::transaction(function () use ($recommendation, $actor, $data): Decision {
            $recommendation = Recommendation::query()
                ->with(['case.status', 'status', 'decision'])
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            return $decision->load($this->detailRelations());
        });
    }

    /**
     * @return Collection<int, Decision>
     */
    public function listForRecommendation(Recommendation $recommendation, User $user): Collection
    {
        $recommendation->loadMissing('case');

        if (! $this->canManageDecision($user) && ! $this->isAssignedToRecommendationCase($recommendation, $user)) {
            throw $this->forbidden();
        }

        return Decision::query()
            ->where('recommendation_id', $recommendation->id)
            ->with($this->detailRelations())
            ->latest()
            ->get();
    }

    public function loadForUser(Decision $decision, User $user): Decision
    {
        $decision->loadMissing('recommendation.case');

        if (! $this->canManageDecision($user) && ! $this->isAssignedToRecommendationCase($decision->recommendation, $user)) {
            throw $this->forbidden();
        }

        return $decision->load($this->detailRelations());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Decision $decision, User $actor, array $data): Decision
    {
        $this->authorizeDecisionRecorder($actor);

        return DB::transaction(function () use ($decision, $data): Decision {
            $decision = Decision::query()->with('status')->whereKey($decision->id)->lockForUpdate()->firstOrFail();

            $this->ensureDecisionEditable($decision);

            $decision->fill($data)->save();

            return $decision->load($this->detailRelations());
        });
    }

    public function updateStatus(Decision $decision, User $actor, string $requestedStatus): Decision
    {
        $this->authorizeDecisionRecorder($actor);

        return DB::transaction(function () use ($decision, $actor, $requestedStatus): Decision {
            $decision = Decision::query()->with('status')->whereKey($decision->id)->lockForUpdate()->firstOrFail();

            $this->ensureDecisionOpen($decision);

            $nextStatus = $this->resolveStatus($requestedStatus);
            $allowedTransitions = $decision->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid decision status transition');
            }

            $fromStatusCode = $decision->status_code;
            $decision->forceFill([
                'status_code' => $nextStatus->code,
                'finalized_at' => $nextStatus->name === DecisionStatusEnum::Finalized->value ? now() : $decision->finalized_at,
            ])->save();

            $this->recordStatusHistory($decision, $fromStatusCode, $nextStatus->code, $actor);

            if ($nextStatus->name === DecisionStatusEnum::Finalized->value) {
                $this->notificationService->decisionFinalized($decision);
            }

            return $decision->load($this->detailRelations());
        });
    }

    private function ensureRecommendationCanReceiveDecision(Recommendation $recommendation): void
    {
        $recommendation->loadMissing(['case.status', 'status', 'decision']);

        if ($recommendation->status?->name !== RecommendationStatusEnum::SubmittedToLeader->value) {
            throw $this->unprocessable('Decision requires a submitted recommendation');
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

    private function authorizeDecisionRecorder(User $actor): void
    {
        if (! $this->canManageDecision($actor)) {
            throw $this->forbidden();
        }
    }

    private function canManageDecision(User $user): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.record_decision')
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
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
