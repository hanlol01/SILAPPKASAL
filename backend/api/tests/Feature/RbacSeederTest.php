<?php

namespace Tests\Feature;

use App\Models\Permission;
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
        $this->assertDatabaseHas('permissions', ['code' => 'evidence.upload']);
        $this->assertDatabaseHas('permissions', ['code' => 'evidence.download']);
        $this->assertDatabaseHas('permissions', ['code' => 'reporter_evidence.read.own']);
        $this->assertDatabaseHas('permissions', ['code' => 'reporter_evidence.upload.own']);
        $this->assertDatabaseHas('permissions', ['code' => 'reporter_evidence.download.own']);
        $this->assertDatabaseHas('permissions', ['code' => 'reporter_evidence.read.assigned']);
        $this->assertDatabaseHas('permissions', ['code' => 'reporter_evidence.download.assigned']);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.review_recommendation']);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.read.sensitive_oversight']);

        $reporter = Role::query()->where('code', 'reporter')->with('permissions')->firstOrFail();
        $this->assertTrue($reporter->permissions->contains('code', 'reports.create'));

        $admin = Role::query()->where('code', 'admin')->with('permissions')->firstOrFail();
        $superAdmin = Role::query()->where('code', 'super_admin')->with('permissions')->firstOrFail();
        $satgas = Role::query()->where('code', 'satgas_ppks')->with('permissions')->firstOrFail();

        foreach ([$superAdmin, $admin, $reporter] as $role) {
            $this->assertFalse($role->permissions->contains('code', 'evidence.upload'));
            $this->assertFalse($role->permissions->contains('code', 'evidence.download'));
        }

        $this->assertTrue($satgas->permissions->contains('code', 'evidence.upload'));
        $this->assertTrue($satgas->permissions->contains('code', 'evidence.download'));
        $this->assertTrue($satgas->permissions->contains('code', 'reporter_evidence.read.assigned'));
        $this->assertTrue($satgas->permissions->contains('code', 'reporter_evidence.download.assigned'));
        $this->assertFalse($satgas->permissions->contains('code', 'reporter_evidence.read.own'));
        $this->assertTrue($reporter->permissions->contains('code', 'reporter_evidence.read.own'));
        $this->assertTrue($reporter->permissions->contains('code', 'reporter_evidence.upload.own'));
        $this->assertTrue($reporter->permissions->contains('code', 'reporter_evidence.download.own'));
        $this->assertFalse($reporter->permissions->contains('code', 'reporter_evidence.read.assigned'));

        foreach ([$superAdmin, $admin] as $role) {
            $this->assertFalse($role->permissions->contains('code', 'reporter_evidence.read.own'));
            $this->assertFalse($role->permissions->contains('code', 'reporter_evidence.upload.own'));
            $this->assertFalse($role->permissions->contains('code', 'reporter_evidence.download.own'));
            $this->assertFalse($role->permissions->contains('code', 'reporter_evidence.read.assigned'));
            $this->assertFalse($role->permissions->contains('code', 'reporter_evidence.download.assigned'));
        }

        $this->assertTrue($admin->permissions->contains('code', 'cases.record_decision'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'cases.record_decision'));
        $this->assertFalse($satgas->permissions->contains('code', 'cases.record_decision'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'cases.review_recommendation'));
        $this->assertTrue($admin->permissions->contains('code', 'cases.review_recommendation'));
        $this->assertFalse($satgas->permissions->contains('code', 'cases.review_recommendation'));
        $this->assertFalse($reporter->permissions->contains('code', 'cases.review_recommendation'));
        $this->assertTrue($admin->permissions->contains('code', 'cases.monitor'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'cases.monitor'));
        $this->assertTrue($satgas->permissions->contains('code', 'cases.monitor'));
        $this->assertTrue($superAdmin->permissions->contains('code', 'cases.read.sensitive_oversight'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'reports.forward'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'cases.assign_satgas'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'system.break_glass_access'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertTrue($admin->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertTrue($satgas->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertTrue($satgas->permissions->contains('code', 'privacy.reveal_anonymous_identity'));
    }

    public function test_database_seeder_creates_demo_dataset_v2_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'superadmin@silappkasal.test']);
        $this->assertDatabaseHas('users', ['email' => 'admin.staisa@silappkasal.test']);
        $this->assertDatabaseHas('users', ['email' => 'satgas.staisa@silappkasal.test']);
        $this->assertDatabaseHas('users', ['email' => 'reporter.staisa@silappkasal.test']);
        $this->assertGreaterThanOrEqual(36, User::query()->count());

        $superAdmin = User::query()->where('email', 'superadmin@silappkasal.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin.staisa@silappkasal.test')->firstOrFail();
        $this->assertNotContains(
            'recommendation_submitted_for_review',
            $superAdmin->notifications->pluck('data.event')->all(),
        );
        $this->assertNotContains(
            'decision_finalized',
            $superAdmin->notifications->pluck('data.event')->all(),
        );
        $this->assertContains(
            'recommendation_submitted_for_review',
            $admin->notifications->pluck('data.event')->all(),
        );
        $satgas = User::query()->where('email', 'satgas.staisa@silappkasal.test')->firstOrFail();
        $this->assertContains(
            'decision_finalized',
            $satgas->notifications->pluck('data.event')->all(),
        );
    }

    public function test_rbac_seeder_reconciles_managed_permissions_without_removing_extensions(): void
    {
        $this->seed(RbacSeeder::class);

        $satgas = Role::query()->where('code', 'satgas_ppks')->firstOrFail();
        $reporter = Role::query()->where('code', 'reporter')->firstOrFail();
        $customPermission = Permission::query()->create([
            'code' => 'integration.case_export',
            'name' => 'Integration Case Export',
            'description' => 'Permission managed by an installed integration.',
            'module' => 'Integration',
        ]);
        $legacyReporterUpload = Permission::query()->where('code', 'evidence.upload')->firstOrFail();
        $assignedReporterEvidence = Permission::query()->where('code', 'reporter_evidence.download.assigned')->firstOrFail();

        $satgas->permissions()->attach($customPermission->id);
        $reporter->permissions()->attach($legacyReporterUpload->id);
        $reporter->permissions()->attach($assignedReporterEvidence->id);

        $this->seed(RbacSeeder::class);

        $this->assertTrue($satgas->fresh()->permissions()->where('permissions.id', $customPermission->id)->exists());
        $this->assertFalse($reporter->fresh()->permissions()->where('permissions.code', 'evidence.upload')->exists());
        $this->assertFalse($reporter->fresh()->permissions()->where('permissions.code', 'reporter_evidence.download.assigned')->exists());
    }

    public function test_m3_migration_reconciles_existing_pivots_and_preserves_super_admin_platform_authorities(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = Role::query()->where('code', 'admin')->firstOrFail();
        $superAdmin = Role::query()->where('code', 'super_admin')->firstOrFail();
        $operationalCodes = [
            'reports.forward',
            'cases.assign_satgas',
            'cases.review_recommendation',
            'cases.monitor',
            'cases.record_decision',
            'cases.assess_risk',
            'cases.investigate',
            'cases.recommend',
            'cases.close',
            'evidence.upload',
        ];

        $superAdmin->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('code', $operationalCodes)->pluck('id')->all(),
        );
        $superAdmin->permissions()->detach(
            Permission::query()->where('code', 'cases.read.sensitive_oversight')->value('id'),
        );
        $admin->permissions()->detach(
            Permission::query()->where('code', 'cases.review_recommendation')->value('id'),
        );

        $migration = require database_path('migrations/2026_07_19_030000_reconcile_m3_operational_permissions.php');
        $migration->up();

        foreach ($operationalCodes as $permissionCode) {
            $this->assertFalse(
                $superAdmin->fresh()->permissions()->where('permissions.code', $permissionCode)->exists(),
                "Super Admin retained operational permission {$permissionCode}.",
            );
        }

        $this->assertTrue($superAdmin->fresh()->permissions()->where('permissions.code', 'cases.read.sensitive_oversight')->exists());
        $this->assertTrue($admin->fresh()->permissions()->where('permissions.code', 'cases.review_recommendation')->exists());

        foreach ([
            'system.configure',
            'system.audit_log.oversight',
            'system.audit_log.export',
            'users.assign_role',
        ] as $platformPermission) {
            $this->assertTrue(
                $superAdmin->fresh()->permissions()->where('permissions.code', $platformPermission)->exists(),
                "Super Admin lost platform permission {$platformPermission}.",
            );
        }

        $this->assertFalse($superAdmin->fresh()->permissions()->where('permissions.code', 'system.break_glass_access')->exists());
    }
}
