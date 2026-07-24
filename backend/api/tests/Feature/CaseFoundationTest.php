<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_case_foundation_tables_exist_without_out_of_scope_tables(): void
    {
        $this->assertTrue(Schema::hasTable('cases'));
        $this->assertTrue(Schema::hasColumn('cases', 'case_number'));
        $this->assertTrue(Schema::hasColumn('cases', 'status_code'));
        $this->assertTrue(Schema::hasTable('case_assignments'));
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertDatabaseCount('evidences', 0);
        $this->assertFalse(Schema::hasTable('risk_assessments'));
        $this->assertTrue(Schema::hasTable('investigations'));
        $this->assertDatabaseCount('investigations', 0);
        $this->assertTrue(Schema::hasTable('recommendations'));
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertDatabaseCount('decisions', 0);
    }

    public function test_admin_can_forward_report_to_case_transactionally(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $response = $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => [$satgas->id],
            'lead_satgas_id' => $satgas->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.status', ReportStatus::Forwarded->value)
            ->assertJsonPath('data.case.status', CaseStatusEnum::Forwarded->value)
            ->assertJsonPath('data.case.assignments.0.satgas_id', $satgas->id)
            ->assertJsonPath('data.case.assignments.0.is_lead', true);

        $report->refresh();
        $case = CaseRecord::query()->where('report_id', $report->id)->firstOrFail();

        $this->assertSame(ReportStatus::Forwarded->value, $report->status);
        $this->assertNotSame($report->registration_number, $case->case_number);
        $this->assertSame('CSTS-05', $case->status_code);
        $this->assertNull($case->priority_code);
        $this->assertDatabaseCount('case_assignments', 1);
        foreach ([AuditAction::ReportForwarded, AuditAction::CaseCreated, AuditAction::CaseAssigned] as $action) {
            $events = DB::table('audit_logs')->where('action', $action->value)->get();
            $this->assertCount(1, $events);
            $this->assertSame($response->headers->get('X-Request-ID'), $events->first()->request_id);
        }
    }

    public function test_campus_scope_denies_other_and_missing_campus_but_super_admin_is_metadata_read_only(): void
    {
        $admin = $this->makeUser('admin', 'admin-campus@university.ac.id');
        $otherAdmin = $this->makeUser('admin', 'other-admin@university.ac.id', 'DEMO-ST');
        $adminWithoutCampus = $this->makeUser('admin', 'no-campus-admin@university.ac.id', null);
        $superAdmin = $this->makeUser('super_admin', 'oversight@university.ac.id', null);
        $satgas = $this->makeUser('satgas_ppks', 'satgas-campus@university.ac.id');
        $report = $this->makeReport();
        $payload = [
            'satgas_ids' => [$satgas->id],
            'lead_satgas_id' => $satgas->id,
        ];

        foreach ([$otherAdmin, $adminWithoutCampus] as $deniedAdmin) {
            $this->actingAsApi($deniedAdmin);
            $this->getJson('/api/v1/reports')->assertOk()->assertJsonPath('meta.total', 0);
            $this->getJson("/api/v1/reports/{$report->id}")->assertForbidden();
            $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", $payload)->assertForbidden();
        }

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/reports')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/reports/{$report->id}")->assertOk();
        $caseId = $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", $payload)
            ->assertOk()
            ->json('data.case.id');

        foreach ([$otherAdmin, $adminWithoutCampus] as $deniedAdmin) {
            $this->actingAsApi($deniedAdmin);
            $this->getJson('/api/v1/cases')->assertOk()->assertJsonPath('meta.total', 0);
            $this->getJson("/api/v1/cases/{$caseId}")->assertForbidden();
            $this->patchJson("/api/v1/cases/{$caseId}/assign", $payload)->assertForbidden();
        }

        config()->set('oversight.cross_campus_sensitive_read', false);
        $this->actingAsApi($superAdmin);
        $this->getJson('/api/v1/reports')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.sensitive_details');
        $this->getJson('/api/v1/cases')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/cases/{$caseId}")->assertOk();
        $unforwardedReport = $this->makeReport();
        $this->postJson("/api/v1/reports/{$unforwardedReport->id}/forward-to-case", $payload)
            ->assertForbidden();
        $this->patchJson("/api/v1/cases/{$caseId}/assign", $payload)->assertForbidden();
        $this->patchJson("/api/v1/cases/{$caseId}/status", [
            'status' => CaseStatusEnum::Assessment->value,
        ])->assertForbidden();
        $this->patchJson("/api/v1/cases/{$caseId}/assessment", [
            'risk_level_code' => 'RISK-02',
            'priority_level_code' => 'PRIO-02',
        ])->assertForbidden();

        CaseRecord::query()->whereKey($caseId)->update(['registration_number' => 'INCONSISTENT-CAMPUS-RELATION']);
        $this->actingAsApi($admin);
        $this->getJson('/api/v1/cases')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/cases/{$caseId}")->assertForbidden();
    }

    public function test_admin_satgas_filters_and_super_admin_report_campus_filter_are_role_scoped(): void
    {
        $admin = $this->makeUser('admin', 'admin-filter@university.ac.id');
        $satgasA = $this->makeUser('satgas_ppks', 'satgas-a-filter@university.ac.id');
        $satgasB = $this->makeUser('satgas_ppks', 'satgas-b-filter@university.ac.id');
        $campusACase = $this->forwardedCase($admin, [$satgasA], $satgasA);
        $secondCampusACase = $this->forwardedCase($admin, [$satgasB], $satgasB);

        $otherAdmin = $this->makeUser('admin', 'other-admin-filter@university.ac.id', 'DEMO-ST');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas-filter@university.ac.id', 'DEMO-ST');
        $otherReporter = $this->makeUser('reporter', 'other-reporter-filter@university.ac.id', 'DEMO-ST');
        $otherReport = $this->makeReport(['reporter_id' => $otherReporter->id]);
        $this->actingAsApi($otherAdmin);
        $this->postJson("/api/v1/reports/{$otherReport->id}/forward-to-case", [
            'satgas_ids' => [$otherSatgas->id],
            'lead_satgas_id' => $otherSatgas->id,
        ])->assertOk();

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/reports?satgas_id={$satgasA->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $campusACase->report_id);
        $this->getJson("/api/v1/cases?satgas_id={$satgasA->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $campusACase->id);
        $this->getJson("/api/v1/reports?satgas_id={$otherSatgas->id}")
            ->assertUnprocessable();
        $this->getJson("/api/v1/cases?satgas_id={$otherSatgas->id}")
            ->assertUnprocessable();
        $this->getJson('/api/v1/reports?university_id=1')->assertUnprocessable();
        $this->getJson('/api/v1/cases?university_id=1')->assertUnprocessable();

        $campusAId = University::query()->where('code', 'DEMO-UNIV')->value('id');
        $campusBId = University::query()->where('code', 'DEMO-ST')->value('id');
        $superAdmin = $this->makeUser('super_admin', 'super-filter@university.ac.id', null);
        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/reports?university_id={$campusAId}")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->getJson("/api/v1/reports?university_id={$campusBId}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $otherReport->id);
        $this->getJson("/api/v1/cases?university_id={$campusAId}")->assertUnprocessable();
        $this->getJson("/api/v1/cases?university_id={$campusBId}")->assertUnprocessable();
        $this->getJson("/api/v1/reports?satgas_id={$satgasA->id}")->assertUnprocessable();
        $this->getJson("/api/v1/cases?satgas_id={$satgasA->id}")->assertUnprocessable();

        $this->assertNotSame($campusACase->id, $secondCampusACase->id);
    }

    public function test_admin_unassigned_filters_use_only_active_assignments_and_preserve_pagination_scope(): void
    {
        $admin = $this->makeUser('admin', 'admin-unassigned-filter@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-unassigned-filter@university.ac.id');
        $foreignSatgas = $this->makeUser(
            'satgas_ppks',
            'foreign-satgas-unassigned-filter@university.ac.id',
            'DEMO-ST',
        );
        $reportWithoutCase = $this->makeReport();
        $caseWithoutAssignment = $this->forwardedCase($admin, [$satgas], $satgas);
        CaseAssignment::query()->where('case_id', $caseWithoutAssignment->id)->delete();
        $caseWithHistoricalAssignment = $this->forwardedCase($admin, [$satgas], $satgas);
        CaseAssignment::query()
            ->where('case_id', $caseWithHistoricalAssignment->id)
            ->update([
                'is_active' => false,
                'is_lead' => false,
                'unassigned_at' => now(),
            ]);
        $caseWithActiveAssignment = $this->forwardedCase($admin, [$satgas], $satgas);

        $otherReporter = $this->makeUser(
            'reporter',
            'foreign-reporter-unassigned-filter@university.ac.id',
            'DEMO-ST',
        );
        $this->makeReport(['reporter_id' => $otherReporter->id]);

        $this->actingAsApi($admin);
        $reportResponse = $this->getJson('/api/v1/reports?assignment_status=unassigned&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(1, 'data');
        $this->assertNotSame($caseWithActiveAssignment->report_id, $reportResponse->json('data.0.id'));

        $unassignedReportIds = $this->getJson('/api/v1/reports?assignment_status=unassigned&per_page=100')
            ->assertOk()
            ->json('data');
        $unassignedReportIds = collect($unassignedReportIds)->pluck('id')->all();
        $this->assertContains($reportWithoutCase->id, $unassignedReportIds);
        $this->assertContains($caseWithoutAssignment->report_id, $unassignedReportIds);
        $this->assertContains($caseWithHistoricalAssignment->report_id, $unassignedReportIds);
        $this->assertNotContains($caseWithActiveAssignment->report_id, $unassignedReportIds);

        $caseResponse = $this->getJson('/api/v1/cases?assignment_status=unassigned&per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $unassignedCaseIds = collect($caseResponse->json('data'))->pluck('id')->all();
        $this->assertContains($caseWithoutAssignment->id, $unassignedCaseIds);
        $this->assertContains($caseWithHistoricalAssignment->id, $unassignedCaseIds);
        $this->assertNotContains($caseWithActiveAssignment->id, $unassignedCaseIds);

        $this->getJson("/api/v1/reports?satgas_id={$satgas->id}&assignment_status=unassigned")
            ->assertUnprocessable();
        $this->getJson("/api/v1/cases?satgas_id={$satgas->id}&assignment_status=unassigned")
            ->assertUnprocessable();
        $this->getJson('/api/v1/reports?assignment_status=assigned')->assertUnprocessable();
        $this->getJson('/api/v1/cases?assignment_status=assigned')->assertUnprocessable();
        $this->getJson("/api/v1/reports?satgas_id={$foreignSatgas->id}")->assertUnprocessable();
        $this->getJson("/api/v1/cases?satgas_id={$foreignSatgas->id}")->assertUnprocessable();

        $superAdmin = $this->makeUser('super_admin', 'super-unassigned-filter@university.ac.id', null);
        $this->actingAsApi($superAdmin);
        $this->getJson('/api/v1/reports?assignment_status=unassigned')->assertUnprocessable();
        $this->getJson('/api/v1/cases?assignment_status=unassigned')->assertUnprocessable();

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/reports?assignment_status=unassigned')->assertUnprocessable();
        $this->getJson('/api/v1/cases?assignment_status=unassigned')->assertUnprocessable();

        $adminWithoutCampus = $this->makeUser(
            'admin',
            'admin-without-campus-unassigned-filter@university.ac.id',
            null,
        );
        $this->actingAsApi($adminWithoutCampus);
        $this->getJson('/api/v1/reports?assignment_status=unassigned')->assertUnprocessable();
        $this->getJson('/api/v1/cases?assignment_status=unassigned')->assertUnprocessable();
    }

    public function test_invalid_satgas_assignment_does_not_forward_report(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => [$reporter->id],
            'lead_satgas_id' => $reporter->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('cases', 0);
        $this->assertSame(ReportStatus::Submitted->value, $report->refresh()->status);
    }

    public function test_forwarding_rolls_back_case_assignments_and_report_when_audit_fails(): void
    {
        $admin = $this->makeUser('admin', 'audit-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'audit-satgas@university.ac.id');
        $report = $this->makeReport();
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => [$satgas->id],
            'lead_satgas_id' => $satgas->id,
        ])->assertServerError();

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('case_assignments', 0);
        $this->assertSame(ReportStatus::Submitted->value, $report->refresh()->status);
    }

    public function test_duplicate_and_ineligible_forwarding_are_rejected(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $report = $this->makeReport();

        $payload = ['satgas_ids' => [$satgas->id], 'lead_satgas_id' => $satgas->id];

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", $payload)
            ->assertOk();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", $payload)
            ->assertUnprocessable();

        $rejectedReport = $this->makeReport(['status' => ReportStatus::Rejected->value]);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$rejectedReport->id}/forward-to-case", $payload)
            ->assertUnprocessable();
    }

    public function test_admin_and_assigned_satgas_read_authorized_case_report_detail(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/cases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.report.chronology');

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.report.incident.chronology', $case->report->chronology);

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.report.incident.chronology', $case->report->chronology);

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertForbidden();

        $this->actingAsApi($reporter);
        $this->getJson('/api/v1/cases')
            ->assertForbidden();
    }

    public function test_case_index_supports_validated_dashboard_quick_filters(): void
    {
        $admin = $this->makeUser('admin', 'admin-filters@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-filters@university.ac.id');
        $activeCase = $this->forwardedCase($admin, [$satgas], $satgas);
        $pendingDecisionCase = $this->forwardedCase($admin, [$satgas], $satgas);
        $evidenceCase = $this->forwardedCase($admin, [$satgas], $satgas);
        $closedCase = $this->forwardedCase($admin, [$satgas], $satgas);
        $closedCase->forceFill(['closed_at' => now()])->save();

        $investigationStatus = DB::table('investigation_statuses')->value('code');
        $recommendationStatus = DB::table('recommendation_statuses')->value('code');
        $decisionStatus = DB::table('decision_statuses')->value('code');
        $evidenceType = DB::table('evidence_types')->value('code');

        $pendingInvestigationId = DB::table('investigations')->insertGetId([
            'case_id' => $pendingDecisionCase->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $recommendationId = DB::table('recommendations')->insertGetId([
            'case_id' => $pendingDecisionCase->id,
            'investigation_id' => $pendingInvestigationId,
            'author_id' => $satgas->id,
            'status_code' => $recommendationStatus,
            'conclusion' => 'encrypted-test-value',
            'recommended_actions' => 'encrypted-test-value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('decisions')->insert([
            'recommendation_id' => $recommendationId,
            'recorder_id' => $admin->id,
            'status_code' => $decisionStatus,
            'outcome_code' => 'accepted',
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'encrypted-test-value',
            'decision_content' => 'encrypted-test-value',
            'recorded_at' => now(),
            'finalized_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evidenceInvestigationId = DB::table('investigations')->insertGetId([
            'case_id' => $evidenceCase->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('evidences')->insert([
            'investigation_id' => $evidenceInvestigationId,
            'evidence_type_code' => $evidenceType,
            'submitted_by' => $satgas->id,
            'title' => 'Dashboard quick-filter evidence',
            'classification' => 'confidential',
            'status' => 'registered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsApi($admin);
        $activeResponse = $this->getJson('/api/v1/cases?quick_filter=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
        $activeIds = collect($activeResponse->json('data'))->pluck('id');
        $this->assertTrue($activeIds->contains($activeCase->id));
        $this->assertFalse($activeIds->contains($closedCase->id));

        $this->getJson('/api/v1/cases?quick_filter=pending_decision')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $pendingDecisionCase->id);

        $this->getJson('/api/v1/cases?quick_filter=with_evidence')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $evidenceCase->id);

        $this->getJson('/api/v1/cases?quick_filter=not-supported')
            ->assertUnprocessable();
    }

    public function test_reassignment_preserves_assignment_history(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgasA = $this->makeUser('satgas_ppks', 'satgas-a@university.ac.id');
        $satgasB = $this->makeUser('satgas_ppks', 'satgas-b@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgasA], $satgasA);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasB->id],
            'lead_satgas_id' => $satgasB->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignments.0.satgas_id', $satgasB->id);

        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasA->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasB->id,
            'is_active' => true,
            'is_lead' => true,
        ]);
        $this->assertSame(2, CaseAssignment::query()->where('case_id', $case->id)->count());
    }

    public function test_report_detail_includes_only_safe_active_case_assignment_context(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonMissingPath('data.0.case');

        $this->getJson("/api/v1/reports/{$case->report_id}")
            ->assertOk()
            ->assertJsonPath('data.case.id', $case->id)
            ->assertJsonPath('data.case.case_number', $case->case_number)
            ->assertJsonPath('data.case.active_assignments.0.satgas_id', $satgas->id)
            ->assertJsonPath('data.case.active_assignments.0.satgas_name', $satgas->name)
            ->assertJsonPath('data.case.active_assignments.0.is_lead', true)
            ->assertJsonPath('data.case.active_assignments.0.is_active', true)
            ->assertJsonMissingPath('data.case.report')
            ->assertJsonMissingPath('data.case.active_assignments.0.id')
            ->assertJsonMissingPath('data.case.active_assignments.0.assigned_at')
            ->assertJsonMissingPath('data.case.active_assignments.0.satgas.email')
            ->assertJsonMissingPath('data.case.active_assignments.0.assigned_by');
    }

    public function test_status_transition_uses_master_data_and_report_status_remains_forwarded(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Assessment->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Assessment->value);

        $case->refresh();
        $this->assertSame('CSTS-06', $case->status_code);
        $this->assertNotNull($case->assessment_at);
        $this->assertSame(ReportStatus::Forwarded->value, $case->report->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CaseStatusChanged->value,
            'actor_id' => $satgas->id,
            'subject_id' => $case->id,
        ]);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Closed->value,
        ])
            ->assertUnprocessable();
    }

    public function test_generic_case_status_api_cannot_bypass_controlled_workflow_transitions(): void
    {
        $admin = $this->makeUser('admin', 'admin-lifecycle@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-lifecycle@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-lifecycle@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);
        $recommendation = CaseStatus::query()->where('name', CaseStatusEnum::Recommendation->value)->firstOrFail();
        $decision = CaseStatus::query()->where('name', CaseStatusEnum::Decision->value)->firstOrFail();

        $recommendation->forceFill(['valid_transitions' => [CaseStatusEnum::Decision->value]])->save();
        $decision->forceFill(['valid_transitions' => [CaseStatusEnum::Decided->value]])->save();

        $case->forceFill([
            'status_code' => $recommendation->code,
            'current_stage' => $recommendation->workflow_stage,
        ])->save();
        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Decision->value,
        ])->assertForbidden();
        $this->assertSame(CaseStatusEnum::Recommendation->value, $case->refresh()->status->name);

        foreach ([$admin, $superAdmin] as $unauthorizedActor) {
            $this->actingAsApi($unauthorizedActor);
            $this->patchJson("/api/v1/cases/{$case->id}/status", [
                'status' => CaseStatusEnum::Decision->value,
            ])->assertForbidden();
        }

        $case->forceFill([
            'status_code' => $decision->code,
            'current_stage' => $decision->workflow_stage,
        ])->save();
        foreach ([$satgas, $admin, $superAdmin] as $unauthorizedActor) {
            $this->actingAsApi($unauthorizedActor);
            $this->patchJson("/api/v1/cases/{$case->id}/status", [
                'status' => CaseStatusEnum::Decided->value,
            ])->assertForbidden();
        }
        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
    }

    public function test_closed_case_rejects_transition_and_assignment(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);
        $closed = CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail();

        $case->forceFill([
            'status_code' => $closed->code,
            'current_stage' => $closed->workflow_stage,
            'closed_at' => now(),
        ])->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Assessment->value,
        ])
            ->assertForbidden();

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$otherSatgas->id],
            'lead_satgas_id' => $otherSatgas->id,
        ])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeReport(array $overrides = []): Report
    {
        $reporter = $this->makeUser('reporter', 'reporter-'.(Report::query()->count() + 1).'@university.ac.id');

        return Report::query()->create(array_merge([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.now()->format('Y-md').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji case foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian akses Satgas.',
            'witness_info' => 'Informasi saksi untuk pengujian akses Satgas.',
            'status' => ReportStatus::Submitted->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
        ], $overrides));
    }

    /**
     * @param  list<User>  $satgasUsers
     */
    private function forwardedCase(User $admin, array $satgasUsers, User $lead): CaseRecord
    {
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $response = $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => collect($satgasUsers)->pluck('id')->all(),
            'lead_satgas_id' => $lead->id,
        ]);

        $response->assertOk();
        $this->flushHeaders();

        return CaseRecord::query()->with('report')->findOrFail($response->json('data.case.id'));
    }

    private function makeUser(
        string $roleCode,
        string $email,
        ?string $universityCode = 'DEMO-UNIV',
    ): User {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
            'university_id' => $universityCode === null
                ? null
                : University::query()->where('code', $universityCode)->firstOrFail()->id,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
