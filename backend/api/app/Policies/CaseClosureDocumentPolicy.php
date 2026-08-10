<?php

namespace App\Policies;

use App\Models\CaseClosureDocument;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\CaseCampusScope;

class CaseClosureDocumentPolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope) {}

    public function view(User $user, CaseClosureDocument $document): bool
    {
        return $this->canRead($user, $document->loadMissing('case.report.reporter'));
    }

    public function issue(User $user, CaseRecord $case): bool
    {
        return $user->is_active
            && $user->hasRole('admin')
            && $user->hasPermission('case_documents.issue')
            && $this->campusScope->sameCampus($user, $case);
    }

    private function canRead(User $user, CaseClosureDocument $document): bool
    {
        $case = $document->case;
        if ($case === null || ! $user->is_active) return false;

        if ($user->hasRole('super_admin')) {
            return $user->hasPermission('case_documents.download.all');
        }

        if ($user->hasRole('admin')) {
            return $user->hasPermission('case_documents.download.own_campus')
                && $this->campusScope->sameCampus($user, $case);
        }

        if ($user->hasRole('satgas_ppks')) {
            return $user->hasPermission('case_documents.download.assigned')
                && $this->campusScope->sameOperationalCampus($user, $case)
                && $case->isAssignedTo($user);
        }

        return $user->hasRole('reporter')
            && $user->hasPermission('case_documents.download.own')
            && $case->report?->reporter_id === $user->id;
    }
}
