<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Cache::clear();
    }

    public function test_login_succeeds_with_normalized_email_identifier(): void
    {
        $user = $this->makeUser([
            'email' => 'admin@university.ac.id',
            'password' => 'SecurePass123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '  ADMIN@UNIVERSITY.AC.ID  ',
            'password' => 'SecurePass123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 86400)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role.code', 'reporter')
            ->assertJsonFragment(['reports.create']);
    }

    public function test_login_succeeds_with_nim_identifier(): void
    {
        $user = $this->makeUser([
            'nim' => '2023123456',
            'password' => 'SecurePass123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => ' 2023123456 ',
            'password' => 'SecurePass123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_succeeds_with_nip_identifier(): void
    {
        $user = $this->makeUser([
            'nip' => '198507202015041001',
            'password' => 'SecurePass123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '198507202015041001',
            'password' => 'SecurePass123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->makeUser(['password' => 'SecurePass123']);

        $this->withHeader('Accept-Language', 'en')->postJson('/api/v1/auth/login', [
            'identifier' => 'reporter@university.ac.id',
            'password' => 'WrongPass123',
        ])->assertUnauthorized()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'invalid_credentials')
            ->assertJsonPath('message', 'The email, student ID, employee ID, or password is incorrect.');
    }

    public function test_inactive_user_login_returns_forbidden(): void
    {
        $this->makeUser([
            'email' => 'inactive@university.ac.id',
            'password' => 'SecurePass123',
            'is_active' => false,
        ]);

        $this->withHeader('Accept-Language', 'id')->postJson('/api/v1/auth/login', [
            'identifier' => 'inactive@university.ac.id',
            'password' => 'SecurePass123',
        ])->assertForbidden()
            ->assertHeader('Content-Language', 'id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'account_inactive');
    }

    public function test_login_validation_errors_return_422(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => '',
            'password' => '',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['identifier', 'password']]);
    }

    public function test_login_rate_limit_returns_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => 'missing@university.ac.id',
                'password' => 'WrongPass123',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'missing@university.ac.id',
            'password' => 'WrongPass123',
        ])->assertTooManyRequests()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'too_many_requests');
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role.code', 'reporter')
            ->assertJsonFragment(['reports.create']);
    }

    public function test_me_requires_token(): void
    {
        $this->withHeader('Accept-Language', '')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Language', 'id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Autentikasi diperlukan.')
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_unsupported_api_locale_falls_back_to_indonesian(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Language', 'id')
            ->assertJsonPath('message', 'Autentikasi diperlukan.')
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_logout_deletes_only_current_token(): void
    {
        $user = $this->makeUser();
        $currentToken = $user->createToken('web-login', ['*'], now()->addDay());
        $otherToken = $user->createToken('web-login', ['*'], now()->addDay());

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        $role = Role::query()->where('code', $overrides['role'] ?? 'reporter')->firstOrFail();
        unset($overrides['role']);

        return User::query()->create(array_merge([
            'role_id' => $role->id,
            'name' => 'Reporter User',
            'email' => 'reporter@university.ac.id',
            'nim' => null,
            'nip' => null,
            'phone_number' => '6281234567890',
            'password' => 'SecurePass123',
            'is_active' => true,
        ], $overrides));
    }
}
