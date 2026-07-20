<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\CaseCampusScope;

class CaseFinalSummaryPolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope)
    {
    }

    public function view(User $user, CaseFinalSummary $summary): bool
    {
        $summary->loadMissing('case');

        if ($user->is_active && $user->hasRole('super_admin') && $user->hasPermission('cases.read.all')) {
            return $summary->isPublished() || $this->campusScope->canSensitiveOversight($user);
        }

        return $summary->case !== null && $this->canViewCase($user, $summary->case);
    }

    public function create(User $user, CaseRecord $case): bool
    {
        return $this->canManage($user, $case);
    }

    public function update(User $user, CaseFinalSummary $summary): bool
    {
        $summary->loadMissing('case');

        return $summary->case !== null && $this->canManage($user, $summary->case);
    }

    public function publish(User $user, CaseFinalSummary $summary): bool
    {
        return $this->update($user, $summary);
    }

    private function canManage(User $user, CaseRecord $case): bool
    {
        return $user->is_active
            && $user->hasRole('admin')
            && $user->hasPermission('cases.monitor')
            && $this->campusScope->sameCampus($user, $case);
    }

    private function canViewCase(User $user, CaseRecord $case): bool
    {
        if ($this->canManage($user, $case)) {
            return true;
        }

        return $user->is_active
            && $user->hasRole('satgas_ppks')
            && $user->hasPermission('cases.read.assigned')
            && CaseAssignment::query()
                ->where('case_id', $case->id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
