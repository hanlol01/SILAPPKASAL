<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ReporterRegistrationStatus;
use App\Models\ReporterRegistration;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporterSelfServiceFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_reporter_can_view_own_profile(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test', '230001');

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $reporter->id)
            ->assertJsonPath('data.email', 'reporter@example.test')
            ->assertJsonPath('data.nim', '230001')
            ->assertJsonPath('data.role.code', 'reporter')
            ->assertJsonMissingPath('data.permissions')
            ->assertJsonMissingPath('data.is_active');
    }

    public function test_reporter_can_update_only_name_and_phone_number(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test', '230001');

        Sanctum::actingAs($reporter, ['*']);

        $this->patchJson('/api/v1/me/profile', [
            'name' => 'Nama Reporter Baru',
            'phone_number' => '+628129990001',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nama Reporter Baru')
            ->assertJsonPath('data.phone_number', '+628129990001')
            ->assertJsonMissingPath('data.permissions');

        $this->assertDatabaseHas('users', [
            'id' => $reporter->id,
            'name' => 'Nama Reporter Baru',
            'phone_number' => '+628129990001',
            'email' => 'reporter@example.test',
            'nim' => '230001',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterSelfServiceProfileUpdated->value,
            'actor_id' => $reporter->id,
        ]);
    }

    public function test_profile_phone_is_nullable_and_rejects_invalid_values(): void
    {
        $reporter = $this->makeUser('reporter', 'phone-profile@example.test', 'DEMO-PROFILE-001');
        Sanctum::actingAs($reporter, ['*']);

        $this->patchJson('/api/v1/me/profile', ['phone_number' => '0812 3456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);

        $this->patchJson('/api/v1/me/profile', ['phone_number' => null])
            ->assertOk()
            ->assertJsonPath('data.phone_number', null);
    }

    public function test_reporter_cannot_update_identity_or_privilege_fields(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test', '230001');
        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();

        Sanctum::actingAs($reporter, ['*']);

        $this->patchJson('/api/v1/me/profile', [
            'name' => 'Nama Tetap Ditolak',
            'email' => 'new@example.test',
            'nim' => '999999',
            'nip' => 'NIP-001',
            'role_id' => $adminRole->id,
            'role' => 'admin',
            'permissions' => ['users.create'],
            'is_active' => false,
            'reviewed_by' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'nim', 'nip', 'role_id', 'role', 'permissions', 'is_active', 'reviewed_by']);

        $reporter->refresh();

        $this->assertSame('reporter@example.test', $reporter->email);
        $this->assertSame('230001', $reporter->nim);
        $this->assertSame('reporter', $reporter->role->code);
        $this->assertTrue($reporter->is_active);
    }

    public function test_reporter_can_view_safe_account_status_metadata(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test', '230001');
        ReporterRegistration::query()->create([
            'registration_number' => 'REG-20260611-0001',
            'name' => $reporter->name,
            'email' => $reporter->email,
            'nim' => $reporter->nim,
            'password_hash' => null,
            'status' => ReporterRegistrationStatus::Approved->value,
            'approved_user_id' => $reporter->id,
            'reviewed_by' => $this->makeUser('admin', 'admin@example.test')->id,
            'reviewed_at' => now(),
        ]);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/me/account-status')
            ->assertOk()
            ->assertJsonPath('data.id', $reporter->id)
            ->assertJsonPath('data.role.code', 'reporter')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.registration_number', 'REG-20260611-0001')
            ->assertJsonMissingPath('data.permissions')
            ->assertJsonMissingPath('data.reviewed_by')
            ->assertJsonMissingPath('data.approved_user_id')
            ->assertJsonMissingPath('data.password_hash');
    }

    public function test_reporter_can_change_password_and_revoke_other_tokens_only(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test', '230001');
        $currentToken = $reporter->createToken('current')->plainTextToken;
        $otherToken = $reporter->createToken('other')->accessToken;

        $this->withToken($currentToken)
            ->patchJson('/api/v1/me/change-password', [
                'current_password' => 'SecurePass123',
                'password' => 'NewSecurePass123',
                'password_confirmation' => 'NewSecurePass123',
            ])->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterSelfServicePasswordChanged->value,
            'actor_id' => $reporter->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => PersonalAccessToken::findToken($currentToken)?->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->id]);

        $this->withToken($currentToken)->getJson('/api/v1/me/profile')->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'reporter@example.test',
            'password' => 'SecurePass123',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'reporter@example.test',
            'password' => 'NewSecurePass123',
        ])->assertOk();
    }

    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test', '230001');

        Sanctum::actingAs($reporter, ['*']);

        $this->patchJson('/api/v1/me/change-password', [
            'current_password' => 'WrongPass123',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'current_password_incorrect')
            ->assertJsonValidationErrors(['current_password']);

        $this->patchJson('/api/v1/me/change-password', [
            'current_password' => 'SecurePass123',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'MismatchPass123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_only_reporter_role_can_access_self_service_endpoints(): void
    {
        foreach ([
            $this->makeUser('admin', 'admin@example.test'),
            $this->makeUser('super_admin', 'super@example.test'),
            $this->makeUser('satgas_ppks', 'satgas@example.test'),
        ] as $user) {
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/v1/me/profile')->assertForbidden();
            $this->patchJson('/api/v1/me/profile', ['name' => 'Blocked User'])->assertForbidden();
            $this->patchJson('/api/v1/me/change-password', [
                'current_password' => 'SecurePass123',
                'password' => 'NewSecurePass123',
                'password_confirmation' => 'NewSecurePass123',
            ])->assertForbidden();
            $this->getJson('/api/v1/me/account-status')->assertForbidden();
        }
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/me/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/me/profile', ['name' => 'Guest'])->assertUnauthorized();
        $this->patchJson('/api/v1/me/change-password', [
            'current_password' => 'SecurePass123',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertUnauthorized();
        $this->getJson('/api/v1/me/account-status')->assertUnauthorized();
    }

    private function makeUser(string $roleCode, string $email, ?string $nim = null): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'nim' => $nim,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }
}
