<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Permission;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CaseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_admin_assigns_and_reassigns_same_campus_satgas_with_history_audit_and_notifications(): void
    {
        $admin = $this->user('admin', 'assign-admin@example.test');
        $satgasA = $this->user('satgas_ppks', 'assign-a@example.test');
        $satgasB = $this->user('satgas_ppks', 'assign-b@example.test');
        $case = $this->case();

        $this->actingAsApi($admin);
        $first = $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasA->id],
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertOk()
            ->assertJsonPath('data.assignments.0.satgas_id', $satgasA->id)
            ->assertJsonPath('data.assignments.0.assignment_type', 'assign')
            ->assertJsonMissingPath('data.report');

        $freshLock = $first->json('data.lock_version');
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasB->id],
            'lock_version' => $freshLock,
        ])->assertOk()
            ->assertJsonPath('data.assignments.0.satgas_id', $satgasB->id)
            ->assertJsonCount(2, 'data.assignment_history');
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasA->id],
            'lock_version' => $freshLock,
        ])->assertConflict()
            ->assertJsonPath('error_code', 'case_assignment_stale');

        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasA->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasB->id,
            'is_active' => true,
            'is_lead' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseAssigned->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseReassigned->value]);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_admin_reassignment_preserves_retained_assignment_history_and_only_notifies_new_assignees(): void
    {
        $firstAdmin = $this->user('admin', 'retained-first-admin@example.test');
        $secondAdmin = $this->user('admin', 'retained-second-admin@example.test');
        $satgasA = $this->user('satgas_ppks', 'retained-a@example.test');
        $satgasB = $this->user('satgas_ppks', 'retained-b@example.test');
        $case = $this->case();

        $this->actingAsApi($firstAdmin);
        $first = $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasA->id],
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertOk();

        $retainedBefore = CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $satgasA->id)
            ->firstOrFail();

        $this->travel(1)->seconds();
        $this->actingAsApi($secondAdmin);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasA->id, $satgasB->id],
            'lock_version' => $first->json('data.lock_version'),
        ])->assertOk()
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonCount(2, 'data.assignment_history');

        $retainedAfter = CaseAssignment::query()->findOrFail($retainedBefore->id);
        $this->assertSame($firstAdmin->id, $retainedAfter->assigned_by);
        $this->assertSame(
            $retainedBefore->assigned_at?->toJSON(),
            $retainedAfter->assigned_at?->toJSON(),
        );
        $this->assertSame(
            $retainedBefore->updated_at?->toJSON(),
            $retainedAfter->updated_at?->toJSON(),
        );
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasB->id,
            'assigned_by' => $secondAdmin->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('case_assignments', 2);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_assignment_and_after_commit_notification_roll_back_when_audit_fails(): void
    {
        $admin = $this->user('admin', 'rollback-admin@example.test');
        $satgas = $this->user('satgas_ppks', 'rollback-satgas@example.test');
        $case = $this->case();
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException('audit unavailable'));
        });

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgas->id],
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertServerError();

        $this->assertDatabaseCount('case_assignments', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assignment_lock_token_is_timezone_stable_and_ignores_unrelated_case_updates(): void
    {
        $actor = $this->user('admin', 'token-admin@example.test');
        $satgas = $this->user('satgas_ppks', 'token-satgas@example.test');
        $case = $this->case();
        $instant = CarbonImmutable::parse('2026-07-24 08:09:10.123456', 'Asia/Jakarta');

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $actor->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => $instant,
            'created_at' => $instant,
            'updated_at' => $instant,
        ]);

        $case = $case->fresh()->load('activeAssignments');
        $timestampCanonicalizer = new \ReflectionMethod($case, 'assignmentTokenTimestamp');
        $this->assertSame(
            $timestampCanonicalizer->invoke($case, $instant),
            $timestampCanonicalizer->invoke($case, $instant->utc()),
        );
        $storedToken = $case->assignmentLockVersion();

        CaseRecord::query()->whereKey($case->id)->update([
            'current_stage' => (int) $case->current_stage + 1,
            'updated_at' => now()->addMinute(),
        ]);
        $this->assertSame($storedToken, $case->fresh()->assignmentLockVersion());
    }

    public function test_satgas_can_list_and_claim_unassigned_same_campus_case_without_target_identity(): void
    {
        $satgas = $this->user('satgas_ppks', 'self-assign@example.test');
        $other = $this->user('satgas_ppks', 'spoof-target@example.test');
        $case = $this->case();

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/cases?assignment_status=unassigned')
            ->assertOk()
            ->assertJsonPath('data.0.id', $case->id)
            ->assertJsonPath('data.0.assignment_capabilities.self_assign.allowed', true);

        foreach ([
            'user_id' => $other->id,
            'satgas_id' => $other->id,
            'satgas_ids' => [$other->id],
            'assignee_id' => $other->id,
            'lead_satgas_id' => $other->id,
        ] as $field => $value) {
            $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
                'lock_version' => $case->assignmentLockVersion(),
                $field => $value,
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('case_assignments', 0);

        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertOk()
            ->assertJsonPath('data.assignments.0.satgas_id', $satgas->id)
            ->assertJsonPath('data.assignments.0.assignment_type', 'self_assign')
            ->assertJsonMissingPath('data.report');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseSelfAssigned->value]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $satgas->id]);
    }

    public function test_self_assignment_is_permission_and_campus_scoped(): void
    {
        $case = $this->case();
        $crossCampusSatgas = $this->user('satgas_ppks', 'cross-campus@example.test', 'DEMO-ST');

        $this->actingAsApi($crossCampusSatgas);
        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertForbidden();

        $inactiveSatgas = $this->user('satgas_ppks', 'inactive-self@example.test');
        $inactiveSatgas->forceFill(['is_active' => false])->save();
        $this->actingAsApi($inactiveSatgas);
        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertForbidden();

        $sameCampusSatgas = $this->user('satgas_ppks', 'no-permission@example.test');
        $permission = Permission::query()->where('code', 'cases.read.assigned')->firstOrFail();
        $sameCampusSatgas->role->permissions()->detach($permission->id);
        $sameCampusSatgas->unsetRelation('role');

        $this->actingAsApi($sameCampusSatgas);
        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertForbidden();
        $this->assertDatabaseCount('case_assignments', 0);
    }

    public function test_admin_assignment_requires_exact_role_permission_and_campus_scope(): void
    {
        $case = $this->case();
        $satgas = $this->user('satgas_ppks', 'authorization-target@example.test');
        $actors = [
            $this->user('admin', 'authorization-cross-campus@example.test', 'DEMO-ST'),
            $this->user('super_admin', 'authorization-super-admin@example.test', null),
            $this->user('reporter', 'authorization-reporter@example.test'),
        ];

        foreach ($actors as $actor) {
            $this->actingAsApi($actor);
            $this->patchJson("/api/v1/cases/{$case->id}/assign", [
                'satgas_ids' => [$satgas->id],
                'lock_version' => $case->assignmentLockVersion(),
            ])->assertForbidden();
        }

        $adminWithoutPermission = $this->user('admin', 'authorization-no-permission@example.test');
        $permission = Permission::query()->where('code', 'cases.assign_satgas')->firstOrFail();
        $adminWithoutPermission->role->permissions()->detach($permission->id);
        $adminWithoutPermission->unsetRelation('role');

        $this->actingAsApi($adminWithoutPermission);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgas->id],
            'lock_version' => $case->assignmentLockVersion(),
        ])->assertForbidden();

        $this->assertDatabaseCount('case_assignments', 0);
    }

    public function test_admin_assignment_rejects_cross_campus_inactive_and_non_satgas_targets(): void
    {
        $admin = $this->user('admin', 'target-admin@example.test');
        $crossCampus = $this->user('satgas_ppks', 'target-cross@example.test', 'DEMO-ST');
        $inactive = $this->user('satgas_ppks', 'target-inactive@example.test');
        $inactive->forceFill(['is_active' => false])->save();
        $reporter = $this->user('reporter', 'target-reporter@example.test');

        foreach ([$crossCampus, $inactive, $reporter] as $target) {
            $case = $this->case();
            $this->actingAsApi($admin);
            $this->patchJson("/api/v1/cases/{$case->id}/assign", [
                'satgas_ids' => [$target->id],
                'lock_version' => $case->assignmentLockVersion(),
            ])->assertUnprocessable();
            $this->assertFalse($case->activeAssignments()->exists());
        }
    }

    public function test_terminal_withdrawn_and_pending_withdrawal_cases_reject_assignment_mutations(): void
    {
        $admin = $this->user('admin', 'guard-admin@example.test');
        $satgas = $this->user('satgas_ppks', 'guard-satgas@example.test');
        $withdrawn = $this->case(CaseStatusEnum::Withdrawn);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$withdrawn->id}/self-assign", [
            'lock_version' => $withdrawn->assignmentLockVersion(),
        ])->assertConflict()
            ->assertJsonPath('error_code', 'case_operationally_terminal');

        $pending = $this->case();
        ReportWithdrawal::query()->create([
            'report_id' => $pending->report_id,
            'case_id' => $pending->id,
            'requester_id' => $pending->report->reporter_id,
            'request_type' => ReportWithdrawalRequestType::FormalWithdrawal->value,
            'status' => ReportWithdrawalStatus::PendingReview->value,
            'reason' => 'Alasan pencabutan formal yang valid untuk pengujian.',
            'previous_report_status' => ReportStatus::Forwarded->value,
            'previous_case_status' => $pending->status_code,
            'submitted_at' => now(),
        ]);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$pending->id}/assign", [
            'satgas_ids' => [$satgas->id],
            'lock_version' => $pending->assignmentLockVersion(),
        ])->assertConflict()
            ->assertJsonPath('error_code', 'withdrawal_pending_review');

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$pending->id}/self-assign", [
            'lock_version' => $pending->assignmentLockVersion(),
        ])->assertConflict()
            ->assertJsonPath('error_code', 'withdrawal_pending_review');
    }

    public function test_final_decision_and_follow_up_stages_are_read_only_for_assignment(): void
    {
        $admin = $this->user('admin', 'read-only-admin@example.test');
        $satgas = $this->user('satgas_ppks', 'read-only-satgas@example.test');
        $caseIds = [];

        foreach ([
            CaseStatusEnum::Decided,
            CaseStatusEnum::Recovery,
            CaseStatusEnum::Monitoring,
            CaseStatusEnum::Escalated,
        ] as $status) {
            $case = $this->case($status);
            $caseIds[] = $case->id;

            $this->actingAsApi($admin);
            $this->patchJson("/api/v1/cases/{$case->id}/assign", [
                'satgas_ids' => [$satgas->id],
                'lock_version' => $case->assignmentLockVersion(),
            ])->assertConflict()
                ->assertJsonPath('error_code', 'case_assignment_read_only');

            $this->actingAsApi($satgas);
            $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
                'lock_version' => $case->assignmentLockVersion(),
            ])->assertConflict()
                ->assertJsonPath('error_code', 'case_assignment_read_only');
        }

        $available = $this->getJson('/api/v1/cases?assignment_status=unassigned')->assertOk();
        $availableIds = array_column($available->json('data'), 'id');

        foreach ($caseIds as $caseId) {
            $this->assertNotContains($caseId, $availableIds);
        }

        $this->assertDatabaseCount('case_assignments', 0);
    }

    public function test_stale_tokens_and_competing_self_assignments_leave_one_active_assignment(): void
    {
        $satgasA = $this->user('satgas_ppks', 'race-a@example.test');
        $satgasB = $this->user('satgas_ppks', 'race-b@example.test');
        $case = $this->case();
        $initialLock = $case->assignmentLockVersion();

        $this->actingAsApi($satgasA);
        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $initialLock,
        ])->assertOk();

        $this->actingAsApi($satgasB);
        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $initialLock,
        ])->assertConflict()
            ->assertJsonPath('error_code', 'case_assignment_stale');

        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $case->fresh()->assignmentLockVersion(),
        ])->assertConflict()
            ->assertJsonPath('error_code', 'case_assignment_unavailable');

        $this->assertSame(1, CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('is_active', true)
            ->count());
    }

    public function test_legacy_lead_flag_does_not_grant_assignment_or_investigation_authority(): void
    {
        $reporter = $this->user('reporter', 'legacy-lead@example.test');
        $case = $this->case();
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $reporter->id,
            'assigned_by' => $reporter->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAsApi($reporter);
        $this->postJson("/api/v1/cases/{$case->id}/self-assign", [
            'lock_version' => $case->fresh()->assignmentLockVersion(),
        ])->assertForbidden();
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'plan_summary' => 'Rencana investigasi ini cukup panjang untuk validasi pengujian.',
        ])->assertForbidden();
    }

    private function case(CaseStatusEnum $status = CaseStatusEnum::Forwarded): CaseRecord
    {
        $reporter = $this->user('reporter', 'case-reporter-'.Report::query()->count().'@example.test');
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-2026-'.str_pad((string) (Report::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi pengaduan yang cukup panjang untuk pengujian penugasan Satgas.',
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Kampus utama',
            'status' => ReportStatus::Forwarded->value,
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);
        $caseStatus = CaseStatus::query()->where('name', $status->value)->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-ASSIGN-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $caseStatus->code,
            'current_stage' => $caseStatus->workflow_stage ?? 2,
            'forwarded_at' => now(),
            'withdrawn_at' => $status === CaseStatusEnum::Withdrawn ? now() : null,
        ])->load('report');
    }

    private function user(
        string $roleCode,
        string $email,
        ?string $universityCode = 'DEMO-UNIV',
    ): User {
        return User::query()->create([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'university_id' => $universityCode === null
                ? null
                : University::query()->where('code', $universityCode)->value('id'),
            'name' => $roleCode.' assignment user',
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
