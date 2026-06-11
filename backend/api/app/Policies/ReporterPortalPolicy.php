<?php

namespace App\Policies;

use App\Models\User;

class ReporterPortalPolicy extends BasePolicy
{
    public function access(User $user): bool
    {
        return $user->is_active && $this->allowRole($user, 'reporter');
    }
}
