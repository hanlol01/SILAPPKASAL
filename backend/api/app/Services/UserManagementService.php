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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters, ?User $actor = null): LengthAwarePaginator
    {
        return $this->applyCampusScope(User::query(), $actor)
            ->with(['role', 'university', 'faculty', 'studyProgram'])
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->when(! empty($filters['role']), fn (Builder $query): Builder => $query->whereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('code', $filters['role'])))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(! empty($filters['university_id']), fn (Builder $query): Builder => $query->where('university_id', $filters['university_id']))
            ->when(! empty($filters['faculty_id']), fn (Builder $query): Builder => $query->where('faculty_id', $filters['faculty_id']))
            ->when(! empty($filters['study_program_id']), fn (Builder $query): Builder => $query->where('study_program_id', $filters['study_program_id']))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function lookup(array $filters, ?User $actor = null)
    {
        return $this->applyCampusScope(User::query(), $actor)
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
        return DB::transaction(function () use ($target, $actor): User {
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

            return $target->refresh()->load(['role', 'university', 'faculty', 'studyProgram']);
        });
    }

    public function deactivate(User $target, User $actor): User
    {
        if ((int) $target->id === (int) $actor->id) {
            throw $this->unprocessable('You cannot deactivate your own account');
        }

        $this->ensureNotLastActiveSuperAdmin($target);

        return DB::transaction(function () use ($target, $actor): User {
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

            return $target->refresh()->load(['role', 'university', 'faculty', 'studyProgram']);
        });
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

        return DB::transaction(function () use ($target, $actor, $role): User {
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

            return $target->refresh()->load(['role', 'university', 'faculty', 'studyProgram']);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array{user: User, temporary_password: string}
     */
    public function createReporter(array $data, User $actor): array
    {
        $this->ensureActorCanManageUniversity($actor, (int) $data['university_id']);
        $this->ensureNoReporterDuplicate((string) $data['email'], (string) $data['nim'], (int) $data['university_id']);

        return DB::transaction(function () use ($data, $actor): array {
            $role = Role::query()->where('code', 'reporter')->firstOrFail();

            $user = User::query()->create([
                'role_id' => $role->id,
                'university_id' => $data['university_id'],
                'faculty_id' => $data['faculty_id'] ?? null,
                'study_program_id' => $data['study_program_id'],
                'name' => trim((string) $data['name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'nim' => trim((string) $data['nim']),
                'phone_number' => trim((string) $data['phone_number']),
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $this->auditLogService->record(
                action: AuditAction::UserReporterCreated,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $user,
                metadata: ['role_code' => 'reporter']
            );

            return [
                'user' => $user->refresh()->load(['role', 'university', 'faculty', 'studyProgram']),
                'temporary_password' => (string) $data['password'],
            ];
        });
    }

    /**
     * @return array{user: User, temporary_password: string}
     */
    public function resetPassword(User $target, User $actor): array
    {
        return DB::transaction(function () use ($target, $actor): array {
            $temporaryPassword = Str::password(14);

            $target->forceFill([
                'password' => Hash::make($temporaryPassword),
            ])->save();
            $target->tokens()->delete();

            $this->auditLogService->record(
                action: AuditAction::UserPasswordReset,
                category: AuditCategory::System,
                severity: AuditSeverity::Warning,
                actor: $actor,
                subject: $target,
                metadata: [
                    'temporary_password_generated' => true,
                ]
            );

            return [
                'user' => $target->refresh()->load(['role', 'university', 'faculty', 'studyProgram']),
                'temporary_password' => $temporaryPassword,
            ];
        });
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

    private function applyCampusScope(Builder $query, ?User $actor): Builder
    {
        if (! $actor || $actor->hasRole('super_admin')) {
            return $query;
        }

        if ($actor->hasRole('admin') && $actor->university_id !== null) {
            return $query->where('university_id', $actor->university_id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function ensureActorCanManageUniversity(User $actor, int $universityId): void
    {
        if ($actor->hasRole('super_admin')) {
            return;
        }

        if ($actor->hasRole('admin') && $actor->university_id !== null && (int) $actor->university_id === $universityId) {
            return;
        }

        throw $this->unprocessable('You cannot manage users for this university');
    }

    private function ensureNoReporterDuplicate(string $email, string $nim, int $universityId): void
    {
        $emailExists = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->exists();

        $nimExists = User::query()
            ->where('university_id', $universityId)
            ->whereRaw('LOWER(nim) = ?', [mb_strtolower(trim($nim))])
            ->exists();

        if ($emailExists || $nimExists) {
            throw $this->unprocessable('An account already exists for this email or NIM in the selected university');
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
