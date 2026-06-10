<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_identifier_normalization_trims_and_lowercases_email_only(): void
    {
        $service = app(AuthService::class);

        $this->assertSame('user@university.ac.id', $service->normalizeIdentifier(' USER@UNIVERSITY.AC.ID '));
        $this->assertSame('ABC123', $service->normalizeIdentifier(' ABC123 '));
    }

    public function test_token_expiry_is_configurable(): void
    {
        config(['sanctum.expiration' => 30]);

        $this->assertSame(30, app(AuthService::class)->tokenExpirationMinutes());
    }

    public function test_user_role_and_permission_helpers(): void
    {
        $role = Role::query()->where('code', 'reporter')->firstOrFail();
        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Reporter User',
            'email' => 'unit-reporter@university.ac.id',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasRole('reporter'));
        $this->assertTrue($user->hasPermission('reports.create'));
        $this->assertFalse($user->hasPermission('system.configure'));
    }
}
