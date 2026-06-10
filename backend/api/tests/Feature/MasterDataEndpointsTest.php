<?php

namespace Tests\Feature;

use App\Models\ReportCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_master_data_requires_authentication(): void
    {
        $this->getJson('/api/v1/master/report-categories')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_read_active_master_data(): void
    {
        $user = $this->makeUser('reporter');
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/master/report-categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Data retrieved successfully')
            ->assertJsonPath('data.0.code', 'RCAT-01')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['code', 'name', 'description', 'is_active', 'sort_order'],
                ],
            ]);
    }

    public function test_reporter_cannot_include_inactive_records(): void
    {
        $user = $this->makeUser('reporter');
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/master/report-categories?include_inactive=true')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_include_inactive_records(): void
    {
        ReportCategory::query()->where('code', 'RCAT-99')->update(['is_active' => false]);
        $user = $this->makeUser('admin');
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/master/report-categories?include_inactive=true')
            ->assertOk()
            ->assertJsonFragment(['code' => 'RCAT-99']);
    }

    public function test_notification_types_are_not_exposed_as_master_endpoint(): void
    {
        $user = $this->makeUser('admin');
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/master/notification-types')
            ->assertNotFound();
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => "{$roleCode}@university.ac.id",
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }
}
