<?php

namespace App\Policies;

use App\Models\BreakGlassRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BreakGlassPolicy extends BasePolicy
{
    public function request(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'admin', 'super_admin')
            && $this->allowPermission($user, 'privacy.request_break_glass');
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'privacy.approve_break_glass');
    }

    public function view(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return (int) $user->id === (int) $breakGlassRequest->requestor_id
            || $this->viewAny($user);
    }

    public function approve(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $this->canResolve($user, $breakGlassRequest);
    }

    public function deny(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $this->canResolve($user, $breakGlassRequest);
    }

    public function reveal(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $user->is_active
            && $breakGlassRequest->isViewable()
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'privacy.reveal_anonymous_identity')
            && (
                (int) $user->id === (int) $breakGlassRequest->requestor_id
                || (int) $user->id === (int) $breakGlassRequest->approver_id
            );
    }

    private function canResolve(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $user->is_active
            && $breakGlassRequest->isPending()
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'privacy.approve_break_glass')
            && (
                (int) $user->id !== (int) $breakGlassRequest->requestor_id
                || $this->isSingleActiveSuperAdmin()
            );
    }

    private function isSingleActiveSuperAdmin(): bool
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'super_admin'))
            ->count() <= 1;
    }
}
