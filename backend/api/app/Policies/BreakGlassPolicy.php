<?php

namespace App\Policies;

use App\Models\BreakGlassRequest;
use App\Models\User;
use App\Support\CaseCampusScope;

class BreakGlassPolicy extends BasePolicy
{
    public function __construct(private readonly CaseCampusScope $campusScope)
    {
    }

    public function request(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'satgas_ppks')
            && $this->allowPermission($user, 'privacy.request_break_glass');
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'admin')
            && $this->allowPermission($user, 'privacy.approve_break_glass');
    }

    public function view(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return ($user->is_active
                && $this->allowRole($user, 'satgas_ppks')
                && $this->allowPermission($user, 'privacy.request_break_glass')
                && (int) $user->id === (int) $breakGlassRequest->requestor_id)
            || ($this->viewAny($user) && $this->sameCampus($user, $breakGlassRequest));
    }

    public function approve(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $this->canResolve($user, $breakGlassRequest);
    }

    public function deny(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $this->canResolve($user, $breakGlassRequest);
    }

    public function revoke(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $this->canResolve($user, $breakGlassRequest);
    }

    public function reveal(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'satgas_ppks')
            && $this->allowPermission($user, 'privacy.reveal_anonymous_identity')
            && (int) $user->id === (int) $breakGlassRequest->requestor_id;
    }

    private function canResolve(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'admin')
            && $this->allowPermission($user, 'privacy.approve_break_glass')
            && $this->sameCampus($user, $breakGlassRequest);
    }

    private function sameCampus(User $user, BreakGlassRequest $breakGlassRequest): bool
    {
        $breakGlassRequest->loadMissing('report');

        return $breakGlassRequest->report !== null
            && $this->campusScope->sameCampus($user, $breakGlassRequest->report);
    }
}
