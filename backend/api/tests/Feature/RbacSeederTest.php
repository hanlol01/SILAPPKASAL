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

        $admin = Role::query()->where('code', 'admin')->with('permissions')->firstOrFail();
        $superAdmin = Role::query()->where('code', 'super_admin')->with('permissions')->firstOrFail();
        $satgas = Role::query()->where('code', 'satgas_ppks')->with('permissions')->firstOrFail();

        $this->assertTrue($admin->permissions->contains('code', 'cases.record_decision'));
        $this->assertTrue($superAdmin->permissions->contains('code', 'cases.record_decision'));
        $this->assertFalse($satgas->permissions->contains('code', 'cases.record_decision'));
        $this->assertTrue($admin->permissions->contains('code', 'cases.monitor'));
        $this->assertTrue($superAdmin->permissions->contains('code', 'cases.monitor'));
        $this->assertTrue($satgas->permissions->contains('code', 'cases.monitor'));
    }

    public function test_database_seeder_creates_demo_dataset_v2_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'superadmin@silappkasal.test']);
        $this->assertDatabaseHas('users', ['email' => 'admin.staisa@silappkasal.test']);
        $this->assertDatabaseHas('users', ['email' => 'satgas.staisa@silappkasal.test']);
        $this->assertDatabaseHas('users', ['email' => 'reporter.staisa@silappkasal.test']);
        $this->assertGreaterThanOrEqual(36, User::query()->count());
    }
}
