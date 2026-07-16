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

    public function uploadFile(User $user, Evidence $evidence): bool
    {
        return $this->canAccessAssignedEvidence($user, $evidence->investigation, 'evidence.upload');
    }

    public function downloadFile(User $user, Evidence $evidence): bool
    {
        return $this->canAccessAssignedEvidence($user, $evidence->investigation, 'evidence.download');
    }

    public function previewFile(User $user, Evidence $evidence): bool
    {
        return $this->downloadFile($user, $evidence);
    }

    public function canManageInvestigationEvidence(User $user, Investigation $investigation): bool
    {
        return $this->canAccessAssignedEvidence($user, $investigation, 'evidence.upload');
    }

    private function canAccessAssignedEvidence(User $user, Investigation $investigation, string $capability): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'evidence.view.case')
            && $this->allowPermission($user, $capability)
            && $this->allowRole($user, 'satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $investigation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }
}
