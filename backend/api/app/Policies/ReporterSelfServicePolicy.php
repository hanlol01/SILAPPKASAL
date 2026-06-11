<?php

namespace App\Policies;

use App\Models\User;

class ReporterSelfServicePolicy extends BasePolicy
{
    public function access(User $user): bool
    {
        return $user->is_active && $this->allowRole($user, 'reporter');
    }
}
