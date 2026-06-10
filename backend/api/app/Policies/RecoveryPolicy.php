<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Decision;
use App\Models\Recovery;
use App\Models\User;

class RecoveryPolicy extends BasePolicy
{
    public function create(User $user, Decision $decision): bool
    {
        return $this->canManageRecovery($user);
    }

    public function view(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user) || $this->canReadAssignedRecovery($user, $recovery);
    }

    public function update(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user);
    }

    public function updateStatus(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user);
    }

    public function createMonitoring(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user) || $this->canReadAssignedRecovery($user, $recovery);
    }

    public function canManageRecovery(User $user): bool
    {
        return $this->allowPermission($user, 'cases.monitor')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    public function canReadAssignedRecovery(User $user, Recovery $recovery): bool
    {
        return $this->allowPermission($user, 'cases.monitor')
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $recovery->decision?->recommendation?->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
