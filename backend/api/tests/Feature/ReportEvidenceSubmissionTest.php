<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Permission;
use App\Models\Report;
use App\Models\ReportEvidenceSubmission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ReportEvidenceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        Storage::fake('evidence');
    }

    public function test_reporter_uploads_lists_and_downloads_an_owned_private_attachment(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $content = $this->pdfContent();
        $this->actingAsApi($reporter);

        $upload = $this->upload($report, 'laporan pendukung.pdf', $content);

        $upload
            ->assertCreated()
            ->assertJsonPath('data.original_filename', 'laporan pendukung.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.file_size', strlen($content))
            ->assertJsonMissingPath('data.report_id')
            ->assertJsonMissingPath('data.uploaded_by')
            ->assertJsonMissingPath('data.storage_disk')
            ->assertJsonMissingPath('data.storage_path')
            ->assertJsonMissingPath('data.checksum_sha256');
        $this->assertSame(
            ['id', 'original_filename', 'mime_type', 'file_size', 'uploaded_at'],
            array_keys($upload->json('data')),
        );

        $uuid = $upload->json('data.id');
        $this->assertIsString($uuid);
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $uuid));

        $submission = ReportEvidenceSubmission::query()->sole();
        $this->assertNotSame((string) $submission->id, $uuid);
        $this->assertSame($report->id, $submission->report_id);
        $this->assertSame($reporter->id, $submission->uploaded_by);
        $this->assertSame(hash('sha256', $content), $submission->checksum_sha256);
        $this->assertSame('evidence', $submission->storage_disk);
        $this->assertMatchesRegularExpression(
            sprintf('#^reports/%d/reporter-submissions/%s\.pdf$#', $report->id, preg_quote($uuid, '#')),
            $submission->storage_path,
        );
        $this->assertStringNotContainsString('laporan pendukung', $submission->storage_path);
        Storage::disk('evidence')->assertExists($submission->storage_path);
        $this->assertSame('private', config('filesystems.disks.evidence.visibility'));
        $this->assertSame($content, Storage::disk('evidence')->get($submission->storage_path));

        $this->getJson($this->reportFilesUrl($report))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $uuid)
            ->assertJsonPath('meta.upload_allowed', true)
            ->assertJsonPath('meta.max_files', 5)
            ->assertJsonPath('meta.remaining_slots', 4)
            ->assertJsonMissingPath('data.0.report_id')
            ->assertJsonMissingPath('data.0.storage_path');

        $download = $this->get("/api/v1/portal/evidence-files/{$uuid}/download");
        $download
            ->assertOk()
            ->assertDownload('laporan pendukung.pdf')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertSame($content, $download->streamedContent());
        $contentDisposition = (string) $download->headers->get('Content-Disposition');
        $this->assertStringNotContainsString($uuid, $contentDisposition);
        $this->assertStringNotContainsString($submission->storage_path, $contentDisposition);
        $this->assertStringNotContainsString('evidence', $contentDisposition);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterEvidenceUploaded->value,
            'actor_id' => $reporter->id,
            'subject_id' => $submission->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterEvidenceDownloadedByReporter->value,
            'actor_id' => $reporter->id,
            'subject_id' => $submission->id,
        ]);

        $audit = AuditLog::query()
            ->where('action', AuditAction::ReporterEvidenceUploaded->value)
            ->firstOrFail();
        $this->assertSame($uuid, $audit->metadata['attachment_uuid']);
        $this->assertSame(
            ['attachment_uuid', 'is_elevated_access'],
            array_keys($audit->metadata),
        );
        $this->assertFalse($audit->metadata['is_elevated_access']);
        $this->assertArrayNotHasKey('report_id', $audit->metadata);
        $this->assertArrayNotHasKey('case_id', $audit->metadata);
        $this->assertArrayNotHasKey('filename', $audit->metadata);
        $this->assertArrayNotHasKey('storage_path', $audit->metadata);
        $this->assertArrayNotHasKey('checksum', $audit->metadata);
    }

    public function test_reporter_cannot_access_another_report_or_substitute_an_attachment_uuid(): void
    {
        $owner = $this->makeUser('reporter', 'owner@example.test');
        $attacker = $this->makeUser('reporter', 'attacker@example.test');
        $ownedByOther = $this->makeReport($owner);

        $this->actingAsApi($owner);
        $uuid = $this->upload($ownedByOther)->assertCreated()->json('data.id');

        $this->actingAsApi($attacker);
        $this->getJson($this->reportFilesUrl($ownedByOther))->assertNotFound();
        $this->upload($ownedByOther)->assertNotFound();
        $this->getJson("/api/v1/portal/evidence-files/{$uuid}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found');
        $this->getJson('/api/v1/portal/evidence-files/00000000-0000-4000-8000-000000000000/download')
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found');

        $anonymousReport = $this->makeReport(null);
        $this->getJson($this->reportFilesUrl($anonymousReport))->assertNotFound();
        $this->assertDatabaseCount('report_evidence_submissions', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_anonymous_report_and_wrong_case_substitution_cannot_bypass_scoped_queries(): void
    {
        $owner = $this->makeUser('reporter', 'owner@example.test');
        $otherOwner = $this->makeUser('reporter', 'other-owner@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $assigned = $this->makeUser('satgas_ppks', 'assigned@example.test');
        $otherAssigned = $this->makeUser('satgas_ppks', 'other-assigned@example.test');
        $ownerReport = $this->makeReport($owner, ReportStatus::Forwarded->value);
        $otherReport = $this->makeReport($otherOwner, ReportStatus::Forwarded->value);
        $anonymousReport = $this->makeReport(null);

        $this->actingAsApi($owner);
        $ownerUuid = $this->upload($ownerReport, 'owner.pdf')->assertCreated()->json('data.id');
        $this->actingAsApi($otherOwner);
        $otherUuid = $this->upload($otherReport, 'other.pdf')->assertCreated()->json('data.id');

        $ownerCase = $this->makeCase($ownerReport, $admin, $assigned);
        $otherCase = $this->makeCase($otherReport, $admin, $otherAssigned);
        $anonymousUuid = Str::uuid()->toString();
        $anonymousPath = "reports/{$anonymousReport->id}/reporter-submissions/{$anonymousUuid}.pdf";
        Storage::disk('evidence')->put($anonymousPath, $this->pdfContent(), ['visibility' => 'private']);
        ReportEvidenceSubmission::query()->create([
            'uuid' => $anonymousUuid,
            'report_id' => $anonymousReport->id,
            'uploaded_by' => $owner->id,
            'original_filename' => 'anonymous.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($this->pdfContent()),
            'checksum_sha256' => hash('sha256', $this->pdfContent()),
            'storage_disk' => 'evidence',
            'storage_path' => $anonymousPath,
            'uploaded_at' => now(),
        ]);

        $this->actingAsApi($owner);
        $this->getJson($this->reportFilesUrl($anonymousReport))->assertNotFound();
        $this->upload($anonymousReport, 'anonymous-upload.pdf')->assertNotFound();
        $this->getJson("/api/v1/portal/evidence-files/{$anonymousUuid}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found');

        $this->actingAsApi($assigned);
        $this->getJson("/api/v1/cases/{$ownerCase->id}/reporter-evidence-files")
            ->assertOk()
            ->assertJsonPath('data.0.id', $ownerUuid);
        $this->getJson("/api/v1/cases/{$otherCase->id}/reporter-evidence-files")->assertForbidden();
        $this->getJson("/api/v1/reporter-evidence-files/{$otherUuid}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found');
    }

    public function test_rejected_and_closed_reports_reject_new_uploads_but_keep_existing_files_accessible(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $rejected = $this->makeReport($reporter);
        $closed = $this->makeReport($reporter, ReportStatus::Forwarded->value);
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $this->actingAsApi($reporter);

        $rejectedUuid = $this->upload($rejected, 'before-rejection.pdf')->assertCreated()->json('data.id');
        $closedUuid = $this->upload($closed, 'before-closure.pdf')->assertCreated()->json('data.id');

        $rejected->forceFill(['status' => ReportStatus::Rejected->value])->save();
        $this->makeCase($closed, $admin, $satgas, activeAssignment: true, closed: true);

        $this->upload($rejected, 'after-rejection.pdf')->assertConflict();
        $this->upload($closed, 'after-closure.pdf')->assertConflict();

        $this->getJson($this->reportFilesUrl($rejected))
            ->assertOk()
            ->assertJsonPath('meta.upload_allowed', false)
            ->assertJsonPath('data.0.id', $rejectedUuid);
        $this->getJson($this->reportFilesUrl($closed))
            ->assertOk()
            ->assertJsonPath('meta.upload_allowed', false)
            ->assertJsonPath('data.0.id', $closedUuid);
        $this->get("/api/v1/portal/evidence-files/{$rejectedUuid}/download")->assertOk();
        $this->get("/api/v1/portal/evidence-files/{$closedUuid}/download")->assertOk();
    }

    public function test_locked_report_count_rejects_a_sixth_upload(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);

        for ($index = 1; $index <= 5; $index++) {
            $this->upload($report, "support-{$index}.pdf")->assertCreated();
        }

        $this->upload($report, 'support-6.pdf')->assertConflict();
        $this->assertDatabaseCount('report_evidence_submissions', 5);
        $this->assertCount(5, Storage::disk('evidence')->allFiles());
        $this->getJson($this->reportFilesUrl($report))
            ->assertOk()
            ->assertJsonPath('meta.upload_allowed', false)
            ->assertJsonPath('meta.remaining_slots', 0);
    }

    public function test_replayed_upload_content_uses_unique_uuid_paths_and_cannot_bypass_the_limit(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);

        for ($index = 0; $index < 5; $index++) {
            $this->upload($report, 'replayed.pdf', $this->pdfContent())->assertCreated();
        }

        $this->upload($report, 'replayed.pdf', $this->pdfContent())->assertConflict();
        $this->assertCount(
            5,
            ReportEvidenceSubmission::query()->where('report_id', $report->id)->pluck('uuid')->unique(),
        );
        $this->assertCount(
            5,
            ReportEvidenceSubmission::query()->where('report_id', $report->id)->pluck('storage_path')->unique(),
        );
        $this->assertCount(5, Storage::disk('evidence')->allFiles());
    }

    public function test_assigned_satgas_lists_and_downloads_reporter_files_after_case_closure(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Forwarded->value);
        $this->actingAsApi($reporter);
        $uuid = $this->upload($report, 'reporter-document.pdf')->assertCreated()->json('data.id');
        $case = $this->makeCase($report, $admin, $satgas, activeAssignment: true, closed: true);

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $uuid)
            ->assertJsonMissingPath('data.0.report_id')
            ->assertJsonMissingPath('data.0.uploaded_by');

        $download = $this->get("/api/v1/reporter-evidence-files/{$uuid}/download");
        $download->assertOk()->assertDownload('reporter-document.pdf');
        $this->assertSame($this->pdfContent(), $download->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterEvidenceDownloadedBySatgas->value,
            'actor_id' => $satgas->id,
        ]);
        $audit = AuditLog::query()
            ->where('action', AuditAction::ReporterEvidenceDownloadedBySatgas->value)
            ->firstOrFail();
        $this->assertSame($uuid, $audit->metadata['attachment_uuid']);
        $this->assertArrayNotHasKey('report_id', $audit->metadata);
        $this->assertArrayNotHasKey('case_id', $audit->metadata);
    }

    public function test_satgas_access_requires_both_a_case_and_a_current_active_assignment(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Forwarded->value);
        $this->actingAsApi($reporter);
        $uuid = $this->upload($report, 'before-case.pdf')->assertCreated()->json('data.id');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found');

        $case = $this->makeCase($report, $admin, $satgas, activeAssignment: false);
        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertForbidden();
        $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found');
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ReporterEvidenceDownloadedBySatgas->value,
        ]);

        CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $satgas->id)
            ->update(['is_active' => true, 'unassigned_at' => null]);

        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")
            ->assertOk()
            ->assertJsonPath('data.0.id', $uuid);
        $this->get("/api/v1/reporter-evidence-files/{$uuid}/download")->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterEvidenceDownloadedBySatgas->value,
            'actor_id' => $satgas->id,
        ]);
    }

    public function test_unassigned_historical_inactive_satgas_admin_and_super_admin_are_denied(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $assigned = $this->makeUser('satgas_ppks', 'assigned@example.test');
        $unassigned = $this->makeUser('satgas_ppks', 'unassigned@example.test');
        $historical = $this->makeUser('satgas_ppks', 'historical@example.test');
        $inactive = $this->makeUser('satgas_ppks', 'inactive@example.test');
        $inactive->forceFill(['is_active' => false])->save();
        $report = $this->makeReport($reporter, ReportStatus::Forwarded->value);
        $this->actingAsApi($reporter);
        $uuid = $this->upload($report)->assertCreated()->json('data.id');
        $case = $this->makeCase($report, $admin, $assigned);

        foreach ([[$historical, false], [$inactive, true]] as [$satgas, $active]) {
            CaseAssignment::query()->create([
                'case_id' => $case->id,
                'satgas_id' => $satgas->id,
                'assigned_by' => $admin->id,
                'is_lead' => false,
                'is_active' => $active,
                'assigned_at' => now(),
                'unassigned_at' => $active ? null : now(),
            ]);
        }

        foreach ([$unassigned, $historical] as $satgas) {
            $this->actingAsApi($satgas);
            $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertForbidden();
            $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")
                ->assertNotFound()
                ->assertJsonPath('message', 'Supporting file not found');
        }

        $this->actingAsApi($inactive);
        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertForbidden();
        $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")->assertForbidden();

        foreach ([$admin, $superAdmin] as $user) {
            $this->actingAsApi($user);
            $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertForbidden();
            $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")->assertForbidden();
        }

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertForbidden();
        $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")->assertForbidden();
    }

    public function test_inactive_reporter_and_reporter_without_exact_permissions_are_denied(): void
    {
        $inactive = $this->makeUser('reporter', 'inactive@example.test');
        $report = $this->makeReport($inactive);
        $this->actingAsApi($inactive);
        $uuid = $this->upload($report, 'before-deactivation.pdf')->assertCreated()->json('data.id');
        $inactive->forceFill(['is_active' => false])->save();
        $inactive = $inactive->fresh();
        $this->actingAsApi($inactive);
        $this->getJson($this->reportFilesUrl($report))->assertForbidden();
        $this->upload($report)->assertForbidden();
        $this->getJson("/api/v1/portal/evidence-files/{$uuid}/download")->assertForbidden();

        $reporter = $this->makeUser('reporter', 'permissionless@example.test');
        $permission = Permission::query()->where('code', 'reporter_evidence.read.own')->firstOrFail();
        $reporter->role->permissions()->detach($permission->id);
        $reporter = $reporter->fresh();
        $ownReport = $this->makeReport($reporter);
        $this->actingAsApi($reporter);
        $this->getJson($this->reportFilesUrl($ownReport))->assertForbidden();
    }

    public function test_routes_require_authentication_and_each_exact_permission(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Forwarded->value);
        $case = $this->makeCase($report, $admin, $satgas);

        $this->getJson($this->reportFilesUrl($report))->assertUnauthorized();
        $this->upload($report)->assertUnauthorized();
        $this->getJson('/api/v1/portal/evidence-files/00000000-0000-4000-8000-000000000000/download')
            ->assertUnauthorized();
        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertUnauthorized();
        $this->getJson('/api/v1/reporter-evidence-files/00000000-0000-4000-8000-000000000000/download')
            ->assertUnauthorized();

        $this->actingAsApi($reporter);
        $uuid = $this->upload($report)->assertCreated()->json('data.id');
        $reporterDownload = Permission::query()->where('code', 'reporter_evidence.download.own')->firstOrFail();
        $reporter->role->permissions()->detach($reporterDownload->id);
        $this->actingAsApi($reporter->fresh());
        $this->getJson("/api/v1/portal/evidence-files/{$uuid}/download")->assertForbidden();

        $satgasRead = Permission::query()->where('code', 'reporter_evidence.read.assigned')->firstOrFail();
        $satgasDownload = Permission::query()->where('code', 'reporter_evidence.download.assigned')->firstOrFail();
        $satgas->role->permissions()->detach($satgasRead->id);
        $this->actingAsApi($satgas->fresh());
        $this->getJson("/api/v1/cases/{$case->id}/reporter-evidence-files")->assertForbidden();

        $satgas->role->permissions()->attach($satgasRead->id);
        $satgas->role->permissions()->detach($satgasDownload->id);
        $this->actingAsApi($satgas->fresh());
        $this->getJson("/api/v1/reporter-evidence-files/{$uuid}/download")->assertForbidden();
    }

    public function test_validation_rejects_unsafe_empty_oversized_double_extension_and_spoofed_files(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);

        $invalidFiles = [
            UploadedFile::fake()->createWithContent('vector.svg', '<svg><script /></svg>'),
            UploadedFile::fake()->createWithContent('page.html', '<!doctype html><script />'),
            UploadedFile::fake()->createWithContent('script.php', '<?php echo "unsafe";'),
            UploadedFile::fake()->createWithContent('program.exe', "MZ\x00\x00unsafe"),
            UploadedFile::fake()->createWithContent('archive.zip', "PK\x03\x04unsafe"),
            UploadedFile::fake()->createWithContent('empty.pdf', ''),
            UploadedFile::fake()->createWithContent('shell.php.pdf', $this->pdfContent()),
            UploadedFile::fake()->createWithContent('document.txt', $this->pdfContent()),
            UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf'),
        ];

        foreach ($invalidFiles as $file) {
            $response = $this->post(
                $this->reportFilesUrl($report),
                ['file' => $file],
                ['Accept' => 'application/json'],
            );
            $this->assertSame(422, $response->status(), $file->getClientOriginalName());
            $response->assertJsonValidationErrors('file');
        }

        $this->assertDatabaseCount('report_evidence_submissions', 0);
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    public function test_actual_content_mime_rejects_spoofed_pdf_and_image_uploads(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);
        $spoofedFiles = [
            ['page.pdf', '<!doctype html><script>alert(1)</script>', 'application/pdf'],
            ['vector.png', '<svg xmlns="http://www.w3.org/2000/svg"><script /></svg>', 'image/png'],
            ['archive.pdf', "PK\x03\x04unsafe", 'application/pdf'],
            ['program.jpg', "MZ\x00\x00unsafe", 'image/jpeg'],
        ];

        foreach ($spoofedFiles as [$name, $content, $clientMimeType]) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'reporter-evidence-security-');
            $this->assertNotFalse($temporaryPath);
            file_put_contents($temporaryPath, $content);

            try {
                $file = new UploadedFile(
                    $temporaryPath,
                    $name,
                    $clientMimeType,
                    UPLOAD_ERR_OK,
                    true,
                );

                $this->post($this->reportFilesUrl($report), ['file' => $file], ['Accept' => 'application/json'])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('file');
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }

        $this->assertDatabaseCount('report_evidence_submissions', 0);
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    public function test_valid_jpeg_and_png_use_server_derived_extensions(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);

        $this->upload($report, 'photo.jpeg', $this->jpegContent())->assertCreated();
        $this->upload($report, 'capture.png', $this->pngContent())->assertCreated();

        $paths = ReportEvidenceSubmission::query()->orderBy('id')->pluck('storage_path')->all();
        $this->assertStringEndsWith('.jpg', $paths[0]);
        $this->assertStringEndsWith('.png', $paths[1]);
    }

    public function test_original_filename_is_sanitized_for_metadata_and_download_headers(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);

        $response = $this->upload($report, 'laporan<>akhir.pdf')->assertCreated();
        $uuid = $response->json('data.id');
        $safeName = $response->json('data.original_filename');

        $this->assertSame('laporan_akhir.pdf', $safeName);
        $this->assertStringNotContainsString('<', $safeName);
        $this->assertStringNotContainsString('>', $safeName);
        $this->get("/api/v1/portal/evidence-files/{$uuid}/download")
            ->assertOk()
            ->assertDownload('laporan_akhir.pdf');
    }

    public function test_path_control_unicode_and_long_filenames_are_safely_normalized(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);

        $pathResponse = $this->upload($report, "..\\..\\laporan\r\nX-Header.pdf")->assertCreated();
        $unicodeResponse = $this->upload($report, 'bukti-aman-漢字.pdf')->assertCreated();
        $longResponse = $this->upload($report, str_repeat('a', 300).'.pdf')->assertCreated();

        $this->assertSame('laporanX-Header.pdf', $pathResponse->json('data.original_filename'));
        $this->assertSame('bukti-aman-漢字.pdf', $unicodeResponse->json('data.original_filename'));

        $longFilename = $longResponse->json('data.original_filename');
        $this->assertIsString($longFilename);
        $this->assertLessThanOrEqual(255, mb_strwidth($longFilename));
        $this->assertStringEndsWith('.pdf', $longFilename);
        $this->assertStringNotContainsString("\r", $longFilename);
        $this->assertStringNotContainsString("\n", $longFilename);
        $this->assertStringNotContainsString('\\', $longFilename);
        $this->assertStringNotContainsString('/', $longFilename);

        $this->get("/api/v1/portal/evidence-files/{$longResponse->json('data.id')}/download")
            ->assertOk()
            ->assertDownload($longFilename);
    }

    public function test_missing_physical_file_returns_generic_not_found_without_path_leakage(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $this->actingAsApi($reporter);
        $uuid = $this->upload($report)->assertCreated()->json('data.id');
        $submission = ReportEvidenceSubmission::query()->sole();
        Storage::disk('evidence')->delete($submission->storage_path);

        $this->getJson("/api/v1/portal/evidence-files/{$uuid}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Supporting file not found')
            ->assertDontSee($submission->storage_path);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ReporterEvidenceDownloadedByReporter->value,
            'subject_id' => $submission->id,
        ]);
    }

    public function test_audit_failure_rolls_back_metadata_and_removes_the_new_file(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $sensitiveFailure = 'storage_path=reports/1/private.pdf checksum_sha256=secret report_id=1';
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException(
                'storage_path=reports/1/private.pdf checksum_sha256=secret report_id=1'
            ));
        });
        $this->actingAsApi($reporter);

        $this->upload($report)
            ->assertServerError()
            ->assertJsonPath('message', 'The supporting file could not be saved')
            ->assertDontSee($sensitiveFailure)
            ->assertDontSee('storage_path')
            ->assertDontSee('checksum_sha256')
            ->assertDontSee('report_id');

        $this->assertDatabaseCount('report_evidence_submissions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    public function test_reporter_upload_route_is_limited_to_ten_attempts_per_hour(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $this->actingAsApi($reporter);
        $reports = [];

        for ($index = 1; $index <= 11; $index++) {
            $reports[] = $this->makeReport($reporter);
        }

        foreach (array_slice($reports, 0, 10) as $index => $report) {
            $this->upload($report, "rate-{$index}.pdf")->assertCreated();
        }

        $this->upload($reports[10], 'rate-11.pdf')->assertTooManyRequests();
        $this->assertDatabaseCount('report_evidence_submissions', 10);
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

    private function makeReport(?User $reporter, string $status = ReportStatus::Submitted->value): Report
    {
        $sequence = Report::query()->count() + 1;

        return Report::query()->create([
            'reporter_id' => $reporter?->id,
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'tracking_code' => $reporter ? null : 'TRACK-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'report_type' => $reporter ? 'confidential' : 'anonymous',
            'category_code' => 'RCAT-01',
            'chronology' => 'A sufficiently long chronology for reporter evidence submission testing.',
            'incident_date' => now()->subDay()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Main campus building',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Respondent',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'status' => $status,
            'submitted_at' => now(),
            'forwarded_at' => $status === ReportStatus::Forwarded->value ? now() : null,
        ]);
    }

    private function makeCase(
        Report $report,
        User $admin,
        User $satgas,
        bool $activeAssignment = true,
        bool $closed = false,
    ): CaseRecord {
        $status = CaseStatus::query()
            ->where('name', $closed ? CaseStatusEnum::Closed->value : CaseStatusEnum::Investigation->value)
            ->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'closed_at' => $closed ? now() : null,
        ]);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => $activeAssignment,
            'assigned_at' => now(),
            'unassigned_at' => $activeAssignment ? null : now(),
        ]);

        return $case->load('status');
    }

    private function upload(Report $report, string $name = 'support.pdf', ?string $content = null)
    {
        return $this->post($this->reportFilesUrl($report), [
            'file' => UploadedFile::fake()->createWithContent($name, $content ?? $this->pdfContent()),
        ], ['Accept' => 'application/json']);
    }

    private function reportFilesUrl(Report $report): string
    {
        return "/api/v1/portal/reports/{$report->registration_number}/evidence-files";
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    private function pngContent(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6ZQAAAABJRU5ErkJggg==', true);
    }

    private function jpegContent(): string
    {
        return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAP/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true);
    }
}
