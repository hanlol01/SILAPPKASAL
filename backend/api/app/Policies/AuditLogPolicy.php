<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowPermission($user, 'system.audit_log.view')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }
}
