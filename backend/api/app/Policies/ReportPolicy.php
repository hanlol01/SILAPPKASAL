<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use App\Support\CaseCampusScope;

class ReportPolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope) {}

    public function viewAny(User $user): bool
    {
        return ($this->allowPermission($user, 'reports.read.all')
                && $this->allowRole($user, 'super_admin'))
            || $this->canReadAllReports($user)
            || $this->allowPermission($user, 'reports.read.own');
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->hasRole('super_admin') && $user->hasPermission('reports.read.all')) {
            return true;
        }

        if ($this->canReadAllReports($user) && $this->campusScope->sameCampus($user, $report)) {
            return true;
        }

        return $this->allowPermission($user, 'reports.read.own')
            && $report->report_type !== 'anonymous'
            && $report->reporter_id === $user->id;
    }

    public function forward(User $user, Report $report): bool
    {
        return $this->allowPermission($user, 'reports.forward')
            && $this->allowRole($user, 'admin')
            && $this->campusScope->sameCampus($user, $report);
    }

    public function cancel(User $user, Report $report): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'reporter')
            && $this->allowPermission($user, 'reports.cancel.own')
            && $report->reporter_id !== null
            && $report->reporter_id === $user->id
            && ! $report->trashed();
    }

    private function canReadAllReports(User $user): bool
    {
        return $this->allowPermission($user, 'reports.read.all')
            && $this->allowRole($user, 'admin');
    }
}
