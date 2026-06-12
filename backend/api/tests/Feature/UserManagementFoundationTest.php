<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_admin_and_super_admin_can_list_and_view_safe_user_metadata(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test', '230001');

        foreach ([$admin, $superAdmin] as $actor) {
            Sanctum::actingAs($actor, ['*']);

            $this->getJson('/api/v1/users')
                ->assertOk()
                ->assertJsonFragment(['email' => 'satgas@example.test'])
                ->assertJsonMissingPath('data.0.password')
                ->assertJsonMissingPath('data.0.permissions')
                ->assertJsonMissingPath('data.0.tokens')
                ->assertJsonMissingPath('data.0.audit_logs');

            $this->getJson("/api/v1/users/{$satgas->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $satgas->id)
                ->assertJsonPath('data.role.code', 'satgas_ppks')
                ->assertJsonMissingPath('data.password')
                ->assertJsonMissingPath('data.permissions');
        }
    }

    public function test_satgas_and_reporter_cannot_access_user_management(): void
    {
        $target = $this->makeUser('reporter', 'target@example.test');

        foreach ([
            $this->makeUser('satgas_ppks', 'satgas@example.test'),
            $this->makeUser('reporter', 'reporter@example.test'),
        ] as $actor) {
            Sanctum::actingAs($actor, ['*']);

            $this->getJson('/api/v1/users')->assertForbidden();
            $this->getJson('/api/v1/users/lookup?role=satgas_ppks')->assertForbidden();
            $this->getJson("/api/v1/users/{$target->id}")->assertForbidden();
            $this->patchJson("/api/v1/users/{$target->id}/activate")->assertForbidden();
            $this->patchJson("/api/v1/users/{$target->id}/deactivate")->assertForbidden();
            $this->patchJson("/api/v1/users/{$target->id}/role", ['role_code' => 'admin'])->assertForbidden();
        }
    }

    public function test_lookup_returns_active_role_filtered_picker_safe_fields_without_email(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $inactiveSatgas = $this->makeUser('satgas_ppks', 'inactive@example.test', null, false);
        $this->makeUser('reporter', 'reporter@example.test');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/users/lookup?role=satgas_ppks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $satgas->id)
            ->assertJsonPath('data.0.name', 'satgas_ppks User')
            ->assertJsonPath('data.0.role.code', 'satgas_ppks')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.nim')
            ->assertJsonMissingPath('data.0.phone_number')
            ->assertJsonMissingPath('data.0.permissions');

        $this->assertNotSame($inactiveSatgas->id, $satgas->id);
    }

    public function test_lookup_rejects_invalid_and_super_admin_roles(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/users/lookup?role=super_admin')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->getJson('/api/v1/users/lookup?role=anonymous')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_admin_and_super_admin_can_deactivate_and_activate_user(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $target = $this->makeUser('reporter', 'target@example.test');
        $token = $target->createToken('target-token')->accessToken;

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/users/{$target->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'target@example.test',
            'password' => 'SecurePass123',
        ])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserDeactivated->value,
            'actor_id' => $admin->id,
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->patchJson("/api/v1/users/{$target->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserActivated->value,
            'actor_id' => $superAdmin->id,
        ]);
    }

    public function test_actor_cannot_deactivate_self_and_last_active_super_admin_is_protected(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson("/api/v1/users/{$admin->id}/deactivate")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot deactivate your own account');

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson("/api/v1/users/{$superAdmin->id}/deactivate")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The last active Super Admin cannot be modified');
    }

    public function test_super_admin_can_assign_allowed_roles_and_tokens_are_revoked(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $target = $this->makeUser('reporter', 'target@example.test');
        $token = $target->createToken('target-token')->accessToken;

        Sanctum::actingAs($superAdmin, ['*']);

        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role_code' => 'satgas_ppks',
        ])->assertOk()
            ->assertJsonPath('data.role.code', 'satgas_ppks');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserRoleChanged->value,
            'actor_id' => $superAdmin->id,
        ]);
    }

    public function test_role_assignment_rejects_super_admin_admin_actor_self_and_last_super_admin_changes(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $target = $this->makeUser('reporter', 'target@example.test');

        Sanctum::actingAs($superAdmin, ['*']);

        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role_code' => 'super_admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role_code']);

        $this->patchJson("/api/v1/users/{$superAdmin->id}/role", [
            'role_code' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot change your own role');

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/users/{$target->id}/role", [
            'role_code' => 'admin',
        ])->assertForbidden();

        $otherSuperAdmin = $this->makeUser('super_admin', 'other-super@example.test');
        Sanctum::actingAs($otherSuperAdmin, ['*']);

        $this->patchJson("/api/v1/users/{$superAdmin->id}/role", [
            'role_code' => 'admin',
        ])->assertOk();

        $this->patchJson("/api/v1/users/{$otherSuperAdmin->id}/role", [
            'role_code' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot change your own role');
    }

    public function test_user_management_responses_never_expose_sensitive_fields(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $target = $this->makeUser('reporter', 'target@example.test');

        Sanctum::actingAs($admin, ['*']);

        foreach ([
            $this->getJson('/api/v1/users'),
            $this->getJson("/api/v1/users/{$target->id}"),
            $this->patchJson("/api/v1/users/{$target->id}/deactivate"),
        ] as $response) {
            $content = $response->getContent();

            $this->assertStringNotContainsString('password', $content);
            $this->assertStringNotContainsString('remember_token', $content);
            $this->assertStringNotContainsString('tokens', $content);
            $this->assertStringNotContainsString('audit_logs', $content);
            $this->assertStringNotContainsString('permissions', $content);
            $this->assertStringNotContainsString('reporter_registrations', $content);
        }
    }

    private function makeUser(string $roleCode, string $email, ?string $nim = null, bool $isActive = true): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'nim' => $nim,
            'password' => 'SecurePass123',
            'is_active' => $isActive,
        ]);
    }
}
