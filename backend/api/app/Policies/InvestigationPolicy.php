<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Investigation;
use App\Models\User;

class InvestigationPolicy extends BasePolicy
{
    public function view(User $user, Investigation $investigation): bool
    {
        return $this->canReadMetadata($user) || $this->canReadSensitive($user, $investigation);
    }

    public function updateStatus(User $user, Investigation $investigation): bool
    {
        return $this->canReadSensitive($user, $investigation);
    }

    public function addActivity(User $user, Investigation $investigation): bool
    {
        return $this->canReadSensitive($user, $investigation);
    }

    public function canReadMetadata(User $user): bool
    {
        return ($this->allowPermission($user, 'cases.read.metadata') && $this->allowRole($user, 'admin', 'super_admin'))
            || ($this->allowPermission($user, 'cases.read.all') && $this->allowRole($user, 'super_admin'));
    }

    public function canReadSensitive(User $user, Investigation $investigation): bool
    {
        return $this->allowPermission($user, 'cases.investigate')
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $investigation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
