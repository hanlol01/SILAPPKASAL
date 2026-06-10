<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(string $identifier, string $password): array
    {
        $user = $this->findUserByIdentifier($this->normalizeIdentifier($identifier));

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Email/NIM/NIP atau password salah',
                'errors' => null,
            ], 401));
        }

        if (! $user->is_active) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi admin.',
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
}
