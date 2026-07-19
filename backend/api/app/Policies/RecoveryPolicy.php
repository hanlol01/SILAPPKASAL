<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Decision;
use App\Models\Recovery;
use App\Models\User;
use App\Support\CaseCampusScope;

class RecoveryPolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope)
    {
    }

    public function create(User $user, Decision $decision): bool
    {
        return $this->canManageDecisionRecovery($user, $decision);
    }

    public function view(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user, $recovery)
            || ($user->hasRole('super_admin') && $user->hasPermission('cases.read.all'))
            || $this->canReadAssignedRecovery($user, $recovery);
    }

    public function update(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user, $recovery);
    }

    public function updateStatus(User $user, Recovery $recovery): bool
    {
        return $this->canManageRecovery($user, $recovery);
    }

    public function createMonitoring(User $user, Recovery $recovery): bool
    {
        return $this->canReadAssignedRecovery($user, $recovery);
    }

    public function canManageRecovery(User $user, Recovery $recovery): bool
    {
        return $this->allowPermission($user, 'cases.monitor')
            && $this->allowRole($user, 'admin')
            && $this->campusScope->sameCampus($user, $recovery);
    }

    private function canManageDecisionRecovery(User $user, Decision $decision): bool
    {
        return $this->allowPermission($user, 'cases.monitor')
            && $this->allowRole($user, 'admin')
            && $this->campusScope->sameCampus($user, $decision);
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
