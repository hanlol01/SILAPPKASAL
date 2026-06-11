<?php

namespace App\Policies;

use App\Models\User;

class MyWorkPolicy extends BasePolicy
{
    public function view(User $user): bool
    {
        return $user->is_active
            && (
                $this->allowRole($user, 'admin', 'super_admin')
                || ($this->allowRole($user, 'satgas_ppks') && $this->allowPermission($user, 'cases.read.assigned'))
            );
    }
}
