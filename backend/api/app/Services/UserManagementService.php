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

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function listStaff(array $filters, User $actor): LengthAwarePaginator
    {
        return $this->applyCampusScope(User::query(), $actor)
            ->with(['role', 'university'])
            ->whereHas('role', fn (Builder $query): Builder => $query->whereIn('code', ['admin', 'satgas_ppks']))
            ->when($actor->hasRole('admin'), fn (Builder $query): Builder => $query->whereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('code', 'satgas_ppks')))
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(! empty($filters['university_id']), fn (Builder $query): Builder => $query->where('university_id', $filters['university_id']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
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
                'phone_number' => (string) $data['phone_number'],
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

    public function createStaff(array $data, User $actor): User
    {
        $this->ensureActorCanManageStaffRole($actor, (string) $data['role_code'], (int) $data['university_id']);

        return DB::transaction(function () use ($data, $actor): User {
            $role = Role::query()->where('code', $data['role_code'])->where('is_active', true)->firstOrFail();
            $user = User::query()->create([
                'role_id' => $role->id,
                'university_id' => $data['university_id'],
                'name' => trim((string) $data['name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'nip' => trim((string) $data['nip']),
                'phone_number' => $data['phone_number'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $this->auditLogService->record(
                action: AuditAction::UserStaffCreated,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $user,
                metadata: ['role_code' => $role->code]
            );

            return $user->refresh()->load(['role', 'university']);
        });
    }

    public function updateStaff(User $target, array $data, User $actor): User
    {
        $this->ensureActorCanManageExistingStaff($actor, $target);

        return DB::transaction(function () use ($target, $data, $actor): User {
            $before = [
                'name' => $target->name,
                'email' => $target->email,
                'nip' => $target->nip,
                'phone_number' => $target->phone_number,
            ];
            $target->forceFill([
                'name' => trim((string) $data['name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'nip' => trim((string) $data['nip']),
                'phone_number' => $data['phone_number'] ?? null,
            ])->save();

            $this->auditLogService->record(
                action: AuditAction::UserStaffUpdated,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $target,
                beforeChanges: [
                    'name_changed' => $before['name'] !== $target->name,
                    'email_changed' => $before['email'] !== $target->email,
                    'nip_changed' => $before['nip'] !== $target->nip,
                    'phone_changed' => $before['phone_number'] !== $target->phone_number,
                ],
                afterChanges: [
                    'name_changed' => $before['name'] !== $target->name,
                    'email_changed' => $before['email'] !== $target->email,
                    'nip_changed' => $before['nip'] !== $target->nip,
                    'phone_changed' => $before['phone_number'] !== $target->phone_number,
                ]
            );

            return $target->refresh()->load(['role', 'university']);
        });
    }

    public function resetStaffPassword(User $target, array $data, User $actor): User
    {
        $this->ensureActorCanManageExistingStaff($actor, $target);

        return DB::transaction(function () use ($target, $data, $actor): User {
            $target->forceFill(['password' => Hash::make((string) $data['password'])])->save();
            $target->tokens()->delete();

            $this->auditLogService->record(
                action: AuditAction::UserPasswordReset,
                category: AuditCategory::System,
                severity: AuditSeverity::Warning,
                actor: $actor,
                subject: $target,
                metadata: ['password_set_by_admin' => true]
            );

            return $target->refresh()->load(['role', 'university']);
        });
    }

    public function activateStaff(User $target, User $actor): User
    {
        $this->ensureActorCanManageExistingStaff($actor, $target);

        return $this->activate($target, $actor);
    }

    public function deactivateStaff(User $target, User $actor): User
    {
        $this->ensureActorCanManageExistingStaff($actor, $target);

        return $this->deactivate($target, $actor);
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

    private function ensureActorCanManageStaffRole(User $actor, string $roleCode, int $universityId): void
    {
        if ($actor->hasRole('super_admin') && in_array($roleCode, ['admin', 'satgas_ppks'], true)) {
            return;
        }

        if ($actor->hasRole('admin') && $roleCode === 'satgas_ppks' && $actor->university_id !== null && (int) $actor->university_id === $universityId) {
            return;
        }

        throw $this->unprocessable('You cannot manage this staff account');
    }

    private function ensureActorCanManageExistingStaff(User $actor, User $target): void
    {
        $target->loadMissing('role');

        if ((int) $target->id === (int) $actor->id) {
            throw $this->unprocessable('You cannot manage your own staff account');
        }

        $this->ensureActorCanManageStaffRole($actor, (string) $target->role?->code, (int) $target->university_id);
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
