<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canReadAllReports($user)
            || $this->allowPermission($user, 'reports.read.own');
    }

    public function view(User $user, Report $report): bool
    {
        if ($this->canReadAllReports($user)) {
            return true;
        }

        return $this->allowPermission($user, 'reports.read.own')
            && $report->report_type !== 'anonymous'
            && $report->reporter_id === $user->id;
    }

    private function canReadAllReports(User $user): bool
    {
        return $this->allowPermission($user, 'reports.read.all')
            && $this->allowRole($user, 'admin', 'super_admin');
    }
}
