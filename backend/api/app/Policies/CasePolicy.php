<?php

namespace App\Policies;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\User;

class CasePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canReadMetadata($user) || $this->canReadAssigned($user);
    }

    public function view(User $user, CaseRecord $case): bool
    {
        if ($this->canReadMetadata($user)) {
            return true;
        }

        return $this->canReadAssigned($user) && $this->isAssignedTo($case, $user);
    }

    public function assign(User $user, CaseRecord $case): bool
    {
        return ! $this->isClosed($case)
            && $this->allowPermission($user, 'cases.assign_satgas')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    public function updateStatus(User $user, CaseRecord $case): bool
    {
        return ! $this->isClosed($case)
            && $this->canReadAssigned($user)
            && $this->isAssignedTo($case, $user);
    }

    public function canReadMetadata(User $user): bool
    {
        return ($this->allowPermission($user, 'cases.read.metadata') && $this->allowRole($user, 'admin', 'super_admin'))
            || ($this->allowPermission($user, 'cases.read.all') && $this->allowRole($user, 'super_admin'));
    }

    public function canReadAssigned(User $user): bool
    {
        return $this->allowPermission($user, 'cases.read.assigned')
            && $this->allowRole($user, 'satgas_ppks');
    }

    private function isClosed(CaseRecord $case): bool
    {
        return $case->status?->name === CaseStatusEnum::Closed->value;
    }

    private function isAssignedTo(CaseRecord $case, User $user): bool
    {
        return CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}
