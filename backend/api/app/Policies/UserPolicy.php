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
        return $this->canReadUsers($user) && $this->sameCampusOrSuperAdmin($user, $target);
    }

    public function lookup(User $user): bool
    {
        return $this->canReadUsers($user);
    }

    public function createReporter(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'users.create')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    public function activate(User $user, User $target): bool
    {
        return $this->canManageActivation($user) && $this->sameCampusOrSuperAdmin($user, $target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $this->canManageActivation($user) && $this->sameCampusOrSuperAdmin($user, $target);
    }

    public function assignRole(User $user, User $target): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'users.assign_role');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->canManageActivation($user) && $this->sameCampusOrSuperAdmin($user, $target);
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

    private function sameCampusOrSuperAdmin(User $user, User $target): bool
    {
        if ($this->allowRole($user, 'super_admin')) {
            return true;
        }

        return $this->allowRole($user, 'admin')
            && $user->university_id !== null
            && (int) $user->university_id === (int) $target->university_id;
    }
}
