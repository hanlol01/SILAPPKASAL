<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogVisibilityScope
{
    /** @return Builder<AuditLog> */
    public function query(User $user): Builder
    {
        return $this->apply(AuditLog::query(), $user);
    }

    /**
     * @param Builder<AuditLog> $query
     * @return Builder<AuditLog>
     */
    public function apply(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if ($user->hasRole('admin')) {
            return $query
                ->where('category', '!=', AuditCategory::Privacy->value)
                ->where('is_elevated_access', false);
        }

        return $query->whereRaw('1 = 0');
    }

    public function allows(User $user, AuditLog $auditLog): bool
    {
        return $this->query($user)->whereKey($auditLog->getKey())->exists();
    }
}
