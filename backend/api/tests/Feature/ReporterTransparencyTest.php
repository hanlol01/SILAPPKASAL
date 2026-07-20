<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\EvidenceClassification;
use App\Enums\EvidenceStatus;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Evidence;
use App\Models\Faculty;
use App\Models\Investigation;
use App\Models\InvestigationActivity;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Recovery;
use App\Models\RecoveryMonitoring;
use App\Models\RecoveryStatus;
use App\Models\Report;
use App\Models\ReportEvidenceSubmission;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporterTransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_owned_report_detail_contains_all_submitted_fields_and_current_account_data(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test', 'DEMO-UNIV', [
            'name' => 'Demo Pelapor',
            'nim' => 'DEMO-2026-001',
            'phone_number' => '081234567890',
            'faculty_id' => Faculty::query()->where('code', 'FT')->whereHas(
                'university',
                fn ($query) => $query->where('code', 'DEMO-UNIV'),
            )->value('id'),
            'study_program_id' => StudyProgram::query()->where('code', 'TI')->whereHas(
                'university',
                fn ($query) => $query->where('code', 'DEMO-UNIV'),
            )->value('id'),
        ]);
        $report = $this->makeReport($reporter, 'SLP-R1-DETAIL', 'confidential');
        $other = $this->makeUser('reporter', 'other@example.test');

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.category.code', 'RCAT-01')
            ->assertJsonPath('data.submitted_details.identification.report_type', 'confidential')
            ->assertJsonPath('data.submitted_details.identification.category.code', 'RCAT-01')
            ->assertJsonPath('data.submitted_details.incident.chronology', 'Kronologi R1 yang dimiliki Pelapor.')
            ->assertJsonPath('data.submitted_details.incident.incident_date', '2026-07-01')
            ->assertJsonPath('data.submitted_details.incident.incident_time', '09:30')
            ->assertJsonPath('data.submitted_details.incident.incident_location', 'Lokasi Demo')
            ->assertJsonPath('data.submitted_details.incident.location_type.code', 'LOC-01')
            ->assertJsonPath('data.submitted_details.respondent.name', 'Pihak Terlapor A')
            ->assertJsonPath('data.submitted_details.respondent.campus_status.code', 'CAMP-01')
            ->assertJsonPath('data.submitted_details.respondent.relation.code', 'REL-01')
            ->assertJsonPath('data.submitted_details.respondent.details', 'Detail terlapor demo.')
            ->assertJsonPath('data.submitted_details.respondent.witness_information', 'Informasi saksi demo.')
            ->assertJsonPath('data.submitted_details.respondent.confidential_reporter_contact', '081111111111')
            ->assertJsonPath('data.submitted_details.reporter_account.source', 'current_account')
            ->assertJsonPath('data.submitted_details.reporter_account.masked', false)
            ->assertJsonPath('data.submitted_details.reporter_account.name', 'Demo Pelapor')
            ->assertJsonPath('data.submitted_details.reporter_account.nim', 'DEMO-2026-001')
            ->assertJsonPath('data.submitted_details.reporter_account.email', 'owner@example.test')
            ->assertJsonPath('data.submitted_details.reporter_account.phone_number', '081234567890')
            ->assertJsonPath('data.submitted_details.reporter_account.faculty.name', 'Fakultas Teknik')
            ->assertJsonPath('data.submitted_details.reporter_account.study_program.name', 'Teknik Informatika')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.tracking_code')
            ->assertJsonMissingPath('data.admin_notes');

        Sanctum::actingAs($other, ['*']);
        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")->assertNotFound();
    }

    public function test_owner_can_view_their_own_anonymous_submitted_data_and_current_identity(): void
    {
        $reporter = $this->makeUser('reporter', 'anonymous-owner@example.test', 'DEMO-UNIV', [
            'name' => 'Anonymous Owner Account',
        ]);
        $report = $this->makeReport($reporter, 'SLP-R1-ANON', 'anonymous');

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.report_type', 'anonymous')
            ->assertJsonPath('data.submitted_details.incident.chronology', 'Kronologi R1 yang dimiliki Pelapor.')
            ->assertJsonPath('data.submitted_details.reporter_account.masked', false)
            ->assertJsonPath('data.submitted_details.reporter_account.name', 'Anonymous Owner Account')
            ->assertJsonPath('data.submitted_details.respondent.confidential_reporter_contact', null);
    }

    public function test_legacy_anonymous_report_without_reporter_id_is_not_available_in_reporter_routes(): void
    {
        $reporter = $this->makeUser('reporter', 'legacy-anonymous@example.test');
        $report = $this->makeReport($reporter, 'SLP-R1-LEGACY-ANON', 'anonymous', [
            'reporter_id' => null,
        ]);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")->assertNotFound();
        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/handling-progress")
            ->assertNotFound();
    }

    public function test_handling_progress_is_owned_aggregate_only_and_excludes_sensitive_content(): void
    {
        $reporter = $this->makeUser('reporter', 'progress-owner@example.test');
        $other = $this->makeUser('reporter', 'progress-other@example.test');
        $admin = $this->makeUser('admin', 'progress-admin@example.test', 'DEMO-UNIV', ['name' => 'Hidden Admin']);
        $satgas = $this->makeUser('satgas_ppks', 'progress-satgas@example.test', 'DEMO-UNIV', ['name' => 'Hidden Satgas']);
        $report = $this->makeReport($reporter, 'SLP-R1-PROGRESS', 'confidential');
        $case = $this->makeCase($report, CaseStatusEnum::Closed, 'PRIO-03');
        $case->assignments()->create([
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now()->subDays(8),
        ]);
        $investigationStatus = InvestigationStatus::query()
            ->where('name', InvestigationStatusEnum::Completed->value)
            ->firstOrFail();
        $investigation = Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus->code,
            'plan_summary' => 'Hidden investigation plan',
            'findings' => 'Hidden investigation findings',
            'conclusion' => 'Hidden investigation conclusion',
            'started_at' => now()->subDays(7),
            'completed_at' => now()->subDays(4),
        ]);
        InvestigationActivity::query()->create([
            'investigation_id' => $investigation->id,
            'investigator_id' => $satgas->id,
            'activity_type' => 'report_drafting',
            'investigation_stage_code' => $investigationStatus->code,
            'activity_date' => now()->subDays(4)->toDateString(),
            'description' => 'Hidden activity narrative',
            'findings' => 'Hidden activity finding',
            'notes' => 'Hidden activity note',
        ]);
        Evidence::query()->create([
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-01',
            'submitted_by' => $satgas->id,
            'title' => 'Hidden evidence title',
            'description' => 'Hidden evidence description',
            'source' => 'Hidden evidence source',
            'classification' => EvidenceClassification::Confidential->value,
            'status' => EvidenceStatus::Registered->value,
            'original_filename' => 'internal-secret.pdf',
        ]);
        ReportEvidenceSubmission::query()->create([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'report_id' => $report->id,
            'uploaded_by' => $reporter->id,
            'original_filename' => 'owner-supporting-file.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 123,
            'checksum_sha256' => str_repeat('a', 64),
            'storage_disk' => 'evidence',
            'storage_path' => 'hidden/path.pdf',
            'uploaded_at' => now()->subDays(9),
        ]);
        $recommendation = Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => RecommendationStatus::query()
                ->where('name', RecommendationStatusEnum::Accepted->value)
                ->value('code'),
            'conclusion' => 'Hidden recommendation conclusion',
            'recommended_actions' => 'Hidden recommendation draft content',
            'submitted_at' => now()->subDays(3),
            'approved_by' => $admin->id,
            'approved_at' => now()->subDays(2),
        ]);
        $decision = Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => DecisionStatus::query()
                ->where('name', DecisionStatusEnum::Finalized->value)
                ->value('code'),
            'outcome_code' => 'accepted',
            'decision_number' => 'PUT-R1-001',
            'decision_date' => now()->subDays(2)->toDateString(),
            'decision_summary' => 'Hidden decision summary',
            'decision_content' => 'Hidden decision narrative',
            'recorded_at' => now()->subDays(2),
            'finalized_at' => now()->subDay(),
        ]);
        $recovery = Recovery::query()->create([
            'decision_id' => $decision->id,
            'recovery_type_code' => 'RCV-01',
            'status_code' => RecoveryStatus::query()
                ->where('name', RecoveryStatusEnum::Completed->value)
                ->value('code'),
            'created_by' => $admin->id,
            'recovery_plan' => 'Hidden recovery plan',
            'support_needs' => 'Hidden support needs',
            'notes' => 'Hidden recovery notes',
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
        RecoveryMonitoring::query()->create([
            'recovery_id' => $recovery->id,
            'monitor_id' => $satgas->id,
            'monitoring_date' => now()->toDateString(),
            'condition_summary' => 'Hidden monitoring summary',
            'follow_up_plan' => 'Hidden follow up',
            'notes' => 'Hidden monitoring notes',
        ]);

        Sanctum::actingAs($reporter, ['*']);
        $response = $this->getJson("/api/v1/portal/reports/{$report->registration_number}/handling-progress")
            ->assertOk()
            ->assertJsonPath('data.case.available', true)
            ->assertJsonPath('data.case.state', 'completed')
            ->assertJsonPath('data.investigation.state', 'completed')
            ->assertJsonPath('data.investigation.activity_count', 1)
            ->assertJsonPath('data.recommendation.state', 'completed')
            ->assertJsonPath('data.decision.state', 'completed')
            ->assertJsonPath('data.recovery.state', 'completed')
            ->assertJsonPath('data.monitoring.count', 1)
            ->assertJsonPath('data.evidence.reporter_supporting_file_count', 1)
            ->assertJsonPath('data.evidence.internal_evidence_count', 1)
            ->assertJsonPath('data.final_summary', null);

        $this->assertNoForbiddenProgressKeys($response->json('data'));
        foreach ([
            'Hidden Admin',
            'Hidden Satgas',
            'Hidden investigation findings',
            'Hidden activity narrative',
            'Hidden activity finding',
            'Hidden activity note',
            'Hidden recommendation draft content',
            'Hidden decision narrative',
            'Hidden recovery plan',
            'Hidden monitoring summary',
            'internal-secret.pdf',
            'owner-supporting-file.pdf',
            'hidden/path.pdf',
            '081111111111',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }

        $this->getJson("/api/v1/portal/reports/{$report->id}/handling-progress")
            ->assertNotFound();

        Sanctum::actingAs($other, ['*']);
        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/handling-progress")
            ->assertNotFound();
    }

    public function test_internal_report_and_case_details_enforce_campus_assignment_and_anonymous_masking(): void
    {
        $reporter = $this->makeUser('reporter', 'internal-owner@example.test');
        $admin = $this->makeUser('admin', 'same-campus-admin@example.test');
        $otherAdmin = $this->makeUser('admin', 'other-campus-admin@example.test', 'DEMO-ST');
        $assignedSatgas = $this->makeUser('satgas_ppks', 'assigned@example.test');
        $unassignedSatgas = $this->makeUser('satgas_ppks', 'unassigned@example.test');
        $superAdmin = $this->makeUser('super_admin', 'oversight@example.test', null);
        $report = $this->makeReport($reporter, 'SLP-R1-INTERNAL', 'open');
        $case = $this->makeCase($report, CaseStatusEnum::Investigation, 'PRIO-03');
        $case->assignments()->create([
            'satgas_id' => $assignedSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);
        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.submitted_details.incident.chronology', 'Kronologi R1 yang dimiliki Pelapor.')
            ->assertJsonPath('data.submitted_details.reporter_account.email', 'internal-owner@example.test');
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.report.incident.chronology', 'Kronologi R1 yang dimiliki Pelapor.')
            ->assertJsonPath('data.report.reporter_account.email', 'internal-owner@example.test');

        Sanctum::actingAs($assignedSatgas, ['*']);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.report.respondent.name', 'Pihak Terlapor A');

        Sanctum::actingAs($unassignedSatgas, ['*']);
        $this->getJson("/api/v1/cases/{$case->id}")->assertForbidden();

        Sanctum::actingAs($otherAdmin, ['*']);
        $this->getJson("/api/v1/reports/{$report->id}")->assertForbidden();
        $this->getJson("/api/v1/cases/{$case->id}")->assertForbidden();

        config()->set('oversight.cross_campus_sensitive_read', false);
        Sanctum::actingAs($superAdmin, ['*']);
        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.submitted_details');
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.report');

        $anonymous = $this->makeReport($reporter, 'SLP-R1-INTERNAL-ANON', 'anonymous');
        $anonymousCase = $this->makeCase($anonymous, CaseStatusEnum::Investigation, null);
        config()->set('oversight.cross_campus_sensitive_read', true);
        $this->getJson("/api/v1/reports/{$anonymous->id}")
            ->assertOk()
            ->assertJsonPath('data.submitted_details.reporter_account.masked', true)
            ->assertJsonMissingPath('data.submitted_details.reporter_account.name')
            ->assertJsonMissingPath('data.submitted_details.reporter_account.email');
        $this->getJson("/api/v1/cases/{$anonymousCase->id}")
            ->assertOk()
            ->assertJsonPath('data.report.reporter_account.masked', true)
            ->assertJsonMissingPath('data.report.reporter_account.name');
    }

    public function test_report_priority_projection_and_analytics_derive_only_from_case(): void
    {
        $admin = $this->makeUser('admin', 'priority-admin@example.test');
        $reporter = $this->makeUser('reporter', 'priority-owner@example.test');
        $noCase = $this->makeReport($reporter, 'SLP-R1-PRIORITY-1', 'open', ['priority' => 'PRIO-01']);
        $unassessed = $this->makeReport($reporter, 'SLP-R1-PRIORITY-2', 'open', ['priority' => 'PRIO-01']);
        $assessed = $this->makeReport($reporter, 'SLP-R1-PRIORITY-3', 'open', ['priority' => 'PRIO-01']);
        $this->makeCase($unassessed, CaseStatusEnum::Assessment, null);
        $this->makeCase($assessed, CaseStatusEnum::Assessment, 'PRIO-03');

        Sanctum::actingAs($admin, ['*']);
        $response = $this->getJson('/api/v1/reports?report_type=open&per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
        $this->assertCount(2, $response->json('data'));

        $allReports = $this->getJson('/api/v1/reports?report_type=open&per_page=15')
            ->assertOk()
            ->json('data');
        $byRegistration = collect($allReports)->keyBy('registration_number');
        $this->assertSame('unavailable', $byRegistration[$noCase->registration_number]['priority']['availability']);
        $this->assertNull($byRegistration[$noCase->registration_number]['priority']['level']);
        $this->assertSame('unassessed', $byRegistration[$unassessed->registration_number]['priority']['availability']);
        $this->assertNull($byRegistration[$unassessed->registration_number]['priority']['level']);
        $this->assertSame('assessed', $byRegistration[$assessed->registration_number]['priority']['availability']);
        $this->assertSame('PRIO-03', $byRegistration[$assessed->registration_number]['priority']['level']['code']);

        $analytics = $this->getJson('/api/v1/dashboard/reports')
            ->assertOk()
            ->assertJsonFragment(['key' => 'PRIO-03', 'count' => 1]);
        $this->assertStringNotContainsString('"key":"PRIO-01"', $analytics->getContent());
    }

    /** @param array<string, mixed> $node */
    private function assertNoForbiddenProgressKeys(array $node): void
    {
        $forbidden = [
            'id',
            'case_id',
            'investigation_id',
            'recommendation_id',
            'decision_id',
            'recovery_id',
            'assignments',
            'staff',
            'findings',
            'notes',
            'filename',
            'original_filename',
            'storage_path',
            'chain_of_custody',
        ];

        foreach ($node as $key => $value) {
            if (is_string($key)) {
                $this->assertNotContains($key, $forbidden);
            }

            if (is_array($value)) {
                $this->assertNoForbiddenProgressKeys($value);
            }
        }
    }

    /** @param array<string, mixed> $overrides */
    private function makeUser(
        string $roleCode,
        string $email,
        ?string $universityCode = 'DEMO-UNIV',
        array $overrides = [],
    ): User {
        return User::query()->create(array_merge([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'university_id' => $universityCode === null
                ? null
                : University::query()->where('code', $universityCode)->value('id'),
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function makeReport(
        User $reporter,
        string $registrationNumber,
        string $reportType,
        array $overrides = [],
    ): Report {
        return Report::query()->create(array_merge([
            'reporter_id' => $reporter->id,
            'registration_number' => $registrationNumber,
            'tracking_code' => $reportType === 'anonymous' ? 'R1AA-R1BB-R1CC-R1DD' : null,
            'report_type' => $reportType,
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi R1 yang dimiliki Pelapor.',
            'incident_date' => '2026-07-01',
            'incident_time' => '09:30',
            'incident_location' => 'Lokasi Demo',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Pihak Terlapor A',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-01',
            'respondent_details' => 'Detail terlapor demo.',
            'witness_info' => 'Informasi saksi demo.',
            'reporter_phone_encrypted' => $reportType === 'confidential' ? '081111111111' : null,
            'status' => ReportStatus::Submitted->value,
            'priority' => null,
            'submitted_at' => now()->subDays(10),
        ], $overrides));
    }

    private function makeCase(
        Report $report,
        CaseStatusEnum $statusName,
        ?string $priorityCode,
    ): CaseRecord {
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.$report->registration_number,
            'status_code' => $status->code,
            'priority_code' => $priorityCode,
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now()->subDays(9),
            'closed_at' => $statusName === CaseStatusEnum::Closed ? now() : null,
        ]);
    }
}
