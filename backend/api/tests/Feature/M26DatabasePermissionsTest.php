<?php

namespace Tests\Feature;

use App\Models\BreakGlassRequest;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class M26DatabasePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_break_glass_requests_schema_exists_without_update_or_delete_columns(): void
    {
        $this->assertTrue(Schema::hasTable('break_glass_requests'));
        $this->assertTrue(Schema::hasColumns('break_glass_requests', [
            'id',
            'requestor_id',
            'approver_id',
            'report_id',
            'reason_category',
            'reason',
            'requested_duration_minutes',
            'status',
            'denial_reason',
            'requested_at',
            'approved_at',
            'grant_starts_at',
            'expires_at',
            'revoked_at',
            'revoked_by',
            'revocation_reason',
            'denied_at',
            'viewed_at',
            'view_count',
            'last_viewed_at',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('break_glass_requests', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('break_glass_requests', 'deleted_at'));
    }

    public function test_break_glass_model_relationships_and_status_helpers(): void
    {
        $requestor = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $approver = $this->makeUser('admin', 'admin@example.test');
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter);

        $breakGlassRequest = BreakGlassRequest::query()->create([
            'requestor_id' => $requestor->id,
            'approver_id' => $approver->id,
            'report_id' => $report->id,
            'reason_category' => 'legal_requirement',
            'reason' => 'A legally mandated review requires controlled identity access for this anonymous report.',
            'requested_duration_minutes' => 60,
            'status' => BreakGlassRequest::STATUS_APPROVED,
            'requested_at' => now(),
            'approved_at' => now(),
            'grant_starts_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($breakGlassRequest->requestor->is($requestor));
        $this->assertTrue($breakGlassRequest->approver->is($approver));
        $this->assertTrue($breakGlassRequest->report->is($report));
        $this->assertTrue($breakGlassRequest->isApproved());
        $this->assertTrue($breakGlassRequest->isViewable());
        $this->assertFalse($breakGlassRequest->isExpired());

        $breakGlassRequest->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertFalse($breakGlassRequest->refresh()->isViewable());
        $this->assertTrue($breakGlassRequest->isExpired());
        $this->assertSame([
            'legal_requirement',
            'safety_emergency',
            'investigation_necessity',
            'institutional_compliance',
            'victim_consent',
        ], BreakGlassRequest::REASON_CATEGORIES);
        $this->assertSame([30, 60, 240, 1440], BreakGlassRequest::ALLOWED_DURATIONS);
    }

    public function test_m26_privacy_permissions_are_seeded_for_expected_roles_only(): void
    {
        $this->seed(RbacSeeder::class);

        $superAdmin = Role::query()->where('code', 'super_admin')->with('permissions')->firstOrFail();
        $admin = Role::query()->where('code', 'admin')->with('permissions')->firstOrFail();
        $satgas = Role::query()->where('code', 'satgas_ppks')->with('permissions')->firstOrFail();
        $reporter = Role::query()->where('code', 'reporter')->with('permissions')->firstOrFail();

        $this->assertDatabaseHas('permissions', [
            'code' => 'privacy.request_break_glass',
            'module' => 'Privasi',
        ]);
        $this->assertDatabaseHas('permissions', [
            'code' => 'privacy.approve_break_glass',
            'module' => 'Privasi',
        ]);
        $this->assertDatabaseHas('permissions', [
            'code' => 'privacy.reveal_anonymous_identity',
            'module' => 'Privasi',
        ]);

        $this->assertFalse($superAdmin->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertFalse($superAdmin->permissions->contains('code', 'privacy.reveal_anonymous_identity'));

        $this->assertFalse($admin->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertTrue($admin->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertFalse($admin->permissions->contains('code', 'privacy.reveal_anonymous_identity'));

        $this->assertTrue($satgas->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertFalse($satgas->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertTrue($satgas->permissions->contains('code', 'privacy.reveal_anonymous_identity'));

        $this->assertFalse($reporter->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertFalse($reporter->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertFalse($reporter->permissions->contains('code', 'privacy.reveal_anonymous_identity'));
    }

    public function test_r2_migration_backfills_legacy_grants_without_fabricating_reveal_audits(): void
    {
        $requestor = $this->makeUser('satgas_ppks', 'legacy-satgas@example.test');
        $approver = $this->makeUser('admin', 'legacy-admin@example.test');
        $report = $this->makeReport($this->makeUser('reporter', 'legacy-reporter@example.test'));
        $migration = require database_path('migrations/2026_07_20_000000_add_emergency_access_lifecycle_to_break_glass_requests.php');

        $unrevealedId = DB::table('break_glass_requests')->insertGetId([
            'requestor_id' => $requestor->id,
            'approver_id' => $approver->id,
            'report_id' => $report->id,
            'reason_category' => 'legal_requirement',
            'reason' => 'Approved grant that has not been revealed before rollback.',
            'status' => BreakGlassRequest::STATUS_APPROVED,
            'requested_at' => now()->subMinutes(10),
            'approved_at' => now()->subMinutes(5),
            'grant_starts_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(55),
            'view_count' => 0,
            'created_at' => now()->subMinutes(10),
        ]);

        $migration->down();

        $this->assertNull(DB::table('break_glass_requests')->where('id', $unrevealedId)->value('viewed_at'));

        $requestedAt = now()->subHour();
        $viewedAt = now()->subMinutes(30);
        $id = DB::table('break_glass_requests')->insertGetId([
            'requestor_id' => $requestor->id,
            'approver_id' => $approver->id,
            'report_id' => $report->id,
            'reason_category' => 'legal_requirement',
            'reason' => 'Legacy approved request retained for migration compatibility testing.',
            'status' => BreakGlassRequest::STATUS_VIEWED,
            'requested_at' => $requestedAt,
            'approved_at' => $requestedAt,
            'viewed_at' => $viewedAt,
            'created_at' => $requestedAt,
        ]);

        $migration->up();

        $legacy = BreakGlassRequest::query()->findOrFail($id);
        $this->assertSame(480, $legacy->requested_duration_minutes);
        $this->assertSame($viewedAt->toDateTimeString(), $legacy->grant_starts_at->toDateTimeString());
        $this->assertSame(
            $viewedAt->copy()->addMinutes(480)->toDateTimeString(),
            $legacy->expires_at->toDateTimeString(),
        );
        $this->assertSame(1, $legacy->view_count);
        $this->assertSame($viewedAt->toDateTimeString(), $legacy->last_viewed_at->toDateTimeString());
        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => $legacy->getMorphClass(),
            'subject_id' => $legacy->id,
            'action' => \App\Enums\AuditAction::BreakGlassIdentityViewed->value,
        ]);
    }

    public function test_r2_deployed_permission_migration_reconciles_operational_roles(): void
    {
        $managedCodes = [
            'system.break_glass_access',
            'privacy.request_break_glass',
            'privacy.approve_break_glass',
            'privacy.reveal_anonymous_identity',
        ];
        $roles = Role::query()->whereIn('code', ['super_admin', 'admin', 'satgas_ppks', 'reporter'])->get()->keyBy('code');
        $permissionIds = DB::table('permissions')->whereIn('code', $managedCodes)->pluck('id')->all();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $migration = require database_path('migrations/2026_07_20_010000_reconcile_r2_emergency_access_permissions.php');
        $migration->up();

        $this->assertSame(
            [],
            $roles['super_admin']->fresh()->permissions()->whereIn('permissions.code', $managedCodes)->pluck('permissions.code')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['privacy.approve_break_glass'],
            $roles['admin']->fresh()->permissions()->whereIn('permissions.code', $managedCodes)->pluck('permissions.code')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['privacy.request_break_glass', 'privacy.reveal_anonymous_identity'],
            $roles['satgas_ppks']->fresh()->permissions()->whereIn('permissions.code', $managedCodes)->pluck('permissions.code')->all(),
        );
        $this->assertSame(
            [],
            $roles['reporter']->fresh()->permissions()->whereIn('permissions.code', $managedCodes)->pluck('permissions.code')->all(),
        );
    }

    private function makeUser(string $roleCode, string $email): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function makeReport(User $reporter): Report
    {
        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-20260622-0001',
            'tracking_code' => 'ABCD-EFGH-IJKL-MNOP',
            'report_type' => 'anonymous',
            'category_code' => 'cat-1',
            'chronology' => 'This is a fictional anonymous report created only for M26 Phase A schema testing.',
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Fictional campus location',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}
