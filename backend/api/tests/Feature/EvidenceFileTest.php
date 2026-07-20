<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\EvidenceClassification;
use App\Enums\EvidenceCustodyEventType;
use App\Enums\EvidenceStatus;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class EvidenceFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('oversight.cross_campus_sensitive_read', false);
        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        Storage::fake('evidence');
    }

    public function test_assigned_satgas_uploads_pdf_with_private_uuid_path_checksum_resource_custody_and_audit(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $content = $this->pdfContent();

        $this->actingAsApi($satgas);
        $this->post("/api/v1/evidences/{$evidence->id}/file", [
            'file' => UploadedFile::fake()->createWithContent('laporan akhir.pdf', $content),
        ])
            ->assertOk()
            ->assertJsonPath('data.file_attachment.original_filename', 'laporan akhir.pdf')
            ->assertJsonPath('data.file_attachment.mime_type', 'application/pdf')
            ->assertJsonPath('data.file_attachment.file_size', strlen($content))
            ->assertJsonPath('data.file_attachment.uploaded_by.id', $satgas->id)
            ->assertJsonMissingPath('data.storage_disk')
            ->assertJsonMissingPath('data.storage_path')
            ->assertJsonMissingPath('data.file_attachment.storage_path');

        $evidence->refresh();
        $this->assertSame('evidence', $evidence->storage_disk);
        $this->assertMatchesRegularExpression(
            sprintf('#^cases/%d/evidences/%d/[0-9a-f-]{36}\.pdf$#', $evidence->investigation->case_id, $evidence->id),
            $evidence->storage_path,
        );
        $this->assertStringNotContainsString('laporan akhir', $evidence->storage_path);
        $this->assertSame(hash('sha256', $content), $evidence->checksum_sha256);
        $this->assertSame(strlen($content), $evidence->file_size);
        $this->assertSame($satgas->id, $evidence->file_uploaded_by);
        $this->assertNotNull($evidence->file_uploaded_at);
        Storage::disk('evidence')->assertExists($evidence->storage_path);
        $this->assertSame($content, Storage::disk('evidence')->get($evidence->storage_path));
        $this->assertDatabaseHas('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FileUploaded->value,
            'actor_id' => $satgas->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::EvidenceFileUploaded->value,
            'actor_id' => $satgas->id,
            'subject_id' => $evidence->id,
        ]);
    }

    public function test_assigned_satgas_uploads_valid_jpeg_and_png_with_server_extensions(): void
    {
        [$firstEvidence, $satgas, $investigation] = $this->assignedEvidence();
        $secondEvidence = $this->makeEvidence($investigation, $satgas, title: 'PNG evidence');
        $this->actingAsApi($satgas);

        $this->post("/api/v1/evidences/{$firstEvidence->id}/file", [
            'file' => UploadedFile::fake()->createWithContent('photo.jpeg', $this->jpegContent()),
        ])->assertOk()->assertJsonPath('data.file_attachment.mime_type', 'image/jpeg');

        $this->post("/api/v1/evidences/{$secondEvidence->id}/file", [
            'file' => UploadedFile::fake()->createWithContent('capture.png', $this->pngContent()),
        ])->assertOk()->assertJsonPath('data.file_attachment.mime_type', 'image/png');

        $this->assertStringEndsWith('.jpg', $firstEvidence->refresh()->storage_path);
        $this->assertStringEndsWith('.png', $secondEvidence->refresh()->storage_path);
    }

    public function test_authorized_download_returns_original_bytes_and_security_headers_and_records_events(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $content = $this->pdfContent();
        $this->actingAsApi($satgas);
        $this->upload($evidence, 'incident-report.pdf', $content)->assertOk();

        $response = $this->get("/api/v1/evidences/{$evidence->id}/file");

        $response
            ->assertOk()
            ->assertDownload('incident-report.pdf')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertSame($content, $response->streamedContent());
        $this->assertDatabaseHas('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FileDownloaded->value,
            'actor_id' => $satgas->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::EvidenceFileDownloaded->value,
            'actor_id' => $satgas->id,
            'subject_id' => $evidence->id,
        ]);
    }

    public function test_authorized_satgas_previews_pdf_jpeg_and_png_inline_with_dedicated_events(): void
    {
        [$pdfEvidence, $satgas, $investigation] = $this->assignedEvidence();
        $jpegEvidence = $this->makeEvidence($investigation, $satgas, title: 'JPEG preview evidence');
        $pngEvidence = $this->makeEvidence($investigation, $satgas, title: 'PNG preview evidence');
        $files = [
            [$pdfEvidence, 'preview.pdf', $this->pdfContent(), 'application/pdf'],
            [$jpegEvidence, 'preview.jpeg', $this->jpegContent(), 'image/jpeg'],
            [$pngEvidence, 'preview.png', $this->pngContent(), 'image/png'],
        ];
        $this->actingAsApi($satgas);

        foreach ($files as [$evidence, $filename, $content]) {
            $this->upload($evidence, $filename, $content)->assertOk();
        }

        foreach ($files as [$evidence, $filename, $content, $mimeType]) {
            $response = $this->get("/api/v1/evidences/{$evidence->id}/preview");

            $response
                ->assertOk()
                ->assertHeader('Content-Type', $mimeType)
                ->assertHeader('Content-Length', (string) strlen($content))
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0')
                ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
            $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString($filename, (string) $response->headers->get('Content-Disposition'));
            $this->assertDatabaseMissing('evidence_custody_events', [
                'evidence_id' => $evidence->id,
                'event_type' => EvidenceCustodyEventType::FilePreviewed->value,
            ]);
            $this->assertDatabaseMissing('audit_logs', [
                'action' => AuditAction::EvidenceFilePreviewed->value,
                'subject_id' => $evidence->id,
            ]);
            $this->assertSame($content, $response->streamedContent());
            $this->assertDatabaseHas('evidence_custody_events', [
                'evidence_id' => $evidence->id,
                'event_type' => EvidenceCustodyEventType::FilePreviewed->value,
                'actor_id' => $satgas->id,
            ]);
            $this->assertDatabaseHas('audit_logs', [
                'action' => AuditAction::EvidenceFilePreviewed->value,
                'actor_id' => $satgas->id,
                'subject_id' => $evidence->id,
            ]);
        }

        $this->assertDatabaseMissing('evidence_custody_events', [
            'event_type' => EvidenceCustodyEventType::FileDownloaded->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EvidenceFileDownloaded->value,
        ]);
    }

    public function test_unauthorized_roles_historical_assignment_and_wrong_case_satgas_cannot_access_files(): void
    {
        [$evidence, $satgas, $investigation, $admin] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $this->upload($evidence)->assertOk();

        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $unassigned = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $historical = $this->makeUser('satgas_ppks', 'historical@university.ac.id');
        $wrongCase = $this->makeUser('satgas_ppks', 'wrong-case@university.ac.id');
        $inactive = $this->makeUser('satgas_ppks', 'inactive@university.ac.id');
        $inactive->forceFill(['is_active' => false])->save();
        CaseAssignment::query()->create([
            'case_id' => $investigation->case_id,
            'satgas_id' => $historical->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => false,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => now(),
        ]);
        CaseAssignment::query()->create([
            'case_id' => $investigation->case_id,
            'satgas_id' => $inactive->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        $this->makeInvestigation($admin, $wrongCase);

        foreach ([$admin, $superAdmin, $reporter, $unassigned, $historical, $wrongCase, $inactive] as $user) {
            $this->actingAsApi($user);
            $this->upload($evidence)->assertForbidden();
            $this->get("/api/v1/evidences/{$evidence->id}/file")->assertForbidden();
            $this->getJson("/api/v1/evidences/{$evidence->id}/preview")->assertForbidden();
        }

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EvidenceFilePreviewed->value,
        ]);
    }

    public function test_super_admin_internal_evidence_reads_require_flag_and_use_oversight_audits(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $superAdmin = $this->makeUser('super_admin', 'oversight@university.ac.id');
        $content = $this->pdfContent();
        $evidence->investigation->case->report()
            ->update([
                'report_type' => 'anonymous',
                'tracking_code' => 'ANON-EVIDENCE-0001',
            ]);

        $this->actingAsApi($satgas);
        $this->upload($evidence, 'Demo-Pelapor-internal.pdf', $content)->assertOk();
        $this->getJson("/api/v1/investigations/{$evidence->investigation_id}/evidences")
            ->assertOk()
            ->assertJsonPath('data.0.file_metadata.original_filename', 'internal-evidence.pdf')
            ->assertJsonMissing(['original_filename' => 'Demo-Pelapor-internal.pdf']);
        $satgasPreview = $this->get("/api/v1/evidences/{$evidence->id}/preview");
        $satgasPreview->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'internal-evidence.pdf',
            (string) $satgasPreview->headers->get('Content-Disposition'),
        );
        $this->assertStringNotContainsString(
            'Demo-Pelapor-internal.pdf',
            (string) $satgasPreview->headers->get('Content-Disposition'),
        );
        $satgasDownload = $this->get("/api/v1/evidences/{$evidence->id}/file");
        $satgasDownload->assertOk()->assertDownload('internal-evidence.pdf');
        $this->assertStringNotContainsString(
            'Demo-Pelapor-internal.pdf',
            (string) $satgasDownload->headers->get('Content-Disposition'),
        );

        $this->actingAsApi($superAdmin);
        config()->set('oversight.cross_campus_sensitive_read', false);
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")->assertForbidden();
        $this->getJson("/api/v1/evidences/{$evidence->id}/file")->assertForbidden();

        config()->set('oversight.cross_campus_sensitive_read', true);
        $this->getJson("/api/v1/investigations/{$evidence->investigation_id}/evidences")
            ->assertOk()
            ->assertJsonPath('data.0.file_metadata.original_filename', 'internal-evidence.pdf')
            ->assertJsonMissing(['original_filename' => 'Demo-Pelapor-internal.pdf']);
        $this->getJson("/api/v1/evidences/{$evidence->id}")
            ->assertOk()
            ->assertJsonPath('data.file_attachment.original_filename', 'internal-evidence.pdf')
            ->assertJsonMissing(['original_filename' => 'Demo-Pelapor-internal.pdf']);

        $preview = $this->get("/api/v1/evidences/{$evidence->id}/preview");
        $preview->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'internal-evidence.pdf',
            (string) $preview->headers->get('Content-Disposition'),
        );
        $this->assertStringNotContainsString(
            'Demo-Pelapor-internal.pdf',
            (string) $preview->headers->get('Content-Disposition'),
        );
        $this->assertSame($content, $preview->streamedContent());

        $download = $this->get("/api/v1/evidences/{$evidence->id}/file");
        $download->assertOk()->assertDownload('internal-evidence.pdf');
        $this->assertStringNotContainsString(
            'Demo-Pelapor-internal.pdf',
            (string) $download->headers->get('Content-Disposition'),
        );
        $this->assertSame($content, $download->streamedContent());

        $investigation = $evidence->investigation;
        $case = $investigation->case;
        $this->upload($evidence, 'super-admin-cannot-upload.pdf', $content)->assertForbidden();
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            'plan_summary' => 'Super Admin tetap tidak dapat membuat Investigasi meskipun flag sensitif aktif.',
        ])->assertForbidden();
        $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            'activity_type' => 'case_review',
            'activity_date' => now()->toDateString(),
            'description' => 'Super Admin tidak dapat menambah aktivitas investigasi.',
        ])->assertForbidden();
        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", [
            'status' => 'evidence_collection',
        ])->assertForbidden();
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", [
            'evidence_type_code' => 'EVID-01',
            'title' => 'Super Admin tidak dapat membuat bukti',
            'description' => 'Percobaan mutasi harus ditolak oleh backend.',
            'source' => 'Pengujian otorisasi.',
            'collected_at' => now()->toJSON(),
            'classification' => EvidenceClassification::Confidential->value,
        ])->assertForbidden();
        $this->patchJson("/api/v1/evidences/{$evidence->id}", [
            'title' => 'Super Admin tidak dapat mengubah bukti',
        ])->assertForbidden();
        $this->patchJson("/api/v1/evidences/{$evidence->id}/status", [
            'status' => EvidenceStatus::Verified->value,
        ])->assertForbidden();

        foreach ([
            AuditAction::EvidenceFilePreviewedByOversight,
            AuditAction::EvidenceFileDownloadedByOversight,
        ] as $action) {
            $audit = AuditLog::query()
                ->where('action', $action->value)
                ->where('actor_id', $superAdmin->id)
                ->where('subject_id', $evidence->id)
                ->firstOrFail();

            $this->assertTrue($audit->is_elevated_access);
            $this->assertSame($evidence->id, $audit->metadata['evidence_id']);
            $this->assertSame(
                $evidence->investigation->case->case_number,
                $audit->metadata['case_number'],
            );
            $this->assertTrue($audit->metadata['cross_campus_read']);
            $this->assertArrayNotHasKey('case_id', $audit->metadata);
            $this->assertArrayNotHasKey('storage_path', $audit->metadata);
            $this->assertArrayNotHasKey('description', $audit->metadata);
        }

        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $superAdmin->id,
            'action' => AuditAction::EvidenceFileDownloaded->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $superAdmin->id,
            'action' => AuditAction::EvidenceFilePreviewed->value,
        ]);
    }

    public function test_file_routes_require_authentication_and_their_distinct_permissions(): void
    {
        [$evidence, $satgas, $investigation] = $this->assignedEvidence();

        $this->upload($evidence)->assertUnauthorized();
        $this->getJson("/api/v1/evidences/{$evidence->id}/file")->assertUnauthorized();
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")->assertUnauthorized();

        $this->actingAsApi($satgas);
        $this->upload($evidence)->assertOk();

        $downloadPermission = Permission::query()->where('code', 'evidence.download')->firstOrFail();
        $satgas->role->permissions()->detach($downloadPermission->id);
        $satgas = $satgas->fresh();
        $this->actingAsApi($satgas);

        $this->get("/api/v1/evidences/{$evidence->id}/file")->assertForbidden();
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")->assertForbidden();
        $this->assertDatabaseMissing('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FileDownloaded->value,
        ]);

        $uploadPermission = Permission::query()->where('code', 'evidence.upload')->firstOrFail();
        $satgas->role->permissions()->detach($uploadPermission->id);
        $satgas = $satgas->fresh();
        $this->actingAsApi($satgas);
        $secondEvidence = $this->makeEvidence($investigation, $satgas, title: 'Permission boundary evidence');

        $this->upload($secondEvidence)->assertForbidden();
        $this->assertNull($secondEvidence->refresh()->storage_path);
    }

    public function test_content_and_extension_validation_rejects_unsafe_empty_and_oversized_files(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $invalidFiles = [
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script /></svg>'),
            UploadedFile::fake()->createWithContent('page.html', '<!doctype html><script>alert(1)</script>'),
            UploadedFile::fake()->createWithContent('archive.zip', "PK\x03\x04unsafe"),
            UploadedFile::fake()->createWithContent('program.exe', "MZ\x00\x00unsafe"),
            UploadedFile::fake()->createWithContent('empty.pdf', ''),
            UploadedFile::fake()->createWithContent('wrong-extension.txt', $this->pngContent()),
            UploadedFile::fake()->createWithContent('too-large.pdf', $this->pdfContent().str_repeat('0', (10 * 1024 * 1024) + 1)),
        ];

        foreach ($invalidFiles as $file) {
            $this->post("/api/v1/evidences/{$evidence->id}/file", ['file' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }

        $this->assertNull($evidence->refresh()->storage_path);
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    public function test_content_validation_rejects_double_extensions_and_spoofed_client_mime_types(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $spoofedFiles = [
            ['shell.php.pdf', '<?php echo "unsafe";', 'application/pdf'],
            ['vector.png', '<svg xmlns="http://www.w3.org/2000/svg"><script /></svg>', 'image/png'],
            ['page.pdf', '<!doctype html><script>alert(1)</script>', 'application/pdf'],
            ['archive.pdf', "PK\x03\x04unsafe", 'application/pdf'],
            ['program.pdf', "MZ\x00\x00unsafe", 'application/pdf'],
        ];

        foreach ($spoofedFiles as [$name, $content, $clientMimeType]) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'evidence-security-');
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

                $this->post("/api/v1/evidences/{$evidence->id}/file", ['file' => $file])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('file');
            } finally {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }

        $this->assertNull($evidence->refresh()->storage_path);
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    public function test_archived_evidence_and_closed_case_reject_upload(): void
    {
        [$archivedEvidence, $satgas, $investigation] = $this->assignedEvidence();
        $archivedEvidence->forceFill(['status' => EvidenceStatus::Archived->value])->save();
        $this->actingAsApi($satgas);
        $this->upload($archivedEvidence)->assertUnprocessable();

        $closedEvidence = $this->makeEvidence($investigation, $satgas, title: 'Closed case evidence');
        $closedStatus = CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail();
        $investigation->case->forceFill([
            'status_code' => $closedStatus->code,
            'current_stage' => $closedStatus->workflow_stage,
            'closed_at' => now(),
        ])->save();

        $this->upload($closedEvidence)->assertUnprocessable();
        $this->assertSame([], Storage::disk('evidence')->allFiles());
    }

    public function test_second_upload_returns_conflict_but_legacy_metadata_without_path_can_receive_file(): void
    {
        [$evidence, $satgas, $investigation] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $this->upload($evidence)->assertOk();
        $firstPath = $evidence->refresh()->storage_path;

        $this->upload($evidence, 'second.pdf')->assertConflict();
        $this->assertSame($firstPath, $evidence->refresh()->storage_path);
        $this->assertCount(1, Storage::disk('evidence')->allFiles());

        $legacy = $this->makeEvidence($investigation, $satgas, title: 'Legacy metadata');
        $legacy->forceFill([
            'original_filename' => 'legacy-name.png',
            'mime_type' => 'image/png',
            'file_size' => 25,
            'checksum_sha256' => str_repeat('a', 64),
        ])->save();

        $this->upload($legacy, 'first-physical.pdf')->assertOk();
        $this->assertNotNull($legacy->refresh()->storage_path);
        $this->assertSame('first-physical.pdf', $legacy->original_filename);
    }

    public function test_missing_physical_object_returns_generic_not_found_without_download_event(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $this->actingAsApi($satgas);

        $this->getJson("/api/v1/evidences/{$evidence->id}/file")
            ->assertNotFound()
            ->assertJsonPath('message', 'Evidence file not found');
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")
            ->assertNotFound()
            ->assertJsonPath('message', 'Evidence file not found');

        $this->upload($evidence)->assertOk();
        $path = $evidence->refresh()->storage_path;
        Storage::disk('evidence')->delete($path);

        $this->getJson("/api/v1/evidences/{$evidence->id}/file")
            ->assertNotFound()
            ->assertJsonPath('message', 'Evidence file not found')
            ->assertDontSee($path);
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")
            ->assertNotFound()
            ->assertJsonPath('message', 'Evidence file not found')
            ->assertDontSee($path);

        $this->assertDatabaseMissing('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FileDownloaded->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EvidenceFileDownloaded->value,
            'subject_id' => $evidence->id,
        ]);
        $this->assertDatabaseMissing('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FilePreviewed->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EvidenceFilePreviewed->value,
            'subject_id' => $evidence->id,
        ]);
    }

    public function test_unsupported_or_inconsistent_preview_metadata_is_rejected_without_success_events(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $this->upload($evidence)->assertOk();

        $evidence->forceFill(['mime_type' => 'text/html'])->save();
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Evidence file cannot be previewed');

        $evidence->forceFill(['mime_type' => 'image/png'])->save();
        $this->getJson("/api/v1/evidences/{$evidence->id}/preview")
            ->assertNotFound()
            ->assertJsonPath('message', 'Evidence file not found');

        $this->assertDatabaseMissing('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FilePreviewed->value,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EvidenceFilePreviewed->value,
            'subject_id' => $evidence->id,
        ]);
    }

    public function test_archived_evidence_remains_downloadable_for_authorized_assigned_satgas(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $this->upload($evidence, 'archived-evidence.pdf')->assertOk();
        $evidence->forceFill(['status' => EvidenceStatus::Archived->value])->save();

        $this->get("/api/v1/evidences/{$evidence->id}/file")
            ->assertOk()
            ->assertDownload('archived-evidence.pdf');
        $previewResponse = $this->get("/api/v1/evidences/{$evidence->id}/preview");
        $previewResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($this->pdfContent(), $previewResponse->streamedContent());
        $this->assertDatabaseHas('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FilePreviewed->value,
            'actor_id' => $satgas->id,
        ]);
    }

    public function test_audit_persistence_failure_rolls_back_metadata_and_removes_new_file(): void
    {
        [$evidence, $satgas] = $this->assignedEvidence();
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('simulated audit failure'));
        });
        $this->actingAsApi($satgas);

        $this->upload($evidence)->assertServerError();

        $evidence->refresh();
        $this->assertNull($evidence->storage_path);
        $this->assertNull($evidence->storage_disk);
        $this->assertNull($evidence->file_uploaded_at);
        $this->assertSame([], Storage::disk('evidence')->allFiles());
        $this->assertDatabaseMissing('evidence_custody_events', [
            'evidence_id' => $evidence->id,
            'event_type' => EvidenceCustodyEventType::FileUploaded->value,
        ]);
    }

    public function test_upload_route_is_limited_to_ten_attempts_per_hour_per_user(): void
    {
        [$firstEvidence, $satgas, $investigation] = $this->assignedEvidence();
        $this->actingAsApi($satgas);
        $evidences = [$firstEvidence];

        for ($index = 2; $index <= 11; $index++) {
            $evidences[] = $this->makeEvidence($investigation, $satgas, title: "Evidence {$index}");
        }

        foreach (array_slice($evidences, 0, 10) as $evidence) {
            $this->upload($evidence)->assertOk();
        }

        $this->upload($evidences[10])->assertTooManyRequests();
        $this->assertNull($evidences[10]->refresh()->storage_path);
    }

    /**
     * @return array{Evidence, User, Investigation, User}
     */
    private function assignedEvidence(): array
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $investigation = $this->makeInvestigation($admin, $satgas);

        return [$this->makeEvidence($investigation, $satgas), $satgas, $investigation, $admin];
    }

    private function upload(Evidence $evidence, string $name = 'evidence.pdf', ?string $content = null)
    {
        return $this->withHeader('Accept', 'application/json')
            ->post("/api/v1/evidences/{$evidence->id}/file", [
            'file' => UploadedFile::fake()->createWithContent($name, $content ?? $this->pdfContent()),
        ]);
    }

    private function makeEvidence(Investigation $investigation, User $satgas, string $title = 'Evidence metadata'): Evidence
    {
        return Evidence::query()->create([
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-03',
            'submitted_by' => $satgas->id,
            'title' => $title,
            'description' => 'Evidence metadata for secure file testing.',
            'source' => 'Investigation intake.',
            'collected_at' => now(),
            'classification' => EvidenceClassification::Confidential->value,
            'status' => EvidenceStatus::Registered->value,
        ]);
    }

    private function makeInvestigation(User $admin, User $satgas): Investigation
    {
        $case = $this->makeInvestigationCase($admin, $satgas);
        $status = InvestigationStatus::query()->where('name', 'planning')->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Secure evidence file test investigation plan.',
            'started_at' => now(),
        ])->load('case.status');
    }

    private function makeInvestigationCase(User $admin, User $satgas): CaseRecord
    {
        $report = Report::query()->create([
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'A sufficiently long chronology for secure evidence file testing.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Main campus building',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Respondent',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);
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
