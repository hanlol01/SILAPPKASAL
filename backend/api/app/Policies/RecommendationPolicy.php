<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Recommendation;
use App\Models\User;

class RecommendationPolicy extends BasePolicy
{
    public function view(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadMetadata($user) || $this->canReadSensitive($user, $recommendation);
    }

    public function update(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadSensitive($user, $recommendation);
    }

    public function updateStatus(User $user, Recommendation $recommendation): bool
    {
        return $this->canReadSensitive($user, $recommendation);
    }

    public function canReadMetadata(User $user): bool
    {
        return ($this->allowPermission($user, 'cases.read.metadata') && $this->allowRole($user, 'admin', 'super_admin'))
            || ($this->allowPermission($user, 'cases.read.all') && $this->allowRole($user, 'super_admin'));
    }

    public function canReadSensitive(User $user, Recommendation $recommendation): bool
    {
        return $this->allowPermission($user, 'cases.recommend')
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $recommendation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
