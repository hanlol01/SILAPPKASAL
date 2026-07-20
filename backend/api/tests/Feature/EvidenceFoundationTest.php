<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\EvidenceClassification;
use App\Enums\EvidenceCustodyEventType;
use App\Enums\EvidenceStatus;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Evidence;
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

class EvidenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('oversight.cross_campus_sensitive_read', false);
        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_evidence_foundation_tables_and_metadata_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertTrue(Schema::hasTable('evidence_status_histories'));
        $this->assertTrue(Schema::hasTable('evidence_custody_events'));
        $this->assertTrue(Schema::hasColumn('evidences', 'investigation_id'));
        $this->assertFalse(Schema::hasColumn('evidences', 'case_id'));
        $this->assertTrue(Schema::hasColumn('evidences', 'original_filename'));
        $this->assertTrue(Schema::hasColumn('evidences', 'mime_type'));
        $this->assertTrue(Schema::hasColumn('evidences', 'file_size'));
        $this->assertTrue(Schema::hasColumn('evidences', 'checksum_sha256'));
        $this->assertTrue(Schema::hasColumn('evidences', 'storage_disk'));
        $this->assertTrue(Schema::hasColumn('evidences', 'storage_path'));
        $this->assertTrue(Schema::hasColumn('evidences', 'file_uploaded_by'));
        $this->assertTrue(Schema::hasColumn('evidences', 'file_uploaded_at'));
        $this->assertFalse(Schema::hasColumn('evidence_custody_events', 'deleted_at'));
    }

    public function test_assigned_satgas_can_create_evidence_metadata_for_assigned_investigation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", $this->evidencePayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', EvidenceStatus::Registered->value)
            ->assertJsonPath('data.classification', EvidenceClassification::Confidential->value)
            ->assertJsonPath('data.file_metadata.original_filename', null)
            ->assertJsonPath('data.file_attachment', null)
            ->assertJsonMissingPath('data.investigation.findings')
            ->assertJsonMissingPath('data.report.chronology')
            ->assertJsonMissingPath('data.file_content');

        $this->assertDatabaseHas('evidences', [
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-01',
            'submitted_by' => $satgas->id,
            'status' => EvidenceStatus::Registered->value,
        ]);
        $this->assertDatabaseCount('evidence_status_histories', 1);
        $this->assertDatabaseHas('evidence_custody_events', [
            'event_type' => EvidenceCustodyEventType::Registered->value,
        ]);
    }

    public function test_evidence_list_uses_id_as_a_deterministic_timestamp_tie_breaker(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);

        $this->actingAsApi($satgas);
        $firstEvidenceId = $this->postJson(
            "/api/v1/investigations/{$investigation->id}/evidences",
            $this->evidencePayload(['title' => 'Bukti pertama']),
        )->assertCreated()->json('data.id');
        $secondEvidenceId = $this->postJson(
            "/api/v1/investigations/{$investigation->id}/evidences",
            $this->evidencePayload(['title' => 'Bukti kedua']),
        )->assertCreated()->json('data.id');

        Evidence::query()
            ->whereIn('id', [$firstEvidenceId, $secondEvidenceId])
            ->update(['created_at' => now()->startOfSecond()]);

        $this->getJson("/api/v1/investigations/{$investigation->id}/evidences")
            ->assertOk()
            ->assertJsonPath('data.0.id', $secondEvidenceId)
            ->assertJsonPath('data.1.id', $firstEvidenceId);
    }

    public function test_admin_super_admin_reporter_and_unassigned_satgas_have_no_default_evidence_access(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);
        $evidence = $this->makeEvidence($investigation, $satgas);

        foreach ([$admin, $superAdmin, $otherSatgas, $reporter] as $user) {
            $this->actingAsApi($user);
            $this->getJson("/api/v1/evidences/{$evidence->id}")
                ->assertForbidden();
        }
    }

    public function test_evidence_metadata_endpoints_reject_forged_file_and_storage_fields(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", $this->evidencePayload([
            'file' => 'fake-upload-content',
            'original_filename' => 'forged.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'storage_disk' => 'evidence',
            'storage_path' => 'private/evidence/file.png',
            'file_uploaded_by' => $satgas->id,
            'file_uploaded_at' => now()->toJSON(),
        ]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('evidences', 0);

        $evidence = $this->makeEvidence($investigation, $satgas);

        $this->patchJson("/api/v1/evidences/{$evidence->id}", [
            'original_filename' => 'forged.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('b', 64),
            'storage_disk' => 'evidence',
            'storage_path' => 'cases/1/evidences/1/forged.pdf',
            'file_uploaded_by' => $satgas->id,
            'file_uploaded_at' => now()->toJSON(),
        ])->assertUnprocessable();
    }

    public function test_assigned_satgas_can_update_metadata_and_custody_is_append_only(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);
        $evidence = $this->makeEvidence($investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/evidences/{$evidence->id}", [
            'title' => 'Metadata evidence diperbarui',
            'classification' => EvidenceClassification::Restricted->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Metadata evidence diperbarui')
            ->assertJsonPath('data.classification', EvidenceClassification::Restricted->value);

        $this->getJson("/api/v1/evidences/{$evidence->id}/custody")
            ->assertOk()
            ->assertJsonPath('data.0.event_type', EvidenceCustodyEventType::Registered->value)
            ->assertJsonPath('data.1.event_type', EvidenceCustodyEventType::MetadataUpdated->value);

        $this->assertDatabaseCount('evidence_custody_events', 2);
    }

    public function test_status_lifecycle_uses_constants_and_verified_is_metadata_review_only(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);
        $evidence = $this->makeEvidence($investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/evidences/{$evidence->id}/status", [
            'status' => EvidenceStatus::UnderReview->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EvidenceStatus::UnderReview->value);

        $this->patchJson("/api/v1/evidences/{$evidence->id}/status", [
            'status' => EvidenceStatus::Verified->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EvidenceStatus::Verified->value)
            ->assertJsonPath('data.status_semantics', 'metadata_reviewed_admin_complete_not_forensic_authenticity');

        $this->assertDatabaseHas('evidence_custody_events', [
            'event_type' => EvidenceCustodyEventType::Reviewed->value,
        ]);

        $this->patchJson("/api/v1/evidences/{$evidence->id}/status", [
            'status' => EvidenceStatus::Archived->value,
        ])->assertOk();

        $this->patchJson("/api/v1/evidences/{$evidence->id}/status", [
            'status' => EvidenceStatus::Registered->value,
        ])->assertUnprocessable();

        $this->assertSame(CaseStatusEnum::Investigation->value, $investigation->case->refresh()->status->name);
    }

    private function evidencePayload(array $overrides = []): array
    {
        return array_merge([
            'evidence_type_code' => 'EVID-01',
            'title' => 'Screenshot percakapan',
            'description' => 'Metadata bukti berupa tangkapan layar percakapan.',
            'source' => 'Diserahkan kepada Satgas saat investigasi.',
            'collected_at' => now()->toJSON(),
            'classification' => EvidenceClassification::Confidential->value,
        ], $overrides);
    }

    private function makeEvidence(Investigation $investigation, User $satgas): Evidence
    {
        $evidence = Evidence::query()->create([
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-01',
            'submitted_by' => $satgas->id,
            'title' => 'Screenshot percakapan',
            'description' => 'Metadata bukti berupa tangkapan layar percakapan.',
            'source' => 'Diserahkan kepada Satgas saat investigasi.',
            'collected_at' => now(),
            'classification' => EvidenceClassification::Confidential->value,
            'status' => EvidenceStatus::Registered->value,
            'original_filename' => 'screenshot-chat.png',
            'mime_type' => 'image/png',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $evidence->statusHistories()->create([
            'from_status' => null,
            'to_status' => EvidenceStatus::Registered->value,
            'changed_by' => $satgas->id,
            'changed_at' => now(),
        ]);

        $evidence->custodyEvents()->create([
            'actor_id' => $satgas->id,
            'event_type' => EvidenceCustodyEventType::Registered->value,
            'event_at' => now(),
            'details' => ['classification' => EvidenceClassification::Confidential->value],
        ]);

        return $evidence;
    }

    private function makeInvestigation(User $admin, User $satgas): Investigation
    {
        $case = $this->makeInvestigationCase($admin, $satgas);
        $status = InvestigationStatus::query()->where('name', 'planning')->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Rencana investigasi rahasia.',
            'started_at' => now(),
        ]);
    }

    private function makeInvestigationCase(User $admin, User $satgas): CaseRecord
    {
        $report = $this->makeReport();
        $status = CaseStatus::query()->where('name', CaseStatusEnum::Investigation->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'investigation_started_at' => now(),
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
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji evidence foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian evidence.',
            'witness_info' => 'Informasi saksi untuk pengujian evidence.',
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
