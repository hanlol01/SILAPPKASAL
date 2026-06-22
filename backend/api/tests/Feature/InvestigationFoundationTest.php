<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestigationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_investigation_foundation_tables_and_transition_metadata_exist(): void
    {
        $this->assertTrue(Schema::hasTable('investigations'));
        $this->assertTrue(Schema::hasTable('investigation_activities'));
        $this->assertTrue(Schema::hasColumn('investigation_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertDatabaseCount('evidences', 0);
        $this->assertTrue(Schema::hasTable('recommendations'));
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertDatabaseCount('decisions', 0);

        $planning = InvestigationStatus::query()
            ->where('name', InvestigationStatusEnum::Planning->value)
            ->firstOrFail();

        $this->assertContains(InvestigationStatusEnum::ReportDrafting->value, $planning->valid_transitions);
    }

    public function test_assigned_satgas_can_create_investigation_only_for_case_in_investigation_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'lead_investigator_id' => $satgas->id,
            'plan_summary' => 'Rencana investigasi awal yang hanya boleh dibaca Satgas assigned.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', InvestigationStatusEnum::Planning->value)
            ->assertJsonPath('data.lead_investigator.id', $satgas->id)
            ->assertJsonPath('data.plan_summary', 'Rencana investigasi awal yang hanya boleh dibaca Satgas assigned.');

        $this->assertDatabaseHas('investigations', [
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => 'INVS-01',
        ]);

        $otherCase = $this->makeForwardedCase($admin, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$otherCase->id}/investigations", [
            'lead_investigator_id' => $satgas->id,
            'plan_summary' => 'Rencana investigasi awal yang valid tetapi kasus belum berada pada status investigation.',
        ])
            ->assertUnprocessable();
    }

    public function test_admin_and_unassigned_satgas_cannot_create_investigation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'lead_investigator_id' => $satgas->id,
            'plan_summary' => 'Rencana investigasi awal yang valid untuk memastikan admin tetap ditolak.',
        ])
            ->assertForbidden();

        $this->actingAsApi($otherSatgas);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'lead_investigator_id' => $otherSatgas->id,
            'plan_summary' => 'Rencana investigasi awal yang valid untuk memastikan Satgas tidak assigned tetap ditolak.',
        ])
            ->assertForbidden();

        $this->assertDatabaseCount('investigations', 0);
    }

    public function test_admin_reads_metadata_only_while_assigned_satgas_reads_sensitive_detail(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);
        $investigation = $this->makeInvestigation($case, $satgas);

        $investigation->activities()->create([
            'investigator_id' => $satgas->id,
            'activity_type' => 'document_review',
            'activity_date' => now()->toDateString(),
            'description' => 'Review dokumen awal yang sensitif.',
        ]);

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/investigations/{$investigation->id}")
            ->assertOk()
            ->assertJsonPath('data.activity_count', 1)
            ->assertJsonMissingPath('data.plan_summary')
            ->assertJsonMissingPath('data.activities.0.description');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/investigations/{$investigation->id}")
            ->assertOk()
            ->assertJsonPath('data.plan_summary', 'Plan rahasia untuk Satgas.')
            ->assertJsonPath('data.activities.0.description', 'Review dokumen awal yang sensitif.');
    }

    public function test_activity_records_use_flexible_application_validation_and_authenticated_investigator(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);
        $investigation = $this->makeInvestigation($case, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            'activity_type' => 'document_review',
            'activity_date' => now()->toDateString(),
            'description' => 'Satgas melakukan review dokumen tanpa upload file.',
            'findings' => 'Temuan awal dari dokumen.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.activity_type', 'document_review')
            ->assertJsonPath('data.investigator.id', $satgas->id);

        $this->assertDatabaseHas('investigation_activities', [
            'investigation_id' => $investigation->id,
            'investigator_id' => $satgas->id,
            'activity_type' => 'document_review',
        ]);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            'activity_type' => 'evidence_upload',
            'activity_date' => now()->toDateString(),
            'description' => 'Invalid karena upload evidence belum masuk scope.',
        ])
            ->assertUnprocessable();
    }

    public function test_investigation_status_transition_uses_master_data_and_completed_is_terminal(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);
        $investigation = $this->makeInvestigation($case, $satgas);

        InvestigationStatus::query()
            ->where('name', InvestigationStatusEnum::Planning->value)
            ->firstOrFail()
            ->forceFill(['valid_transitions' => [InvestigationStatusEnum::Completed->value]])
            ->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", [
            'status' => InvestigationStatusEnum::Completed->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', InvestigationStatusEnum::Completed->value);

        $investigation->refresh();
        $this->assertNotNull($investigation->completed_at);
        $this->assertSame(CaseStatusEnum::Investigation->value, $case->refresh()->status->name);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", [
            'status' => InvestigationStatusEnum::ReportDrafting->value,
        ])
            ->assertUnprocessable();

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            'activity_type' => 'case_review',
            'activity_date' => now()->toDateString(),
            'description' => 'Tidak boleh masuk setelah completed.',
        ])
            ->assertUnprocessable();
    }

    public function test_investigation_status_options_follow_view_policy_and_master_data_transitions(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);
        $investigation = $this->makeInvestigation($case, $satgas);

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/investigations/{$investigation->id}/status-options")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_status.code', 'INVS-01')
            ->assertJsonPath('data.current_status.name', InvestigationStatusEnum::Planning->value)
            ->assertJsonFragment([
                'code' => 'INVS-02',
                'name' => InvestigationStatusEnum::EvidenceCollection->value,
            ])
            ->assertJsonMissing([
                'name' => InvestigationStatusEnum::Completed->value,
            ]);

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/investigations/{$investigation->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', InvestigationStatusEnum::Planning->value);

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/investigations/{$investigation->id}/status-options")
            ->assertForbidden();
    }

    public function test_investigation_creation_requires_plan_summary_minimum_length(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'lead_investigator_id' => $satgas->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_summary');

        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'lead_investigator_id' => $satgas->id,
            'plan_summary' => 'Terlalu pendek.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_summary');
    }

    public function test_investigation_actions_dispatch_audit_logs_and_notifications(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $otherSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAsApi($satgas);
        $investigationId = $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'lead_investigator_id' => $satgas->id,
            'plan_summary' => 'Rencana investigasi awal yang cukup panjang untuk memenuhi validasi minimum M27.',
        ])
            ->assertCreated()
            ->json('data.id');

        $investigation = Investigation::query()->findOrFail($investigationId);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::InvestigationCreated->value,
            'category' => AuditCategory::Investigation->value,
            'subject_type' => $investigation->getMorphClass(),
            'subject_id' => $investigation->id,
        ]);

        $this->assertSame(1, $admin->notifications()->where('data->notification_type_code', 'investigation_created')->count());
        $this->assertSame(1, $superAdmin->notifications()->where('data->notification_type_code', 'investigation_created')->count());
        $this->assertSame(0, $satgas->notifications()->where('data->notification_type_code', 'investigation_created')->count());

        $activityId = $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            'activity_type' => 'document_review',
            'activity_date' => now()->toDateString(),
            'description' => 'Satgas melakukan review dokumen tanpa upload file.',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::InvestigationActivityCreated->value,
            'category' => AuditCategory::Investigation->value,
            'subject_id' => $activityId,
        ]);

        InvestigationStatus::query()
            ->where('name', InvestigationStatusEnum::Planning->value)
            ->firstOrFail()
            ->forceFill(['valid_transitions' => [InvestigationStatusEnum::Completed->value]])
            ->save();

        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", [
            'status' => InvestigationStatusEnum::Completed->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', InvestigationStatusEnum::Completed->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::InvestigationStatusChanged->value,
            'category' => AuditCategory::Investigation->value,
            'subject_type' => $investigation->getMorphClass(),
            'subject_id' => $investigation->id,
        ]);

        $this->assertSame(1, $satgas->notifications()->where('data->notification_type_code', 'investigation_status_changed')->count());
        $this->assertSame(1, $otherSatgas->notifications()->where('data->notification_type_code', 'investigation_status_changed')->count());
        $this->assertSame(0, $admin->notifications()->where('data->notification_type_code', 'investigation_status_changed')->count());
        $this->assertSame(0, $superAdmin->notifications()->where('data->notification_type_code', 'investigation_status_changed')->count());
        $this->assertSame(1, $admin->notifications()->where('data->notification_type_code', 'investigation_completed')->count());
        $this->assertSame(1, $superAdmin->notifications()->where('data->notification_type_code', 'investigation_completed')->count());

        $payload = $satgas->notifications()->where('data->notification_type_code', 'investigation_status_changed')->firstOrFail()->data;
        $this->assertSame($case->case_number, $payload['case_number']);
        $this->assertSame(InvestigationStatusEnum::Planning->value, $payload['from_status']);
        $this->assertSame(InvestigationStatusEnum::Completed->value, $payload['to_status']);
        $this->assertArrayNotHasKey('plan_summary', $payload);
        $this->assertArrayNotHasKey('findings', $payload);
        $this->assertArrayNotHasKey('description', $payload);

        $json = AuditLog::query()
            ->whereIn('action', [
                AuditAction::InvestigationCreated->value,
                AuditAction::InvestigationActivityCreated->value,
                AuditAction::InvestigationStatusChanged->value,
            ])
            ->get()
            ->toJson();

        $this->assertStringNotContainsString('Rencana investigasi awal', $json);
        $this->assertStringNotContainsString('Satgas melakukan review dokumen', $json);
    }

    private function makeInvestigation(CaseRecord $case, User $satgas): Investigation
    {
        $status = InvestigationStatus::query()
            ->where('name', InvestigationStatusEnum::Planning->value)
            ->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Plan rahasia untuk Satgas.',
            'started_at' => now(),
        ]);
    }

    private function makeInvestigationCase(User $admin, User $satgas): CaseRecord
    {
        $case = $this->makeForwardedCase($admin, $satgas);
        $status = CaseStatus::query()->where('name', CaseStatusEnum::Investigation->value)->firstOrFail();

        $case->forceFill([
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'investigation_started_at' => now(),
        ])->save();

        return $case->refresh();
    }

    private function makeForwardedCase(User $admin, User $satgas): CaseRecord
    {
        $report = $this->makeReport();
        $status = CaseStatus::query()->where('name', CaseStatusEnum::Forwarded->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
        ]);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return $case->load('status');
    }

    private function makeReport(): Report
    {
        return Report::query()->create([
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji investigation foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian investigation.',
            'witness_info' => 'Informasi saksi untuk pengujian investigation.',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);
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

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
