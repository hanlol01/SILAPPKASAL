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
        return $this->canReview($user);
    }

    public function approve(User $user, ReporterRegistration $registration): bool
    {
        return $this->canReview($user);
    }

    public function reject(User $user, ReporterRegistration $registration): bool
    {
        return $this->canReview($user);
    }

    private function canReview(User $user): bool
    {
        return $user->is_active
            && $this->allowPermission($user, 'users.create')
            && $this->allowRole($user, 'admin', 'super_admin');
    }
}
