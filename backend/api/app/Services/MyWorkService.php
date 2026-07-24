<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseRecord;
use App\Models\Investigation;
use App\Models\User;
use App\Support\CaseCampusScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MyWorkService
{
    public function __construct(private readonly CaseCampusScope $campusScope) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        return [
            'scope' => $this->scopeName($user),
            'assigned_active_cases' => $this->casesQuery($user)->operationallyActive()->count(),
            'pending_investigations' => $this->pendingInvestigationsQuery($user)->count(),
            'pending_recommendations' => $this->pendingRecommendationsCount($user),
            'pending_decisions' => $this->pendingDecisionCount($user),
            'pending_recoveries' => $this->pendingRecoveryCount($user),
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function cases(User $user, array $filters = []): LengthAwarePaginator
    {
        $cases = $this->casesQuery($user)
            ->with(['status', 'activeAssignments.satgas'])
            ->when(empty($filters['status']), fn (Builder $query): Builder => $query->operationallyActive())
            ->when(! empty($filters['status']), fn (Builder $query): Builder => $this->filterCaseStatus($query, (string) $filters['status']))
            ->latest('forwarded_at')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return $this->throughPaginator($cases, fn (CaseRecord $case): array => $this->caseItem($case, $user));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function investigations(User $user, array $filters = []): LengthAwarePaginator
    {
        $investigations = $this->pendingInvestigationsQuery($user)
            ->with(['case.status', 'case.activeAssignments.satgas', 'status'])
            ->when(! empty($filters['status']), fn (Builder $query): Builder => $this->filterInvestigationStatus($query, (string) $filters['status']))
            ->latest('started_at')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return $this->throughPaginator($investigations, fn (Investigation $investigation): array => [
            'work_type' => 'pending_investigation',
            'investigation_id' => $investigation->id,
            'case_id' => $investigation->case_id,
            'case_number' => $investigation->case?->case_number,
            'registration_number' => $investigation->case?->registration_number,
            'case_status_code' => $investigation->case?->status_code,
            'case_status_name' => $investigation->case?->status?->name,
            'status_code' => $investigation->status_code,
            'status_name' => $investigation->status?->name,
            'lead_investigator_id' => $investigation->lead_investigator_id,
            'started_at' => $investigation->started_at?->toJSON(),
            'completed_at' => $investigation->completed_at?->toJSON(),
            'assignment' => $this->assignmentMetadata($investigation->case, $user),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function recommendations(User $user, array $filters = []): LengthAwarePaginator
    {
        $recommendations = $this->pendingRecommendationsQuery($user)
            ->paginate((int) ($filters['per_page'] ?? 15));

        $items = $recommendations
            ->getCollection()
            ->map(fn (CaseRecord $case): array => $this->recommendationItem($case, $user))
            ->when(! empty($filters['status']), fn (Collection $items): Collection => $items->filter(
                fn (array $item): bool => $this->matchesStatusFilter($item['status_code'] ?? null, $item['status_name'] ?? null, (string) $filters['status'])
            )->values());

        $recommendations->setCollection($items);

        return $recommendations;
    }

    private function casesQuery(User $user): Builder
    {
        $query = CaseRecord::query();

        if ($user->hasRole('super_admin')) {
            $query->whereRaw('1 = 0');
        } elseif ($user->hasRole('admin')) {
            $this->campusScope->scopeCases($query, $user);
        } elseif ($this->isSatgas($user)) {
            $query->whereHas('activeAssignments', fn (Builder $query): Builder => $query->where('satgas_id', $user->id));
        }

        return $query;
    }

    private function pendingInvestigationsQuery(User $user): Builder
    {
        return Investigation::query()
            ->whereNull('completed_at')
            ->whereHas('status', fn (Builder $query): Builder => $query->where('name', '!=', InvestigationStatusEnum::Completed->value))
            ->whereHas('case', function (Builder $query) use ($user): void {
                $query->operationallyActive();

                if ($user->hasRole('super_admin')) {
                    $query->whereRaw('1 = 0');
                } elseif ($user->hasRole('admin')) {
                    $this->campusScope->scopeCases($query, $user);
                } elseif ($this->isSatgas($user)) {
                    $query->whereHas('activeAssignments', fn (Builder $assignmentQuery): Builder => $assignmentQuery->where('satgas_id', $user->id));
                }
            });
    }

    private function pendingRecommendationsQuery(User $user): Builder
    {
        $query = CaseRecord::query()
            ->with(['status', 'recommendation.status', 'activeAssignments.satgas'])
            ->whereHas('status', fn (Builder $query): Builder => $query->where('name', CaseStatusEnum::Recommendation->value))
            ->operationallyActive()
            ->latest('recommendation_at');

        if ($user->hasRole('super_admin')) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin')) {
            $this->campusScope->scopeCases($query, $user);

            return $query->whereHas('recommendation.status', fn (Builder $statusQuery): Builder => $statusQuery
                ->whereIn('name', RecommendationStatusEnum::submittedReviewValues()));
        }

        return $query
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('recommendation')
                    ->orWhereHas('recommendation.status', fn (Builder $statusQuery): Builder => $statusQuery
                        ->whereNotIn('name', RecommendationStatusEnum::submittedReviewValues())
                        ->whereNotIn('name', RecommendationStatusEnum::decisionOnlyValues()));
            })
            ->when($this->isSatgas($user), fn (Builder $query): Builder => $query->whereHas('activeAssignments', fn (Builder $assignmentQuery): Builder => $assignmentQuery->where('satgas_id', $user->id)));
    }

    private function pendingRecommendationsCount(User $user): int
    {
        return $this->pendingRecommendationsQuery($user)->count();
    }

    private function pendingDecisionCount(User $user): int
    {
        if (! $user->hasRole('admin')) {
            return 0;
        }

        $query = CaseRecord::query()
            ->whereHas('status', fn (Builder $status): Builder => $status->where('name', CaseStatusEnum::Decision->value))
            ->operationallyActive()
            ->whereHas('recommendation.status', fn (Builder $status): Builder => $status->where('name', RecommendationStatusEnum::Accepted->value));
        $this->campusScope->scopeCases($query, $user);

        return $query->count();
    }

    private function pendingRecoveryCount(User $user): int
    {
        if (! $user->hasRole('admin')) {
            return 0;
        }

        $query = CaseRecord::query()
            ->operationallyActive()
            ->whereHas('status', fn (Builder $status): Builder => $status
                ->whereIn('name', [CaseStatusEnum::Decided->value, CaseStatusEnum::Recovery->value]));
        $this->campusScope->scopeCases($query, $user);

        return $query->count();
    }

    private function caseItem(CaseRecord $case, User $user): array
    {
        return [
            'work_type' => 'case',
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'registration_number' => $case->registration_number,
            'status_code' => $case->status_code,
            'status_name' => $case->status?->name,
            'current_stage' => $case->current_stage,
            'forwarded_at' => $case->forwarded_at?->toJSON(),
            'assignment' => $this->assignmentMetadata($case, $user),
        ];
    }

    private function recommendationItem(CaseRecord $case, User $user): array
    {
        $recommendation = $case->recommendation;

        return [
            'work_type' => $recommendation ? 'pending_recommendation' : 'recommendation_needed',
            'recommendation_id' => $recommendation?->id,
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'registration_number' => $case->registration_number,
            'case_status_code' => $case->status_code,
            'case_status_name' => $case->status?->name,
            'status_code' => $recommendation?->status_code,
            'status_name' => $recommendation?->status?->name,
            'author_id' => $recommendation?->author_id,
            'submitted_at' => $recommendation?->submitted_at?->toJSON(),
            'assignment' => $this->assignmentMetadata($case, $user),
        ];
    }

    private function assignmentMetadata(?CaseRecord $case, User $user): ?array
    {
        if (! $case) {
            return null;
        }

        $assignment = $case->activeAssignments
            ->first(fn ($assignment): bool => $this->isSatgas($user)
                ? (int) $assignment->satgas_id === (int) $user->id
                : true);

        if (! $assignment) {
            return null;
        }

        return [
            'satgas_id' => $assignment->satgas_id,
            'is_lead' => (bool) $assignment->is_lead,
            'assigned_at' => $assignment->assigned_at?->toJSON(),
        ];
    }

    private function filterCaseStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('status', fn (Builder $statusQuery): Builder => $this->filterStatusRelation($statusQuery, $status));
    }

    private function filterInvestigationStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('status', fn (Builder $statusQuery): Builder => $this->filterStatusRelation($statusQuery, $status));
    }

    private function filterStatusRelation(Builder $query, string $status): Builder
    {
        $normalized = mb_strtolower(trim($status));

        return $query->where(function (Builder $query) use ($normalized): void {
            $query->whereRaw('LOWER(code) = ?', [$normalized])
                ->orWhereRaw('LOWER(name) = ?', [$normalized]);
        });
    }

    private function matchesStatusFilter(?string $code, ?string $name, string $filter): bool
    {
        $filter = mb_strtolower(trim($filter));

        return mb_strtolower((string) $code) === $filter || mb_strtolower((string) $name) === $filter;
    }

    private function isSatgas(User $user): bool
    {
        return $user->hasRole('satgas_ppks');
    }

    private function scopeName(User $user): string
    {
        return $this->isSatgas($user)
            ? 'assigned_cases'
            : ($user->hasRole('admin') ? 'campus' : 'oversight_read_only');
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  LengthAwarePaginator<TKey, TValue>  $paginator
     * @param  callable(TValue): array<string, mixed>  $callback
     * @return LengthAwarePaginator<TKey, array<string, mixed>>
     */
    private function throughPaginator(LengthAwarePaginator $paginator, callable $callback): LengthAwarePaginator
    {
        $paginator->setCollection($paginator->getCollection()->map($callback));

        return $paginator;
    }
}
