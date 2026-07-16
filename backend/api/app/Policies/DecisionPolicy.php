<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Decision;
use App\Models\User;

class DecisionPolicy extends BasePolicy
{
    public function view(User $user, Decision $decision): bool
    {
        return $this->canManageDecision($user)
            || $this->canReadLeadershipDecision($user)
            || $this->canReadAssignedDecision($user, $decision);
    }

    public function update(User $user, Decision $decision): bool
    {
        return $this->canManageDecision($user);
    }

    public function updateStatus(User $user, Decision $decision): bool
    {
        return $this->canManageDecision($user);
    }

    public function canManageDecision(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'cases.record_decision')
            && $this->allowRole($user, 'admin');
    }

    private function canReadLeadershipDecision(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'cases.read.all')
            && $this->allowRole($user, 'super_admin');
    }

    public function canReadAssignedDecision(User $user, Decision $decision): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'cases.read.assigned')
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $decision->recommendation?->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
