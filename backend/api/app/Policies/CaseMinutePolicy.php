<?php

namespace App\Policies;

use App\Models\CaseMinute;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\CaseCampusScope;

class CaseMinutePolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope) {}

    public function viewAny(User $user): bool
    {
        return $this->canReadInternal($user) || $this->canReadMetadata($user);
    }

    public function view(User $user, CaseMinute|CaseRecord $subject): bool
    {
        $case = $subject instanceof CaseMinute
            ? $subject->loadMissing('case')->case
            : $subject;

        return $case !== null && ($this->canReadMetadata($user) || $this->canReadInternalForCase($user, $case));
    }

    public function create(User $user, CaseRecord $case): bool
    {
        return $this->canWrite($user, $case);
    }

    public function update(User $user, CaseMinute $minute): bool
    {
        $minute->loadMissing('case');

        return $minute->case !== null && $this->canWrite($user, $minute->case);
    }

    public function createRevision(User $user, CaseMinute $minute): bool
    {
        return $this->update($user, $minute);
    }

    public function finalize(User $user, CaseMinute $minute): bool
    {
        $minute->loadMissing('case');

        return $minute->case !== null
            && $user->is_active
            && $user->hasRole('admin')
            && $user->hasPermission('case_minutes.finalize')
            && $this->campusScope->sameCampus($user, $minute->case);
    }

    public function canReadMetadata(User $user): bool
    {
        return $user->is_active
            && $user->hasRole('super_admin')
            && $user->hasPermission('cases.read.all');
    }

    private function canReadInternal(User $user): bool
    {
        return $user->is_active
            && (($user->hasRole('admin') && $user->hasPermission('case_minutes.read'))
                || ($user->hasRole('satgas_ppks') && $user->hasPermission('case_minutes.read')));
    }

    private function canReadInternalForCase(User $user, CaseRecord $case): bool
    {
        if (! $this->canReadInternal($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return $this->campusScope->sameCampus($user, $case);
        }

        return $this->campusScope->sameOperationalCampus($user, $case) && $case->isAssignedTo($user);
    }

    private function canWrite(User $user, CaseRecord $case): bool
    {
        if (! $user->is_active || ! $user->hasPermission('case_minutes.write')) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return $this->campusScope->sameCampus($user, $case);
        }

        return $user->hasRole('satgas_ppks')
            && $this->campusScope->sameOperationalCampus($user, $case)
            && $case->isAssignedTo($user);
    }
}
