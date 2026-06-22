<?php

namespace Tests\Feature;

use App\Models\BreakGlassRequest;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status',
            'denial_reason',
            'requested_at',
            'approved_at',
            'denied_at',
            'viewed_at',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('break_glass_requests', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('break_glass_requests', 'deleted_at'));
    }

    public function test_break_glass_model_relationships_and_status_helpers(): void
    {
        $requestor = $this->makeUser('admin', 'admin@example.test');
        $approver = $this->makeUser('super_admin', 'super@example.test');
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter);

        $breakGlassRequest = BreakGlassRequest::query()->create([
            'requestor_id' => $requestor->id,
            'approver_id' => $approver->id,
            'report_id' => $report->id,
            'reason_category' => 'legal_requirement',
            'reason' => 'A legally mandated review requires controlled identity access for this anonymous report.',
            'status' => BreakGlassRequest::STATUS_APPROVED,
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        $this->assertTrue($breakGlassRequest->requestor->is($requestor));
        $this->assertTrue($breakGlassRequest->approver->is($approver));
        $this->assertTrue($breakGlassRequest->report->is($report));
        $this->assertTrue($breakGlassRequest->isApproved());
        $this->assertTrue($breakGlassRequest->isViewable());
        $this->assertFalse($breakGlassRequest->isExpired());

        $breakGlassRequest->forceFill(['viewed_at' => now()->subHours(9)])->save();

        $this->assertFalse($breakGlassRequest->refresh()->isViewable());
        $this->assertTrue($breakGlassRequest->isExpired());
        $this->assertSame([
            'legal_requirement',
            'safety_emergency',
            'investigation_necessity',
            'institutional_compliance',
            'victim_consent',
        ], BreakGlassRequest::REASON_CATEGORIES);
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

        $this->assertTrue($superAdmin->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertTrue($superAdmin->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertTrue($superAdmin->permissions->contains('code', 'privacy.reveal_anonymous_identity'));

        $this->assertTrue($admin->permissions->contains('code', 'privacy.request_break_glass'));
        $this->assertFalse($admin->permissions->contains('code', 'privacy.approve_break_glass'));
        $this->assertFalse($admin->permissions->contains('code', 'privacy.reveal_anonymous_identity'));

        foreach ([$satgas, $reporter] as $role) {
            $this->assertFalse($role->permissions->contains('code', 'privacy.request_break_glass'));
            $this->assertFalse($role->permissions->contains('code', 'privacy.approve_break_glass'));
            $this->assertFalse($role->permissions->contains('code', 'privacy.reveal_anonymous_identity'));
        }
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
