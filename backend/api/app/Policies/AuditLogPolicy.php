<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogVisibilityScope;

class AuditLogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowPermission($user, 'system.audit_log.view')
            && $this->allowRole($user, 'admin', 'super_admin');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user)
            && app(AuditLogVisibilityScope::class)->allows($user, $auditLog);
    }

    public function oversight(User $user): bool
    {
        return $user->hasRole('super_admin')
            && $user->hasPermission('system.audit_log.oversight');
    }

    public function export(User $user): bool
    {
        return $user->hasRole('super_admin')
            && $user->hasPermission('system.audit_log.export');
    }
}
