<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canReadUsers($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->canReadUsers($user);
    }

    public function lookup(User $user): bool
    {
        return $this->canReadUsers($user);
    }

    public function activate(User $user, User $target): bool
    {
        return $this->canManageActivation($user);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $this->canManageActivation($user);
    }

    public function assignRole(User $user, User $target): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'users.assign_role');
    }

    private function canReadUsers(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'users.read')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    private function canManageActivation(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'users.deactivate')
            && $this->allowRole($user, 'admin', 'super_admin');
    }
}
