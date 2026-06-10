<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_seeders_create_expected_roles_and_permissions_without_anonymous(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(RbacSeeder::class);

        $this->assertDatabaseHas('roles', ['code' => 'super_admin']);
        $this->assertDatabaseHas('roles', ['code' => 'admin']);
        $this->assertDatabaseHas('roles', ['code' => 'satgas_ppks']);
        $this->assertDatabaseHas('roles', ['code' => 'reporter']);
        $this->assertDatabaseMissing('roles', ['code' => 'anonymous']);

        $this->assertDatabaseHas('permissions', ['code' => 'reports.create']);
        $this->assertDatabaseHas('permissions', ['code' => 'system.break_glass_access']);

        $reporter = Role::query()->where('code', 'reporter')->with('permissions')->firstOrFail();
        $this->assertTrue($reporter->permissions->contains('code', 'reports.create'));
        $this->assertTrue($reporter->permissions->contains('code', 'evidence.upload'));
    }

    public function test_database_seeder_does_not_create_dummy_users(): void
    {
        $userCount = User::query()->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($userCount, User::query()->count());
    }
}
