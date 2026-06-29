<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ReporterRegistrationStatus;
use App\Models\ReporterRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ReporterRegistrationService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data): ReporterRegistration
    {
        $email = $this->normalizeEmail($data['email']);
        $nim = $this->normalizeNim($data['nim']);
        $universityId = (int) $data['university_id'];

        $this->ensureNoActiveUserDuplicate($email, $nim, $universityId);
        $this->ensureNoPendingRegistrationDuplicate($email, $nim, $universityId);

        return DB::transaction(function () use ($data, $email, $nim, $universityId): ReporterRegistration {
            $registration = ReporterRegistration::query()->create([
                'registration_number' => $this->generateRegistrationNumber(),
                'university_id' => $universityId,
                'faculty_id' => $data['faculty_id'] ?? null,
                'study_program_id' => $data['study_program_id'],
                'name' => trim((string) $data['name']),
                'email' => $email,
                'nim' => $nim,
                'phone_number' => trim((string) $data['phone_number']),
                'password_hash' => Hash::make($data['password']),
                'status' => ReporterRegistrationStatus::Pending->value,
            ]);

            $this->auditLogService->record(
                action: AuditAction::ReporterRegistrationSubmitted,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                subject: $registration,
                metadata: [
                    'registration_number' => $registration->registration_number,
                    'status' => ReporterRegistrationStatus::Pending->value,
                ]
            );

            return $registration;
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, ReporterRegistration>
     */
    public function list(array $filters = [], ?User $actor = null): LengthAwarePaginator
    {
        return $this->applyRegistrationScope(ReporterRegistration::query(), $actor)
            ->with(['university', 'faculty', 'studyProgram'])
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applyRegistrationSearch($query, (string) $filters['search']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['university_id']), fn ($query) => $query->where('university_id', $filters['university_id']))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function approve(ReporterRegistration $registration, User $actor): ReporterRegistration
    {
        return DB::transaction(function () use ($registration, $actor): ReporterRegistration {
            $registration = ReporterRegistration::query()
                ->with(['university', 'faculty', 'studyProgram'])
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($registration);
            $this->ensureNoActiveUserDuplicate($registration->email, $registration->nim, $registration->university_id);

            if (! $registration->password_hash) {
                throw $this->unprocessable('Registration password is no longer available for approval');
            }

            $role = Role::query()->where('code', 'reporter')->firstOrFail();

            $user = User::query()->create([
                'role_id' => $role->id,
                'university_id' => $registration->university_id,
                'faculty_id' => $registration->faculty_id,
                'study_program_id' => $registration->study_program_id,
                'name' => $registration->name,
                'email' => $registration->email,
                'nim' => $registration->nim,
                'phone_number' => $registration->phone_number,
                'password' => $registration->password_hash,
                'is_active' => true,
            ]);

            $registration->forceFill([
                'status' => ReporterRegistrationStatus::Approved,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'approved_user_id' => $user->id,
                'password_hash' => null,
                'rejection_reason' => null,
            ])->save();

            $this->auditLogService->record(
                action: AuditAction::ReporterRegistrationApproved,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $registration,
                metadata: [
                    'registration_number' => $registration->registration_number,
                    'approved_user_id' => $user->id,
                    'status' => ReporterRegistrationStatus::Approved->value,
                ],
                beforeChanges: ['status' => ReporterRegistrationStatus::Pending->value],
                afterChanges: ['status' => ReporterRegistrationStatus::Approved->value]
            );

            return $registration->refresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function correct(array $data): ReporterRegistration
    {
        $email = $this->normalizeEmail($data['email']);

        $registration = ReporterRegistration::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', ReporterRegistrationStatus::Rejected->value)
            ->whereNotNull('password_hash')
            ->first();

        if (! $registration || ! Hash::check((string) $data['password'], (string) $registration->password_hash)) {
            throw $this->unprocessable('Invalid registration credentials');
        }

        $newNim = $this->normalizeNim($data['nim']);
        $universityId = (int) $data['university_id'];
        $nimChanged = $newNim !== $registration->nim;
        $previousNim = $registration->nim;

        $this->ensureNoActiveUserDuplicate($registration->email, $newNim, $universityId);
        $this->ensureNoPendingRegistrationDuplicate($registration->email, $newNim, $universityId, $registration->id);

        return DB::transaction(function () use ($registration, $data, $newNim, $universityId, $nimChanged, $previousNim): ReporterRegistration {
            $updates = [
                'name' => trim((string) $data['name']),
                'nim' => $newNim,
                'phone_number' => trim((string) $data['phone_number']),
                'university_id' => $universityId,
                'faculty_id' => $data['faculty_id'] ?? null,
                'study_program_id' => $data['study_program_id'],
                'status' => ReporterRegistrationStatus::Pending,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ];

            if (! empty($data['new_password'])) {
                $updates['password_hash'] = Hash::make((string) $data['new_password']);
            }

            $before = [
                'status' => ReporterRegistrationStatus::Rejected->value,
                'nim' => $previousNim,
                'university_id' => $registration->university_id,
                'faculty_id' => $registration->faculty_id,
                'study_program_id' => $registration->study_program_id,
            ];

            $registration->forceFill($updates)->save();

            $this->auditLogService->record(
                action: AuditAction::ReporterRegistrationCorrected,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                subject: $registration,
                metadata: [
                    'registration_number' => $registration->registration_number,
                    'status' => ReporterRegistrationStatus::Pending->value,
                    'nim_changed' => $nimChanged,
                    'previous_nim' => $nimChanged ? $previousNim : null,
                ],
                beforeChanges: $before,
                afterChanges: [
                    'status' => ReporterRegistrationStatus::Pending->value,
                    'nim' => $newNim,
                    'university_id' => $universityId,
                    'faculty_id' => $data['faculty_id'] ?? null,
                    'study_program_id' => $data['study_program_id'],
                ]
            );

            return $registration->refresh()->load(['university', 'faculty', 'studyProgram']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function reject(ReporterRegistration $registration, User $actor, array $data): ReporterRegistration
    {
        return DB::transaction(function () use ($registration, $actor, $data): ReporterRegistration {
            $registration = ReporterRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($registration);

            $registration->forceFill([
                'status' => ReporterRegistrationStatus::Rejected,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ])->save();

            $this->auditLogService->record(
                action: AuditAction::ReporterRegistrationRejected,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $registration,
                metadata: [
                    'registration_number' => $registration->registration_number,
                    'has_rejection_reason' => true,
                    'status' => ReporterRegistrationStatus::Rejected->value,
                ],
                beforeChanges: ['status' => ReporterRegistrationStatus::Pending->value],
                afterChanges: ['status' => ReporterRegistrationStatus::Rejected->value]
            );

            return $registration->refresh();
        });
    }

    private function ensurePending(ReporterRegistration $registration): void
    {
        if ($registration->status !== ReporterRegistrationStatus::Pending) {
            throw $this->unprocessable('Only pending reporter registrations can be reviewed');
        }
    }

    private function ensureNoActiveUserDuplicate(string $email, string $nim, ?int $universityId = null): void
    {
        $emailExists = User::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->exists();

        $nimExists = User::query()
            ->where('is_active', true)
            ->where('university_id', $universityId)
            ->whereRaw('LOWER(nim) = ?', [mb_strtolower($this->normalizeNim($nim))])
            ->exists();

        if ($emailExists || $nimExists) {
            throw $this->unprocessable('An active account already exists for this email or NIM in the selected university');
        }
    }

    private function ensureNoPendingRegistrationDuplicate(string $email, string $nim, ?int $universityId = null, ?int $excludeId = null): void
    {
        $emailQuery = ReporterRegistration::query()
            ->where('status', ReporterRegistrationStatus::Pending->value)
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->when($excludeId, fn (Builder $query): Builder => $query->whereKeyNot($excludeId));

        $nimQuery = ReporterRegistration::query()
            ->where('status', ReporterRegistrationStatus::Pending->value)
            ->where('university_id', $universityId)
            ->whereRaw('LOWER(nim) = ?', [mb_strtolower($this->normalizeNim($nim))])
            ->when($excludeId, fn (Builder $query): Builder => $query->whereKeyNot($excludeId));

        if ($emailQuery->exists() || $nimQuery->exists()) {
            throw $this->unprocessable('A pending registration already exists for this email or NIM in the selected university');
        }
    }

    private function applyRegistrationScope(Builder $query, ?User $actor): Builder
    {
        if (! $actor || $actor->hasRole('super_admin')) {
            return $query;
        }

        if ($actor->hasRole('admin') && $actor->university_id !== null) {
            return $query->where('university_id', $actor->university_id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function applyRegistrationSearch(Builder $query, string $search): Builder
    {
        $needle = mb_strtolower(trim($search));

        return $query->where(function (Builder $query) use ($needle): void {
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(registration_number) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(nim) LIKE ?', ["%{$needle}%"]);
        });
    }

    private function generateRegistrationNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "REG-{$date}-";

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $nextNumber = ReporterRegistration::query()
                ->where('registration_number', 'like', "{$prefix}%")
                ->count() + 1 + $attempt;

            $candidate = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

            if (! ReporterRegistration::query()->where('registration_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw $this->unprocessable('Unable to generate reporter registration number');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeNim(string $nim): string
    {
        return trim($nim);
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
