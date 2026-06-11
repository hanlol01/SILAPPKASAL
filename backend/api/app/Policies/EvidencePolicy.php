<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\User;

class EvidencePolicy extends BasePolicy
{
    public function create(User $user, Investigation $investigation): bool
    {
        return $this->canManageInvestigationEvidence($user, $investigation);
    }

    public function view(User $user, Evidence $evidence): bool
    {
        return $this->canManageInvestigationEvidence($user, $evidence->investigation);
    }

    public function update(User $user, Evidence $evidence): bool
    {
        return $this->canManageInvestigationEvidence($user, $evidence->investigation);
    }

    public function updateStatus(User $user, Evidence $evidence): bool
    {
        return $this->canManageInvestigationEvidence($user, $evidence->investigation);
    }

    public function viewCustody(User $user, Evidence $evidence): bool
    {
        return $this->canManageInvestigationEvidence($user, $evidence->investigation);
    }

    public function canManageInvestigationEvidence(User $user, Investigation $investigation): bool
    {
        return $this->allowPermission($user, 'evidence.view.case')
            && $this->allowPermission($user, 'evidence.upload')
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $investigation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
