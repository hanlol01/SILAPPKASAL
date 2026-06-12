<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->with('role')
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->when(! empty($filters['role']), fn (Builder $query): Builder => $query->whereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('code', $filters['role'])))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function lookup(array $filters)
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', $filters['role']))
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->orderBy('name')
            ->limit((int) ($filters['limit'] ?? 25))
            ->get();
    }

    public function activate(User $target, User $actor): User
    {
        $before = ['is_active' => (bool) $target->is_active];

        $target->forceFill(['is_active' => true])->save();

        $this->auditLogService->record(
            action: AuditAction::UserActivated,
            category: AuditCategory::System,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $target,
            beforeChanges: $before,
            afterChanges: ['is_active' => true]
        );

        return $target->refresh()->load('role');
    }

    public function deactivate(User $target, User $actor): User
    {
        if ((int) $target->id === (int) $actor->id) {
            throw $this->unprocessable('You cannot deactivate your own account');
        }

        $this->ensureNotLastActiveSuperAdmin($target);

        $before = ['is_active' => (bool) $target->is_active];

        $target->forceFill(['is_active' => false])->save();
        $target->tokens()->delete();

        $this->auditLogService->record(
            action: AuditAction::UserDeactivated,
            category: AuditCategory::System,
            severity: AuditSeverity::Warning,
            actor: $actor,
            subject: $target,
            beforeChanges: $before,
            afterChanges: ['is_active' => false]
        );

        return $target->refresh()->load('role');
    }

    public function assignRole(User $target, User $actor, string $roleCode): User
    {
        if ((int) $target->id === (int) $actor->id) {
            throw $this->unprocessable('You cannot change your own role');
        }

        $this->ensureNotLastActiveSuperAdmin($target);

        $role = Role::query()
            ->where('code', $roleCode)
            ->where('is_active', true)
            ->firstOrFail();

        $beforeRoleCode = $target->role?->code;

        $target->forceFill(['role_id' => $role->id])->save();
        $target->tokens()->delete();

        $this->auditLogService->record(
            action: AuditAction::UserRoleChanged,
            category: AuditCategory::System,
            severity: AuditSeverity::Warning,
            actor: $actor,
            subject: $target,
            beforeChanges: ['role_code' => $beforeRoleCode],
            afterChanges: ['role_code' => $role->code]
        );

        return $target->refresh()->load('role');
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $needle = mb_strtolower(trim($search));

        return $query->where(function (Builder $query) use ($needle): void {
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(nim) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(nip) LIKE ?', ["%{$needle}%"]);
        });
    }

    private function ensureNotLastActiveSuperAdmin(User $target): void
    {
        $target->loadMissing('role');

        if (! $target->is_active || $target->role?->code !== 'super_admin') {
            return;
        }

        $activeSuperAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'super_admin'))
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw $this->unprocessable('The last active Super Admin cannot be modified');
        }
    }

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }
}
