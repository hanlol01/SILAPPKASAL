<?php

namespace App\Policies;

use App\Models\ReporterRegistration;
use App\Models\User;

class ReporterRegistrationPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canReview($user);
    }

    public function view(User $user, ReporterRegistration $registration): bool
    {
        return $this->canReview($user) && $this->sameCampusOrSuperAdmin($user, $registration);
    }

    public function approve(User $user, ReporterRegistration $registration): bool
    {
        return $this->canReview($user) && $this->sameCampusOrSuperAdmin($user, $registration);
    }

    public function reject(User $user, ReporterRegistration $registration): bool
    {
        return $this->canReview($user) && $this->sameCampusOrSuperAdmin($user, $registration);
    }

    private function canReview(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'users.create')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    private function sameCampusOrSuperAdmin(User $user, ReporterRegistration $registration): bool
    {
        if ($this->allowRole($user, 'super_admin')) {
            return true;
        }

        return $this->allowRole($user, 'admin')
            && $user->university_id !== null
            && (int) $user->university_id === (int) $registration->university_id;
    }
}
