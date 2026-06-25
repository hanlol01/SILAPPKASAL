<?php

namespace App\Policies;

use App\Models\User;

class CampusMasterDataPolicy extends BasePolicy
{
    public function manage(User $user): bool
    {
        return $this->allowRole($user, 'super_admin');
    }
}
