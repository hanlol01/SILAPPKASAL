<?php

namespace App\Services;

use App\Enums\ReporterRegistrationStatus;
use App\Models\ReporterRegistration;
use App\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(string $identifier, string $password): array
    {
        $user = $this->findUserByIdentifier($this->normalizeIdentifier($identifier));

        if (! $user || ! Hash::check($password, $user->password)) {
            $registrationResult = $this->registrationLogin($this->normalizeIdentifier($identifier), $password);

            if ($registrationResult) {
                return $registrationResult;
            }

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.invalid_credentials'),
                'error_code' => ApiErrorCode::InvalidCredentials,
                'errors' => null,
            ], 401));
        }

        if (! $user->is_active) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.account_inactive'),
                'error_code' => ApiErrorCode::AccountInactive,
                'errors' => null,
            ], 403));
        }

        $user->load('role.permissions');

        $expirationMinutes = $this->tokenExpirationMinutes();
        $token = $user->createToken(
            'web-login',
            ['*'],
            now()->addMinutes($expirationMinutes)
        );

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expirationMinutes * 60,
            'user' => $user,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        return str_contains($identifier, '@')
            ? mb_strtolower($identifier)
            : $identifier;
    }

    public function tokenExpirationMinutes(): int
    {
        return (int) config('sanctum.expiration', 1440);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->with('role.permissions')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])
            ->orWhereRaw('LOWER(nim) = ?', [mb_strtolower($identifier)])
            ->orWhereRaw('LOWER(nip) = ?', [mb_strtolower($identifier)])
            ->first();
    }

    private function registrationLogin(string $identifier, string $password): ?array
    {
        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $registration = ReporterRegistration::query()
            ->with(['university', 'faculty', 'studyProgram'])
            ->whereIn('status', [
                ReporterRegistrationStatus::Pending->value,
                ReporterRegistrationStatus::Rejected->value,
            ])
            ->whereNotNull('password_hash')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $registration || ! Hash::check($password, (string) $registration->password_hash)) {
            return null;
        }

        return [
            'type' => 'registration',
            'registration' => $registration,
        ];
    }
}
