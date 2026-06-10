<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    protected function allowPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    protected function allowRole(User $user, string ...$roles): bool
    {
        return in_array($user->role?->code, $roles, true);
    }
}
