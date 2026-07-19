<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Recommendation;
use App\Models\User;
use App\Support\CaseCampusScope;

class RecommendationPolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope)
    {
    }

    public function view(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadMetadata($user, $recommendation) || $this->canReadSensitive($user, $recommendation);
    }

    public function update(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadAssignedSensitive($user, $recommendation);
    }

    public function updateStatus(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadAssignedSensitive($user, $recommendation);
    }

    public function submit(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadAssignedSensitive($user, $recommendation);
    }

    public function review(User $user, Recommendation $recommendation): bool
    {
        return $this->canReview($user, $recommendation);
    }

    public function canReadMetadata(User $user, Recommendation $recommendation): bool
    {
        return ($this->allowPermission($user, 'cases.read.metadata') && $this->allowRole($user, 'admin') && $this->campusScope->sameCampus($user, $recommendation))
            || ($this->allowPermission($user, 'cases.read.all') && $this->allowRole($user, 'super_admin'));
    }

    public function canReadSensitive(User $user, Recommendation $recommendation): bool
    {
        return $this->canReview($user, $recommendation)
            || $this->campusScope->canSensitiveOversight($user)
            || $this->canReadAssignedSensitive($user, $recommendation);
    }

    private function canReview(User $user, Recommendation $recommendation): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'cases.review_recommendation')
            && $this->allowRole($user, 'admin')
            && $this->campusScope->sameCampus($user, $recommendation);
    }

    private function canReadAssignedSensitive(User $user, Recommendation $recommendation): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'cases.recommend')
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $recommendation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
