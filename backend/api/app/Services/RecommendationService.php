<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
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

            return $recommendation->load(['case', 'status', 'author', 'statusHistories.fromStatus', 'statusHistories.toStatus', 'statusHistories.changedBy']);
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

            $recommendation->fill($data)->save();

            return $recommendation->load(['case', 'status', 'author', 'statusHistories.fromStatus', 'statusHistories.toStatus', 'statusHistories.changedBy']);
        });
    }

    public function updateStatus(Recommendation $recommendation, User $actor, string $requestedStatus): Recommendation
    {
        return DB::transaction(function () use ($recommendation, $actor, $requestedStatus): Recommendation {
            $recommendation = Recommendation::query()->with(['case.status', 'status'])->whereKey($recommendation->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedRecommender($recommendation->case, $actor);
            $this->ensureRecommendationOpen($recommendation);

            $nextStatus = $this->resolveStatus($requestedStatus);

            if (in_array($nextStatus->name, RecommendationStatusEnum::decisionOnlyValues(), true)) {
                throw $this->unprocessable('Recommendation status is reserved for decision workflow');
            }

            $allowedTransitions = $recommendation->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid recommendation status transition');
            }

            $fromStatusCode = $recommendation->status_code;
            $recommendation->forceFill([
                'status_code' => $nextStatus->code,
                'submitted_at' => $nextStatus->name === RecommendationStatusEnum::SubmittedToLeader->value ? now() : $recommendation->submitted_at,
            ])->save();

            $this->recordStatusHistory($recommendation, $fromStatusCode, $nextStatus->code, $actor);

            return $recommendation->load(['case', 'status', 'author', 'statusHistories.fromStatus', 'statusHistories.toStatus', 'statusHistories.changedBy']);
        });
    }

    public function canReadSensitive(Recommendation $recommendation, User $user): bool
    {
        return $user->hasPermission('cases.recommend')
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
        if (! in_array($recommendation->status?->name, [RecommendationStatusEnum::Drafting->value, RecommendationStatusEnum::Revised->value], true)) {
            throw $this->unprocessable('Recommendation cannot be edited in its current status');
        }
    }

    private function ensureRecommendationOpen(Recommendation $recommendation): void
    {
        if ($recommendation->status?->name === RecommendationStatusEnum::SubmittedToLeader->value) {
            throw $this->unprocessable('Submitted recommendations cannot transition in Milestone 8');
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

    private function statusByName(RecommendationStatusEnum $status): RecommendationStatus
    {
        return RecommendationStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
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
