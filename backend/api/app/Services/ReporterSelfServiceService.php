<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ReporterRegistrationStatus;
use App\Models\ReporterRegistration;
use App\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ReporterSelfServiceService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $nameChanged = array_key_exists('name', $data) && $data['name'] !== $user->name;
            $phoneChanged = array_key_exists('phone_number', $data) && $data['phone_number'] !== $user->phone_number;

            $user->forceFill(collect($data)->only(['name', 'phone_number'])->all())->save();

            $this->auditLogService->record(
                action: AuditAction::ReporterSelfServiceProfileUpdated,
                category: AuditCategory::System,
                severity: AuditSeverity::Info,
                actor: $user,
                subject: $user,
                afterChanges: [
                    'name_changed' => $nameChanged,
                    'phone_changed' => $phoneChanged,
                ]
            );

            return $user->refresh()->load('role');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function changePassword(User $user, array $data): void
    {
        if (! Hash::check((string) $data['current_password'], $user->password)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.current_password_incorrect'),
                'error_code' => ApiErrorCode::CurrentPasswordIncorrect,
                'errors' => [
                    'current_password' => [__('api.errors.current_password_incorrect')],
                ],
            ], 422));
        }

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data['password'],
            ])->save();

            $currentTokenId = $user->currentAccessToken()?->id;
            $revokedTokenCount = $currentTokenId
                ? $user->tokens()->whereKeyNot($currentTokenId)->delete()
                : $user->tokens()->delete();

            $this->auditLogService->record(
                action: AuditAction::ReporterSelfServicePasswordChanged,
                category: AuditCategory::Auth,
                severity: AuditSeverity::Info,
                actor: $user,
                subject: $user,
                metadata: [
                    'revoked_other_tokens' => $revokedTokenCount,
                ]
            );
        });
    }

    /**
     * @return array{user: User, registration_number: ?string}
     */
    public function accountStatus(User $user): array
    {
        $registrationNumber = ReporterRegistration::query()
            ->where('approved_user_id', $user->id)
            ->where('status', ReporterRegistrationStatus::Approved->value)
            ->latest('created_at')
            ->latest('id')
            ->value('registration_number');

        return [
            'user' => $user->load('role'),
            'registration_number' => $registrationNumber,
        ];
    }
}
