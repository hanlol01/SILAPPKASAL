<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\Foundation\RbacSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaseClosureDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
        Storage::fake('case_documents');
    }

    public function test_same_campus_admin_issues_private_document_and_reporter_can_download_only_own_document(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->closedCase();
        Sanctum::actingAs($admin, ['*']);
        $issued = $this->postJson("/api/v1/cases/{$case->id}/closure-document")
            ->assertCreated()
            ->assertJsonPath('data.document_number', 'BAHPKS/'.now('Asia/Jakarta')->format('Y').'/'.$case->case_number);

        $document = $issued->json('data');
        $this->assertDatabaseHas('case_closure_documents', [
            'case_id' => $case->id,
            'public_id' => $document['public_id'],
            'signer_id' => $satgas->id,
            'signer_name' => 'Satgas Penandatangan',
            'signer_identity_number' => '198001012026041001',
        ]);
        $satgas->forceFill(['name' => 'Nama Satgas Diperbarui', 'nip' => 'NIP-BARU'])->save();
        $this->assertDatabaseHas('case_closure_documents', [
            'case_id' => $case->id,
            'signer_name' => 'Satgas Penandatangan',
            'signer_identity_number' => '198001012026041001',
        ]);
        Storage::disk('case_documents')->assertExists(CaseRecord::query()->findOrFail($case->id)->closureDocument->storage_path);
        $issueAudit = AuditLog::query()->where('action', 'case_closure_document.issued')->firstOrFail();
        $auditMetadata = json_encode($issueAudit->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('storage_path', $auditMetadata);
        $this->assertStringNotContainsString('checksum', $auditMetadata);

        Sanctum::actingAs($reporter, ['*']);
        $download = $this->get("/api/v1/portal/reports/{$case->registration_number}/closure-document/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));

        $this->getJson("/api/v1/portal/reports/{$case->registration_number}/handling-progress")
            ->assertOk()
            ->assertJsonPath('data.closure_document.available', true)
            ->assertJsonPath('data.closure_document.document_number', $document['document_number']);

        $otherReporter = $this->user('reporter', 'other-reporter@example.test', $reporter->university);
        Sanctum::actingAs($otherReporter, ['*']);
        $this->getJson("/api/v1/portal/reports/{$case->registration_number}/closure-document/download")->assertNotFound();

        Sanctum::actingAs($satgas, ['*']);
        $this->get("/api/v1/case-closure-documents/{$document['public_id']}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_issue_requires_closed_case_and_an_eligible_assigned_signer(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->closedCase();
        $case->forceFill(['closed_at' => null, 'status_code' => CaseStatus::query()->where('name', CaseStatusEnum::Recovery->value)->firstOrFail()->code])->save();
        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/v1/cases/{$case->id}/closure-document")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_closure_document_prerequisites_missing');

        $case->forceFill(['closed_at' => now(), 'status_code' => CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail()->code])->save();
        $satgas->forceFill(['nip' => null])->save();
        $this->postJson("/api/v1/cases/{$case->id}/closure-document")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_closure_document_signer_missing');
        $this->assertDatabaseMissing('case_closure_documents', ['case_id' => $case->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'case_closure_document.issued']);
    }

    public function test_multiple_eligible_signers_require_an_explicit_active_assignment_selection(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->closedCase();
        $secondSatgas = $this->user(
            'satgas_ppks',
            'second-signer@example.test',
            $reporter->university,
            'Satgas Penandatangan Kedua',
            '198202022026042002',
        );
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $secondSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now()->subWeeks(2),
        ]);

        Sanctum::actingAs($admin, ['*']);
        $this->getJson("/api/v1/cases/{$case->id}/closure-document")
            ->assertOk()
            ->assertJsonPath('data.capabilities.issue', true)
            ->assertJsonPath('data.signer_options.selection_required', true)
            ->assertJsonCount(2, 'data.signer_options.eligible_signers');

        $this->postJson("/api/v1/cases/{$case->id}/closure-document")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_closure_document_signer_selection_required');
        $this->assertDatabaseMissing('case_closure_documents', ['case_id' => $case->id]);

        $this->postJson("/api/v1/cases/{$case->id}/closure-document", ['signer_id' => $secondSatgas->id])
            ->assertCreated();
        $this->assertDatabaseHas('case_closure_documents', [
            'case_id' => $case->id,
            'signer_id' => $secondSatgas->id,
            'signer_name' => 'Satgas Penandatangan Kedua',
            'signer_identity_number' => '198202022026042002',
        ]);
    }

    public function test_selected_signer_must_be_active_assigned_and_have_an_identity_number(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->closedCase();
        $unassigned = $this->user(
            'satgas_ppks',
            'unassigned-signer@example.test',
            $reporter->university,
            'Satgas Tidak Ditugaskan',
            '198303032026043003',
        );
        $inactive = $this->user(
            'satgas_ppks',
            'inactive-signer@example.test',
            $reporter->university,
            'Satgas Tidak Aktif',
            '198404042026044004',
        );
        $inactive->forceFill(['is_active' => false])->save();
        $missingIdentity = $this->user(
            'satgas_ppks',
            'missing-identity@example.test',
            $reporter->university,
            'Satgas Tanpa Identitas',
        );
        foreach ([$inactive, $missingIdentity] as $candidate) {
            CaseAssignment::query()->create([
                'case_id' => $case->id,
                'satgas_id' => $candidate->id,
                'assigned_by' => $admin->id,
                'is_lead' => false,
                'is_active' => true,
                'assigned_at' => now()->subWeek(),
            ]);
        }

        Sanctum::actingAs($admin, ['*']);
        foreach ([$unassigned, $inactive, $missingIdentity] as $candidate) {
            $this->postJson("/api/v1/cases/{$case->id}/closure-document", ['signer_id' => $candidate->id])
                ->assertUnprocessable()
                ->assertJsonPath('error_code', 'case_closure_document_signer_invalid');
        }

        $this->assertDatabaseMissing('case_closure_documents', ['case_id' => $case->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'case_closure_document.issued']);
    }

    public function test_pdf_template_preserves_fixed_copy_and_only_projects_declared_placeholders(): void
    {
        $html = view('pdf.case-closure-document', [
            'universityName' => 'Universitas Template',
            'universityAddress' => 'Alamat Kampus Template',
            'registrationNumber' => 'SLP-TEMPLATE-001',
            'documentNumber' => 'BAHPKS/2026/CASE-TEMPLATE-001',
            'receivedDate' => '01 Agustus 2026',
            'issuedDay' => 'Kamis',
            'issuedDateNumber' => '13',
            'issuedMonth' => 'Agustus',
            'issuedYear' => '2026',
            'issuedDateLong' => 'Kamis, 13 Agustus 2026',
            'caseStatus' => 'Ditutup',
            'signerName' => 'Penandatangan Berita Acara Template',
            'signerIdentityNumber' => '198001012026041001',
        ])->render();

        $this->assertStringContainsString('HASIL AKHIR REKAPITULASI PELAPORAN DUGAAN KASUS KEKERASAN', $html);
        $this->assertStringContainsString('Universitas Template', $html);
        $this->assertStringContainsString('BAHPKS/2026/CASE-TEMPLATE-001', $html);
        $this->assertStringContainsString('SLP-TEMPLATE-001', $html);
        $this->assertStringContainsString('Rekapitulasi ini merupakan dokumen administrasi', $html);
        $this->assertStringContainsString('tidak dimaksudkan sebagai putusan hukum', $html);
        $this->assertStringContainsString('Penandatangan Berita Acara Template', $html);
        $this->assertStringContainsString('198001012026041001', $html);
        $this->assertStringNotContainsString('officialStatement', $html);
        $this->assertStringNotContainsString('closingExplanation', $html);
        $this->assertStringNotContainsString('followUp', $html);
    }

    /** @return array{User, User, User, CaseRecord} */
    private function closedCase(): array
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $university->forceFill(['address' => 'Jl. Kampus Nomor 1'])->save();
        $admin = $this->user('admin', 'admin-doc@example.test', $university);
        $satgas = $this->user('satgas_ppks', 'satgas-doc@example.test', $university, 'Satgas Penandatangan', '198001012026041001');
        $reporter = $this->user('reporter', 'reporter-doc@example.test', $university);
        $report = Report::query()->create([
            'reporter_id' => $reporter->id, 'registration_number' => 'SLP-DOC-001', 'report_type' => 'confidential',
            'category_code' => 'RCAT-01', 'chronology' => 'Kronologi pengujian dokumen hasil pelaporan.',
            'incident_date' => now()->subMonth()->toDateString(), 'incident_location' => 'Kampus',
            'status' => ReportStatus::Forwarded->value, 'submitted_at' => now()->subMonth(), 'forwarded_at' => now()->subWeeks(3),
        ]);
        $closed = CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id, 'registration_number' => $report->registration_number, 'case_number' => 'CASE-DOC-001',
            'status_code' => $closed->code, 'current_stage' => $closed->workflow_stage, 'forwarded_at' => now()->subWeeks(3), 'closed_at' => now(),
        ]);
        CaseAssignment::query()->create(['case_id' => $case->id, 'satgas_id' => $satgas->id, 'assigned_by' => $admin->id, 'is_lead' => false, 'is_active' => true, 'assigned_at' => now()->subWeeks(3)]);
        CaseFinalSummary::query()->create([
            'case_id' => $case->id, 'outcome_code' => 'resolved', 'completion_date' => now()->toDateString(),
            'official_statement' => 'Penanganan laporan telah selesai sesuai prosedur.', 'closing_explanation' => 'Kasus ditutup setelah prasyarat terpenuhi.',
            'follow_up_or_referral' => 'Pelapor dapat menghubungi kanal resmi bila membutuhkan informasi lanjutan.',
            'created_by' => $admin->id, 'updated_by' => $admin->id, 'published_by' => $admin->id, 'published_at' => now(),
        ]);
        return [$admin, $satgas, $reporter, $case];
    }

    private function user(string $role, string $email, University $university, ?string $name = null, ?string $nip = null): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('code', $role)->firstOrFail()->id,
            'university_id' => $university->id, 'name' => $name ?? ucfirst($role), 'email' => $email,
            'nip' => $nip, 'password' => 'SecurePass123', 'is_active' => true,
        ]);
    }
}
