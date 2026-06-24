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

        $this->ensureNoActiveUserDuplicate($email, $nim);
        $this->ensureNoPendingRegistrationDuplicate($email, $nim);

        return DB::transaction(function () use ($data, $email, $nim): ReporterRegistration {
            $registration = ReporterRegistration::query()->create([
                'registration_number' => $this->generateRegistrationNumber(),
                'name' => trim((string) $data['name']),
                'email' => $email,
                'nim' => $nim,
                'phone_number' => isset($data['phone_number']) ? trim((string) $data['phone_number']) : null,
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
    public function list(array $filters = []): LengthAwarePaginator
    {
        return ReporterRegistration::query()
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function approve(ReporterRegistration $registration, User $actor): ReporterRegistration
    {
        return DB::transaction(function () use ($registration, $actor): ReporterRegistration {
            $registration = ReporterRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($registration);
            $this->ensureNoActiveUserDuplicate($registration->email, $registration->nim);

            if (! $registration->password_hash) {
                throw $this->unprocessable('Registration password is no longer available for approval');
            }

            $role = Role::query()->where('code', 'reporter')->firstOrFail();

            $user = User::query()->create([
                'role_id' => $role->id,
                'university_id' => $registration->university_id,
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

    private function ensureNoActiveUserDuplicate(string $email, string $nim): void
    {
        $exists = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($email, $nim): void {
                $query->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
                    ->orWhereRaw('LOWER(nim) = ?', [mb_strtolower($this->normalizeNim($nim))]);
            })
            ->exists();

        if ($exists) {
            throw $this->unprocessable('An active account already exists for this email or NIM');
        }
    }

    private function ensureNoPendingRegistrationDuplicate(string $email, string $nim): void
    {
        $exists = ReporterRegistration::query()
            ->where('status', ReporterRegistrationStatus::Pending->value)
            ->where(function ($query) use ($email, $nim): void {
                $query->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
                    ->orWhereRaw('LOWER(nim) = ?', [mb_strtolower($this->normalizeNim($nim))]);
            })
            ->exists();

        if ($exists) {
            throw $this->unprocessable('A pending registration already exists for this email or NIM');
        }
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
