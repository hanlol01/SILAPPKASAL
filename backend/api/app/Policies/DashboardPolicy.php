<?php

namespace App\Policies;

use App\Models\User;

class DashboardPolicy extends BasePolicy
{
    public function view(User $user): bool
    {
        return $this->allowPermission($user, 'statistics.view')
            && $this->allowRole($user, 'admin', 'super_admin', 'satgas_ppks');
    }
}
