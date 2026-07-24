<?php

namespace App\Policies;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\CaseCampusScope;

class CasePolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope) {}

    public function viewAny(User $user): bool
    {
        return $this->canReadMetadata($user) || $this->canReadAssigned($user);
    }

    public function view(User $user, CaseRecord $case): bool
    {
        if ($user->hasRole('super_admin') && $this->canReadMetadata($user)) {
            return true;
        }

        if ($user->hasRole('admin') && $this->canReadMetadata($user)) {
            return $this->campusScope->sameCampus($user, $case);
        }

        return $this->canReadAssigned($user) && $this->isAssignedTo($case, $user);
    }

    public function assign(User $user, CaseRecord $case): bool
    {
        return ! $case->isClosed()
            && $this->allowPermission($user, 'cases.assign_satgas')
            && $this->allowRole($user, 'admin')
            && $this->campusScope->sameCampus($user, $case);
    }

    public function updateStatus(User $user, CaseRecord $case): bool
    {
        return ! $case->isClosed()
            && ! $this->isLifecycleControlled($case)
            && $this->canReadAssigned($user)
            && $this->isAssignedTo($case, $user);
    }

    public function recordAssessment(User $user, CaseRecord $case): bool
    {
        return ! $case->isClosed()
            && $this->canReadAssigned($user)
            && $this->isAssignedTo($case, $user);
    }

    public function finalizeClosure(User $user, CaseRecord $case): bool
    {
        return ! $case->isClosed()
            && $this->allowPermission($user, 'cases.close')
            && $this->allowRole($user, 'satgas_ppks')
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

    private function isLifecycleControlled(CaseRecord $case): bool
    {
        return in_array($case->status?->name, [
            CaseStatusEnum::Recommendation->value,
            CaseStatusEnum::Decision->value,
        ], true);
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
