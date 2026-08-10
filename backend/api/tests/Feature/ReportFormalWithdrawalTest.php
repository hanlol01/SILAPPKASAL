<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseFinalOutcome;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\EvidenceStatus;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseAssignment;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Recovery;
use App\Models\RecoveryStatus;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use App\Services\AuditLogService;
use App\Services\CaseClosureService;
use App\Services\CaseFinalSummaryService;
use App\Services\CaseMutationGuard;
use App\Services\CaseService;
use App\Services\CaseWorkflowContextService;
use App\Services\DecisionService;
use App\Services\EvidenceService;
use App\Services\FormalReportWithdrawalService;
use App\Services\InvestigationService;
use App\Services\RecommendationService;
use App\Services\RecoveryService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ReportFormalWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private University $campus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
        $this->campus = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        config()->set('withdrawal.early_cancellation_enabled', true);
        config()->set('withdrawal.formal_withdrawal_enabled', true);
        Storage::fake('withdrawal');
    }

    public function test_eligible_owner_can_create_encrypted_draft_with_authoritative_capabilities(): void
    {
        $reporter = $this->makeUser('reporter', 'owner@example.test');
        $report = $this->makeReport($reporter);
        $case = $this->makeCase($report, CaseStatusEnum::Assessment);
        $reason = 'Saya mengajukan pencabutan formal karena alasan pribadi.';

        Sanctum::actingAs($reporter, ['*']);
        $response = $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => " \u{00A0}{$reason}\u{00A0} "],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->assertJsonPath('data.status', ReportWithdrawalStatus::Draft->value)
            ->assertJsonPath('data.lock_version', 0)
            ->assertJsonPath('data.reason', $reason)
            ->assertJsonPath('data.has_signed_document', false)
            ->assertJsonPath('data.capabilities.can_view_draft', true)
            ->assertJsonPath('data.capabilities.can_upload_document', true)
            ->assertJsonPath('data.capabilities.can_submit', false)
            ->assertJsonPath('data.capabilities.can_cancel_request', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.report_id')
            ->assertJsonMissingPath('data.case_id')
            ->assertJsonMissingPath('data.requester_id')
            ->assertJsonMissingPath('data.disk')
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.sha256');

        $withdrawal = ReportWithdrawal::query()->sole();
        $this->assertSame($reason, $withdrawal->reason);
        $this->assertSame($report->registration_number, $withdrawal->registration_number_snapshot);
        $this->assertSame($reporter->name, $withdrawal->requester_display_name_snapshot);
        $this->assertSame($report->status, $withdrawal->previous_report_status);
        $this->assertSame($case->status->name, $withdrawal->previous_case_status);
        $this->assertNotSame(
            $reason,
            DB::table('report_withdrawals')->where('id', $withdrawal->id)->value('reason'),
        );
        $this->assertNotSame(
            $reporter->name,
            DB::table('report_withdrawals')->where('id', $withdrawal->id)
                ->value('requester_display_name_snapshot'),
        );

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.withdrawal_capabilities.can_cancel', false)
            ->assertJsonPath('data.withdrawal_capabilities.can_request_withdrawal', false)
            ->assertJsonPath(
                'data.withdrawal_capabilities.active_withdrawal.status',
                ReportWithdrawalStatus::Draft->value,
            )
            ->assertJsonPath('data.withdrawal_capabilities.active_withdrawal.lock_version', 0);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/withdrawal")
            ->assertOk()
            ->assertJsonPath('data.lock_version', 0);
    }

    public function test_eligibility_is_fail_closed_for_feature_actor_ownership_and_case_state(): void
    {
        $owner = $this->makeUser('reporter', 'owner@example.test');
        $other = $this->makeUser('reporter', 'other@example.test');
        $report = $this->makeReport($owner);
        $this->makeCase($report, CaseStatusEnum::Assessment);

        Sanctum::actingAs($other, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertNotFound();

        $inactive = $this->makeUser('reporter', 'inactive@example.test', active: false);
        Sanctum::actingAs($inactive, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertForbidden();

        $admin = $this->makeUser('admin', 'admin@example.test');
        Sanctum::actingAs($admin, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertForbidden();

        config()->set('withdrawal.formal_withdrawal_enabled', false);
        Sanctum::actingAs($owner, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertConflict()->assertJsonPath('reason_code', 'feature_disabled');

        config()->set('withdrawal.formal_withdrawal_enabled', true);
        $submitted = $this->makeReport(
            $owner,
            ReportStatus::Submitted,
            'confidential',
            'SLP-20260724-1002',
        );
        $this->postJson(
            "/api/v1/portal/reports/{$submitted->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertConflict()->assertJsonPath('reason_code', 'not_forwarded');

        $legacy = $this->makeReport(
            null,
            ReportStatus::Forwarded,
            'anonymous',
            'SLP-20260724-1003',
        );
        $this->postJson(
            "/api/v1/portal/reports/{$legacy->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertNotFound();
    }

    public function test_authenticated_anonymous_owner_can_view_blank_draft_template_without_identity(): void
    {
        $reporter = $this->makeUser('reporter', 'anonymous@example.test');
        $report = $this->makeReport(
            $reporter,
            ReportStatus::Forwarded,
            'anonymous',
            'SLP-20260724-1010',
        );
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);

        $publicId = $this->createFormal($report);
        $response = $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document");

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('SURAT PERNYATAAN')
            ->assertSee('DRAFT/SLP-20260724-1010')
            ->assertDontSee($reporter->name)
            ->assertDontSee('report_id')
            ->assertDontSee('case_id')
            ->assertDontSee('respondent');
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    public function test_reason_normalization_and_boundaries_are_enforced(): void
    {
        foreach ([
            ["\u{00A0}".str_repeat('a', 20)."\u{2003}", str_repeat('a', 20), true],
            [str_repeat('b', 2000).'  ', str_repeat('b', 2000), true],
            [str_repeat('c', 19).'  ', null, false],
            [str_repeat('d', 2001), null, false],
        ] as $index => [$input, $expected, $valid]) {
            $reporter = $this->makeUser('reporter', "reason-{$index}@example.test");
            $report = $this->makeReport(
                $reporter,
                ReportStatus::Forwarded,
                'confidential',
                'SLP-20260724-'.str_pad((string) (1200 + $index), 4, '0', STR_PAD_LEFT),
            );
            $this->makeCase($report, CaseStatusEnum::Assessment);
            Sanctum::actingAs($reporter, ['*']);

            $response = $this->postJson(
                "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
                ['reason' => $input],
            );

            if ($valid) {
                $response->assertCreated()->assertJsonPath('data.reason', $expected);
            } else {
                $response->assertUnprocessable()->assertJsonValidationErrors('reason');
            }
        }
    }

    public function test_draft_document_get_is_read_only_and_matches_the_manual_template(): void
    {
        $reporter = $this->makeUser('reporter', 'draft@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $before = ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $beforeUpdatedAt = $before->updated_at;

        $first = $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document");
        $first
            ->assertOk()
            ->assertSee('SURAT PERNYATAAN')
            ->assertSee('PERMOHONAN PENGHENTIAN PENANGANAN LAPORAN')
            ->assertSee('Nomor: DRAFT/SLP-20260724-1001')
            ->assertSee('Materai Rp10.000')
            ->assertDontSee('Alasan pencabutan:', false);

        $unchanged = ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertSame(ReportWithdrawalStatus::Draft, $unchanged->status);
        $this->assertNull($unchanged->draft_document_viewed_at);
        $this->assertSame(0, $unchanged->lock_version);
        $this->assertTrue($beforeUpdatedAt->equalTo($unchanged->updated_at));

        $this->travel(1)->minute();
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();
        $unchanged->refresh();
        $this->assertSame(ReportWithdrawalStatus::Draft, $unchanged->status);
        $this->assertNull($unchanged->draft_document_viewed_at);
        $this->assertSame(0, $unchanged->lock_version);
        $this->assertTrue($beforeUpdatedAt->equalTo($unchanged->updated_at));
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReportWithdrawalDraftDocumentPrepared->value,
        ]);
    }

    public function test_draft_document_escapes_dynamic_html_and_restricts_browser_capabilities(): void
    {
        $reporter = $this->makeUser('reporter', 'xss-draft@example.test');
        $reporter->forceFill([
            'name' => '<img src=x onerror=alert(1)>',
        ])->save();
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        $reason = "<script>alert(1)</script>\n<svg onload=alert(2)>"
            .' <a href="javascript:alert(3)">x</a> &#x3C;img src=x onerror=alert(4)&#x3E;';
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report, $reason);

        $response = $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document");

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<svg onload=', false)
            ->assertDontSee('<img src=x onerror=', false)
            ->assertDontSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('&amp;#x3C;img', false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_owner_can_download_numbered_docx_draft_and_open_private_example(): void
    {
        $reporter = $this->makeUser('reporter', 'draft-download@example.test');
        $report = $this->makeReport($reporter);
        $report->forceFill(['registration_number' => 'SLP-DEMO-2026-0810-0001'])->save();
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);

        $download = $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document/download");
        $download
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));

        $temporaryPath = tempnam(sys_get_temp_dir(), 'withdrawal-docx-test-');
        $this->assertNotFalse($temporaryPath);
        file_put_contents($temporaryPath, $download->streamedContent());

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($temporaryPath) === true);
        $documentXml = $archive->getFromName('word/document.xml');
        $coreProperties = $archive->getFromName('docProps/core.xml');
        $archive->close();
        @unlink($temporaryPath);

        $this->assertIsString($documentXml);
        $this->assertIsString($coreProperties);
        $this->assertStringContainsString('DRAFT/SLP-2026-0810-0001', $documentXml);
        $this->assertStringNotContainsString('DRAFT/SLP-DEMO-', $documentXml);
        $this->assertStringNotContainsString('{{generate_system}}', $documentXml);
        $this->assertStringNotContainsString('richard mills', $coreProperties);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReportWithdrawalDraftDocumentDownloaded->value,
        ]);

        $example = $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document/example");
        $example
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="contoh-pengisian-surat-pencabutan.pdf"');
        $this->assertStringContainsString('no-store', (string) $example->headers->get('Cache-Control'));

        $otherReporter = $this->makeUser('reporter', 'other-draft-download@example.test');
        Sanctum::actingAs($otherReporter, ['*']);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document/download")->assertNotFound();
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document/example")->assertNotFound();
    }

    public function test_formal_mutations_reject_stale_lock_versions_without_partial_changes(): void
    {
        Notification::fake();
        $reporter = $this->makeUser('reporter', 'stale-formal@example.test');
        $this->makeUser('admin', 'stale-formal-admin@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);

        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf('version-1.pdf'), 'lock_version' => 0],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.latest_attachment.version', 1);

        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf('stale.pdf'), 'lock_version' => 0],
            ['Accept' => 'application/json'],
        )
            ->assertConflict()
            ->assertJsonPath('reason_code', 'stale_update');
        $this->assertDatabaseCount('report_withdrawal_attachments', 1);
        $this->assertCount(1, Storage::disk('withdrawal')->allFiles());

        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf('version-2.pdf'), 'lock_version' => 1],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('data.lock_version', 2)
            ->assertJsonPath('data.latest_attachment.version', 2);

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => 1],
        )
            ->assertConflict()
            ->assertJsonPath('reason_code', 'stale_update');
        $this->assertSame(
            ReportWithdrawalStatus::WaitingDocument,
            ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail()->status,
        );

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => 2],
        )->assertOk()->assertJsonPath('data.lock_version', 3);
        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/cancel",
            ['lock_version' => 2],
        )
            ->assertConflict()
            ->assertJsonPath('reason_code', 'stale_update');
        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/cancel",
            ['lock_version' => 3],
        )->assertOk()->assertJsonPath('data.lock_version', 4);
    }

    public function test_signed_document_upload_is_private_versioned_and_safely_projected(): void
    {
        $reporter = $this->makeUser('reporter', 'upload@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();

        $first = $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            [
                'file' => $this->validPdf('../signed-statement.pdf'),
                'lock_version' => $this->lockVersion($publicId),
            ],
            ['Accept' => 'application/json'],
        );
        $first
            ->assertCreated()
            ->assertJsonPath('data.has_signed_document', true)
            ->assertJsonPath('data.latest_attachment.version', 1)
            ->assertJsonPath('data.latest_attachment.document_type', 'signed_withdrawal_statement')
            ->assertJsonPath('data.capabilities.can_submit', true)
            ->assertJsonMissingPath('data.latest_attachment.original_name')
            ->assertJsonMissingPath('data.latest_attachment.disk')
            ->assertJsonMissingPath('data.latest_attachment.path')
            ->assertJsonMissingPath('data.latest_attachment.sha256');

        $second = $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPng(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        );
        $second
            ->assertCreated()
            ->assertJsonPath('data.latest_attachment.version', 2);
        $third = $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validJpeg(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        );
        $third
            ->assertCreated()
            ->assertJsonPath('data.latest_attachment.version', 3)
            ->assertJsonPath('data.attachments.0.version', 3)
            ->assertJsonPath('data.attachments.1.version', 2)
            ->assertJsonPath('data.attachments.2.version', 1)
            ->assertJsonMissingPath('data.attachments.0.original_name')
            ->assertJsonMissingPath('data.attachments.0.disk')
            ->assertJsonMissingPath('data.attachments.0.path')
            ->assertJsonMissingPath('data.attachments.0.sha256');

        $withdrawal = ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertCount(3, $withdrawal->attachments);
        $this->assertSame([1, 2, 3], $withdrawal->attachments->sortBy('version')->pluck('version')->all());

        foreach ($withdrawal->attachments as $attachment) {
            Storage::disk('withdrawal')->assertExists($attachment->path);
            $this->assertStringStartsWith("formal/{$publicId}/", $attachment->path);
            $this->assertNotSame(
                $attachment->original_name,
                DB::table('report_withdrawal_attachments')
                    ->where('id', $attachment->id)
                    ->value('original_name'),
            );
        }

        $latest = $withdrawal->currentSignedAttachment();
        $download = $this->get(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document/{$latest->public_id}",
        );
        $download
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString(
            'no-store',
            (string) $download->headers->get('Cache-Control'),
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReportWithdrawalSignedDocumentDownloaded->value,
        ]);
    }

    public function test_upload_validation_rejects_unsafe_files_and_non_owner_download(): void
    {
        $reporter = $this->makeUser('reporter', 'security@example.test');
        $other = $this->makeUser('reporter', 'other@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();

        foreach ([
            UploadedFile::fake()->createWithContent('empty.pdf', ''),
            UploadedFile::fake()->createWithContent('image.svg', '<svg></svg>'),
            UploadedFile::fake()->createWithContent('double.pdf.exe', '%PDF-1.4 %%EOF'),
            UploadedFile::fake()->create('too-large.pdf', 10_241, 'application/pdf'),
            UploadedFile::fake()->createWithContent(
                'unsafe.pdf',
                "%PDF-1.4\n1 0 obj\n<< /OpenAction 2 0 R >>\nendobj\n%%EOF",
            ),
            UploadedFile::fake()->createWithContent(
                'encoded-action.pdf',
                "%PDF-1.4\n1 0 obj\n<< /J#61vaScript (alert) >>\nendobj\n%%EOF",
            ),
            UploadedFile::fake()->createWithContent(
                'trailing-polyglot.pdf',
                "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\nMZ executable",
            ),
        ] as $file) {
            $this->post(
                "/api/v1/portal/withdrawals/{$publicId}/signed-document",
                ['file' => $file, 'lock_version' => $this->lockVersion($publicId)],
                ['Accept' => 'application/json'],
            )->assertUnprocessable();
        }

        $uploaded = $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $attachmentId = $uploaded->json('data.latest_attachment.attachment_reference');

        Sanctum::actingAs($other, ['*']);
        $this->get(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document/{$attachmentId}",
        )->assertNotFound();
    }

    public function test_soft_deleted_report_hides_draft_and_private_attachment(): void
    {
        $reporter = $this->makeUser('reporter', 'deleted-report@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $upload = $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => 0],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $attachmentPublicId = $upload->json('data.latest_attachment.attachment_reference');
        $report->delete();

        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertNotFound();
        $this->get(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document/{$attachmentPublicId}",
        )->assertNotFound();
    }

    public function test_submit_requires_latest_valid_document_and_notifies_only_authorized_campus_admin(): void
    {
        Queue::fake();
        $reporter = $this->makeUser('reporter', 'submit@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $otherCampus = University::query()->where('code', 'DEMO-ST')->firstOrFail();
        $otherAdmin = $this->makeUser('admin', 'other-admin@example.test', $otherCampus);
        $report = $this->makeReport($reporter);
        $case = $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => $this->lockVersion($publicId)],
        )
            ->assertConflict()
            ->assertJsonPath('reason_code', 'invalid_transition');

        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $beforeReportStatus = $report->status;
        $beforeCaseStatus = $case->status_code;

        DB::beginTransaction();
        try {
            app(FormalReportWithdrawalService::class)->submit(
                $reporter,
                $publicId,
                $this->lockVersion($publicId),
            );
        } finally {
            DB::rollBack();
        }
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn ($job): bool => $job->afterCommit === true,
        );
        $this->assertSame(
            ReportWithdrawalStatus::WaitingDocument,
            ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail()->status,
        );
        Queue::fake();
        Notification::fake();

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => $this->lockVersion($publicId)],
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReportWithdrawalStatus::PendingReview->value)
            ->assertJsonPath('data.capabilities.can_upload_document', false)
            ->assertJsonPath('data.capabilities.can_submit', false)
            ->assertJsonPath('data.capabilities.can_cancel_request', true);

        $withdrawal = ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertNotNull($withdrawal->submitted_at);
        $this->assertSame(2, $withdrawal->lock_version);
        $this->assertSame($beforeReportStatus, $report->fresh()->status);
        $this->assertSame($beforeCaseStatus, $case->fresh()->status_code);

        Notification::assertSentTo(
            $admin,
            WorkflowDatabaseNotification::class,
            function (WorkflowDatabaseNotification $notification) use ($admin, $report): bool {
                $payload = $notification->toDatabase($admin);
                $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

                return $payload['notification_type_code'] === 'NOTIF-26'
                    && $payload['registration_number'] === $report->registration_number
                    && ! str_contains($serialized, 'Saya mengajukan');
            },
        );
        Notification::assertNotSentTo($satgas, WorkflowDatabaseNotification::class);
        Notification::assertNotSentTo($otherAdmin, WorkflowDatabaseNotification::class);

        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            [
                'file' => $this->validPdf('late.pdf'),
                'lock_version' => $this->lockVersion($publicId),
            ],
            ['Accept' => 'application/json'],
        )->assertConflict();
    }

    public function test_submit_fails_closed_when_latest_signed_binary_is_missing(): void
    {
        $reporter = $this->makeUser('reporter', 'missing-binary@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => 0],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $attachment = ReportWithdrawal::query()
            ->where('public_id', $publicId)
            ->firstOrFail()
            ->currentSignedAttachment();
        $this->assertNotNull($attachment);
        Storage::disk('withdrawal')->delete($attachment->path);

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => 1],
        )
            ->assertConflict()
            ->assertJsonPath('reason_code', 'signed_document_required');
        $withdrawal = ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertSame(ReportWithdrawalStatus::WaitingDocument, $withdrawal->status);
        $this->assertNull($withdrawal->submitted_at);
        $this->assertSame(1, $withdrawal->lock_version);
    }

    public function test_pending_review_pauses_case_and_reporter_evidence_until_cancel_reopens_it(): void
    {
        Notification::fake();
        $reporter = $this->makeUser('reporter', 'pause@example.test');
        $admin = $this->makeUser('admin', 'pause-admin@example.test');
        $report = $this->makeReport($reporter);
        $case = $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();
        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => $this->lockVersion($publicId)],
        )->assertOk();

        try {
            DB::transaction(
                fn () => app(CaseMutationGuard::class)->lockAndAssertMutable($case),
            );
            $this->fail('Pending formal withdrawal must pause operational mutations.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame(
                'withdrawal_pending_review',
                $exception->getResponse()->getData(true)['error_code'],
            );
        }

        $context = app(CaseWorkflowContextService::class)->forCase($case->fresh(), $admin);
        $this->assertTrue($context['facts']['operationally_paused']);
        foreach ($context['actions'] as $action) {
            $this->assertFalse($action['allowed']);
            $this->assertSame('withdrawal_pending_review', $action['reason_code']);
        }

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/evidence-files")
            ->assertOk()
            ->assertJsonPath('meta.upload_allowed', false);
        $this->post(
            "/api/v1/portal/reports/{$report->registration_number}/evidence-files",
            ['file' => $this->validPdf('support.pdf')],
            ['Accept' => 'application/json'],
        )->assertConflict();

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/cancel",
            ['lock_version' => $this->lockVersion($publicId)],
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReportWithdrawalStatus::Cancelled->value)
            ->assertJsonPath('data.capabilities.can_cancel_request', false);
        Notification::assertSentTo(
            $admin,
            WorkflowDatabaseNotification::class,
            fn (WorkflowDatabaseNotification $notification): bool => $notification
                ->toDatabase($admin)['notification_type_code'] === 'NOTIF-27',
        );

        DB::transaction(fn () => app(CaseMutationGuard::class)->lockAndAssertMutable($case));
        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/evidence-files")
            ->assertOk()
            ->assertJsonPath('meta.upload_allowed', true);
        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertSame(CaseStatusEnum::Assessment->value, $case->fresh()->status->name);
        $this->assertDatabaseCount('report_withdrawal_attachments', 1);
    }

    public function test_pending_review_blocks_every_case_and_child_mutation_service(): void
    {
        Notification::fake();
        $reporter = $this->makeUser('reporter', 'pause-matrix-reporter@example.test');
        $admin = $this->makeUser('admin', 'pause-matrix-admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'pause-matrix-satgas@example.test');
        $otherSatgas = $this->makeUser('satgas_ppks', 'pause-matrix-other@example.test');
        $report = $this->makeReport($reporter);
        $case = $this->makeCase($report, CaseStatusEnum::Assessment);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => 0],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => 1],
        )->assertOk();

        $investigation = Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => InvestigationStatus::query()
                ->where('name', InvestigationStatusEnum::Planning->value)
                ->value('code'),
            'plan_summary' => 'Rencana historis untuk probe pause.',
            'started_at' => now(),
        ]);
        $evidence = Evidence::query()->create([
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-01',
            'submitted_by' => $satgas->id,
            'title' => 'Bukti probe pause',
            'classification' => 'confidential',
            'status' => EvidenceStatus::Registered->value,
        ]);
        $recommendation = Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => RecommendationStatus::query()
                ->where('name', RecommendationStatusEnum::Drafting->value)
                ->value('code'),
            'conclusion' => 'Kesimpulan probe pause',
            'recommended_actions' => 'Tindakan probe pause',
        ]);
        $decision = Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => DecisionStatus::query()
                ->where('name', DecisionStatusEnum::Draft->value)
                ->value('code'),
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'Ringkasan probe pause',
            'decision_content' => 'Isi probe pause',
            'recorded_at' => now(),
        ]);
        $recovery = Recovery::query()->create([
            'decision_id' => $decision->id,
            'recovery_type_code' => 'RCV-01',
            'status_code' => RecoveryStatus::query()
                ->where('name', RecoveryStatusEnum::Ongoing->value)
                ->value('code'),
            'created_by' => $admin->id,
            'recovery_plan' => 'Rencana pemulihan probe pause',
            'started_at' => now(),
        ]);
        $summary = CaseFinalSummary::query()->create([
            'case_id' => $case->id,
            'outcome_code' => CaseFinalOutcome::Resolved,
            'completion_date' => now()->toDateString(),
            'official_statement' => 'Pernyataan probe pause',
            'closing_explanation' => 'Penjelasan probe pause',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        foreach ([
            'assignment' => fn () => app(CaseService::class)->assignSatgas($case, $admin, [
                'satgas_ids' => [$otherSatgas->id],
                'lock_version' => $case->assignmentLockVersion(),
            ]),
            'assessment' => fn () => app(CaseService::class)->recordAssessment($case, $satgas, [
                'risk_level_code' => 'RISK-02',
                'priority_level_code' => 'PRIO-02',
            ]),
            'transition-or-escalation' => fn () => app(CaseService::class)->updateStatus(
                $case,
                $satgas,
                CaseStatusEnum::Escalated->value,
            ),
            'investigation-create' => fn () => app(InvestigationService::class)->createForCase(
                $case,
                $satgas,
                ['plan_summary' => 'Tidak boleh dibuat'],
            ),
            'investigation-activity' => fn () => app(InvestigationService::class)->addActivity(
                $investigation,
                $satgas,
                [],
            ),
            'investigation-status' => fn () => app(InvestigationService::class)->updateStatus(
                $investigation,
                $satgas,
                InvestigationStatusEnum::EvidenceCollection->value,
            ),
            'evidence-create' => fn () => app(EvidenceService::class)->createForInvestigation(
                $investigation,
                $satgas,
                [],
            ),
            'evidence-update' => fn () => app(EvidenceService::class)->update(
                $evidence,
                $satgas,
                ['title' => 'Tidak boleh berubah'],
            ),
            'evidence-status' => fn () => app(EvidenceService::class)->updateStatus(
                $evidence,
                $satgas,
                EvidenceStatus::Verified->value,
            ),
            'evidence-upload' => fn () => app(EvidenceService::class)->uploadFile(
                $evidence,
                $satgas,
                $this->validPdf('evidence.pdf'),
            ),
            'recommendation-create' => fn () => app(RecommendationService::class)->createForCase(
                $case,
                $satgas,
                [],
            ),
            'recommendation-update' => fn () => app(RecommendationService::class)->update(
                $recommendation,
                $satgas,
                ['conclusion' => 'Tidak boleh berubah'],
            ),
            'recommendation-submit' => fn () => app(RecommendationService::class)->submit(
                $recommendation,
                $satgas,
            ),
            'recommendation-review' => fn () => app(RecommendationService::class)->review(
                $recommendation,
                $admin,
                [],
            ),
            'decision-create' => fn () => app(DecisionService::class)->createForRecommendation(
                $recommendation,
                $admin,
                [],
            ),
            'decision-update' => fn () => app(DecisionService::class)->update(
                $decision,
                $admin,
                ['decision_summary' => 'Tidak boleh berubah'],
            ),
            'decision-status' => fn () => app(DecisionService::class)->updateStatus(
                $decision,
                $admin,
                DecisionStatusEnum::Recorded->value,
            ),
            'recovery-create' => fn () => app(RecoveryService::class)->createForDecision(
                $decision,
                $admin,
                [],
            ),
            'recovery-update' => fn () => app(RecoveryService::class)->update(
                $recovery,
                $admin,
                ['recovery_plan' => 'Tidak boleh berubah'],
            ),
            'recovery-status' => fn () => app(RecoveryService::class)->updateStatus(
                $recovery,
                $admin,
                [],
            ),
            'monitoring' => fn () => app(RecoveryService::class)->createMonitoring(
                $recovery,
                $satgas,
                [],
            ),
            'final-summary-create' => fn () => app(CaseFinalSummaryService::class)->create(
                $case,
                $admin,
                [],
            ),
            'final-summary-update' => fn () => app(CaseFinalSummaryService::class)->update(
                $summary,
                $admin,
                [],
            ),
            'final-summary-publish' => fn () => app(CaseFinalSummaryService::class)->publish(
                $summary,
                $admin,
            ),
            'closure' => fn () => app(CaseClosureService::class)->close($case, $satgas),
        ] as $operation => $mutation) {
            $this->assertPausedConflict($mutation, $operation);
        }

        $this->assertSame('Bukti probe pause', $evidence->fresh()->title);
        $this->assertSame('Kesimpulan probe pause', $recommendation->fresh()->conclusion);
        $this->assertDatabaseCount('recovery_monitorings', 0);
    }

    public function test_cancelled_request_is_immutable_but_allows_a_new_eligible_request(): void
    {
        $reporter = $this->makeUser('reporter', 'cancel@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);

        $first = $this->createFormal($report);
        $this->postJson(
            "/api/v1/portal/withdrawals/{$first}/cancel",
            ['lock_version' => $this->lockVersion($first)],
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReportWithdrawalStatus::Cancelled->value);
        $this->postJson(
            "/api/v1/portal/withdrawals/{$first}/cancel",
            ['lock_version' => $this->lockVersion($first)],
        )->assertConflict();

        $second = $this->createFormal($report);
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('report_withdrawals', 2);
    }

    public function test_waiting_document_request_can_be_cancelled_without_deleting_history(): void
    {
        $reporter = $this->makeUser('reporter', 'waiting-cancel@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();
        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        )->assertCreated()->assertJsonPath('data.status', ReportWithdrawalStatus::WaitingDocument->value);

        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/cancel",
            ['lock_version' => $this->lockVersion($publicId)],
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReportWithdrawalStatus::Cancelled->value);
        $this->assertNotNull(
            ReportWithdrawal::query()->where('public_id', $publicId)->value('cancelled_at'),
        );
    }

    public function test_case_terminal_stages_and_duplicate_active_request_are_rejected(): void
    {
        foreach ([
            CaseStatusEnum::Decided,
            CaseStatusEnum::Recovery,
            CaseStatusEnum::Monitoring,
            CaseStatusEnum::Closed,
            CaseStatusEnum::Withdrawn,
            CaseStatusEnum::Escalated,
        ] as $index => $caseStatus) {
            $reporter = $this->makeUser(
                'reporter',
                "stage-{$caseStatus->value}@example.test",
            );
            Sanctum::actingAs($reporter, ['*']);
            $report = $this->makeReport(
                $reporter,
                ReportStatus::Forwarded,
                'confidential',
                'SLP-20260724-'.str_pad((string) (1100 + $index), 4, '0', STR_PAD_LEFT),
            );
            $this->makeCase($report, $caseStatus);

            $this->postJson(
                "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
                ['reason' => str_repeat('x', 20)],
            )->assertConflict()->assertJsonPath('reason_code', 'case_stage_ineligible');
        }

        $reporter = $this->makeUser('reporter', 'duplicate@example.test');
        Sanctum::actingAs($reporter, ['*']);
        $eligible = $this->makeReport(
            $reporter,
            ReportStatus::Forwarded,
            'confidential',
            'SLP-20260724-1199',
        );
        $this->makeCase($eligible, CaseStatusEnum::Assessment);
        $this->createFormal($eligible);
        $this->postJson(
            "/api/v1/portal/reports/{$eligible->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertConflict()->assertJsonPath('reason_code', 'active_request');
    }

    public function test_finalized_decision_blocks_formal_withdrawal_while_decision_stage_itself_is_eligible(): void
    {
        $reporter = $this->makeUser('reporter', 'final-decision-reporter@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'final-decision-satgas@example.test');
        $admin = $this->makeUser('admin', 'final-decision-admin@example.test');
        $report = $this->makeReport($reporter);
        $case = $this->makeCase($report, CaseStatusEnum::Decision);
        $investigationStatus = InvestigationStatus::query()
            ->where('name', 'completed')
            ->firstOrFail();
        $investigation = Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus->code,
            'plan_summary' => 'Rencana investigasi untuk pengujian keputusan final.',
            'findings' => 'Temuan investigasi.',
            'conclusion' => 'Kesimpulan investigasi.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $recommendationStatus = RecommendationStatus::query()
            ->where('name', RecommendationStatusEnum::Accepted->value)
            ->firstOrFail();
        $recommendation = Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $recommendationStatus->code,
            'conclusion' => 'Kesimpulan rekomendasi.',
            'recommended_actions' => 'Tindakan rekomendasi.',
            'sanction_recommendation' => 'Rekomendasi sanksi.',
            'recovery_recommendation' => 'Rekomendasi pemulihan.',
            'prevention_recommendation' => 'Rekomendasi pencegahan.',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ]);
        $decisionStatus = DecisionStatus::query()
            ->where('name', DecisionStatusEnum::Finalized->value)
            ->firstOrFail();
        Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => $decisionStatus->code,
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'Ringkasan keputusan final.',
            'decision_content' => 'Isi keputusan final.',
            'recorded_at' => now(),
            'finalized_at' => now(),
        ]);

        Sanctum::actingAs($reporter, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => str_repeat('x', 20)],
        )->assertConflict()->assertJsonPath('reason_code', 'decision_finalized');
    }

    public function test_audit_and_timeline_are_reporter_safe(): void
    {
        $reporter = $this->makeUser('reporter', 'audit@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $reason = 'Narasi privat tidak boleh pernah masuk metadata audit.';

        $publicId = $this->createFormal($report, $reason);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();
        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $this->postJson(
            "/api/v1/portal/withdrawals/{$publicId}/submit",
            ['lock_version' => $this->lockVersion($publicId)],
        )->assertOk();

        foreach ([
            AuditAction::ReportWithdrawalCreated,
            AuditAction::ReportWithdrawalDraftDocumentViewed,
            AuditAction::ReportWithdrawalSignedDocumentUploaded,
            AuditAction::ReportWithdrawalSubmitted,
        ] as $action) {
            $audit = DB::table('audit_logs')->where('action', $action->value)->first();
            $this->assertNotNull($audit);
            $serialized = json_encode($audit, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString($reason, $serialized);
            $this->assertStringNotContainsString('signed-statement.pdf', $serialized);
            $this->assertStringNotContainsString('formal/', $serialized);
            $this->assertStringNotContainsString('sha256', $serialized);
        }

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/timeline")
            ->assertOk()
            ->assertJsonFragment(['stage' => 'permohonan_pencabutan_dibuat'])
            ->assertJsonFragment(['stage' => 'dokumen_pencabutan_disiapkan'])
            ->assertJsonFragment(['stage' => 'surat_pencabutan_diunggah'])
            ->assertJsonFragment(['stage' => 'pencabutan_dikirim_untuk_verifikasi'])
            ->assertJsonMissing(['reason' => $reason]);
    }

    public function test_storage_is_cleaned_when_upload_transaction_fails(): void
    {
        $reporter = $this->makeUser('reporter', 'rollback@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->createFormal($report);
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();

        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException('audit unavailable'));
        });

        $this->post(
            "/api/v1/portal/withdrawals/{$publicId}/signed-document",
            ['file' => $this->validPdf(), 'lock_version' => $this->lockVersion($publicId)],
            ['Accept' => 'application/json'],
        )->assertServerError();

        $this->assertDatabaseCount('report_withdrawal_attachments', 0);
        $this->assertSame([], Storage::disk('withdrawal')->allFiles());
    }

    public function test_all_formal_routes_have_auth_no_store_and_named_throttle(): void
    {
        $expected = [
            'portal.reports.withdrawal.current' => 'throttle:reporter.withdrawal.read',
            'portal.reports.withdrawals.store' => 'throttle:reporter.withdrawal.create',
            'portal.withdrawals.draft-document' => 'throttle:reporter.withdrawal.document',
            'portal.withdrawals.draft-document.download' => 'throttle:reporter.withdrawal.document',
            'portal.withdrawals.draft-document.example' => 'throttle:reporter.withdrawal.document',
            'portal.withdrawals.signed-document.store' => 'throttle:reporter.withdrawal.upload',
            'portal.withdrawals.signed-document.download' => 'throttle:reporter.withdrawal.document',
            'portal.withdrawals.submit' => 'throttle:reporter.withdrawal.mutate',
            'portal.withdrawals.cancel' => 'throttle:reporter.withdrawal.mutate',
        ];

        foreach ($expected as $routeName => $throttle) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($route) => $route->getName() === $routeName);
            $this->assertNotNull($route, "Route {$routeName} is missing.");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('private.no-store', $middleware);
            $this->assertContains($throttle, $middleware);
        }
    }

    public function test_additive_migration_rolls_back_and_reapplies_on_sqlite(): void
    {
        $reporter = $this->makeUser('reporter', 'migration@example.test');
        $report = $this->makeReport($reporter);
        $this->makeCase($report, CaseStatusEnum::Assessment);
        $snapshot = DB::table('reports')->where('id', $report->id)
            ->first(['registration_number', 'status', 'forwarded_at']);
        $migration = require database_path(
            'migrations/2026_07_24_020000_extend_report_withdrawals_for_reporter_formal_flow.php',
        );

        $migration->down();
        $this->assertFalse(Schema::hasColumn('report_withdrawals', 'registration_number_snapshot'));
        $this->assertFalse(Schema::hasColumn('report_withdrawals', 'requester_display_name_snapshot'));
        $this->assertFalse(Schema::hasColumn('report_withdrawals', 'draft_document_viewed_at'));
        $this->assertDatabaseMissing('notification_types', ['code' => 'NOTIF-26']);
        $this->assertDatabaseMissing('notification_types', ['code' => 'NOTIF-27']);
        $this->assertEquals($snapshot, DB::table('reports')->where('id', $report->id)
            ->first(['registration_number', 'status', 'forwarded_at']));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('report_withdrawals', 'registration_number_snapshot'));
        $this->assertTrue(Schema::hasColumn('report_withdrawals', 'requester_display_name_snapshot'));
        $this->assertTrue(Schema::hasColumn('report_withdrawals', 'draft_document_viewed_at'));
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-26']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-27']);
        $this->assertEquals($snapshot, DB::table('reports')->where('id', $report->id)
            ->first(['registration_number', 'status', 'forwarded_at']));
    }

    private function createFormal(Report $report, ?string $reason = null): string
    {
        return (string) $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/withdrawals",
            ['reason' => $reason ?? 'Alasan pencabutan formal yang memenuhi batas minimum.'],
        )->assertCreated()->json('data.withdrawal_reference');
    }

    private function lockVersion(string $publicId): int
    {
        return (int) ReportWithdrawal::query()
            ->where('public_id', $publicId)
            ->value('lock_version');
    }

    private function assertPausedConflict(callable $mutation, string $operation): void
    {
        try {
            $mutation();
            $this->fail("Expected {$operation} to be paused by a pending formal withdrawal.");
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode(), $operation);
            $this->assertSame(
                'withdrawal_pending_review',
                $exception->getResponse()->getData(true)['error_code'] ?? null,
                $operation,
            );
        }
    }

    private function validPdf(string $name = 'signed-statement.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF",
        );
    }

    private function validPng(string $name = 'signed-v2.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );
    }

    private function validJpeg(string $name = 'signed-v3.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode(
                '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAP/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
                true,
            ),
        );
    }

    private function makeUser(
        string $roleCode,
        string $email,
        ?University $campus = null,
        bool $active = true,
    ): User {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'university_id' => $roleCode === 'super_admin' ? null : ($campus ?? $this->campus)->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => $active,
        ]);
    }

    private function makeReport(
        ?User $reporter,
        ReportStatus $status = ReportStatus::Forwarded,
        string $reportType = 'confidential',
        string $registrationNumber = 'SLP-20260724-1001',
    ): Report {
        return Report::query()->create([
            'reporter_id' => $reporter?->id,
            'registration_number' => $registrationNumber,
            'tracking_code' => $reportType === 'anonymous'
                ? strtoupper(substr(hash('sha256', $registrationNumber), 0, 16))
                : null,
            'report_type' => $reportType,
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi aman untuk pengujian pencabutan formal.',
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Lokasi kejadian',
            'status' => $status->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'forwarded_at' => $status === ReportStatus::Forwarded ? now() : null,
        ]);
    }

    private function makeCase(Report $report, CaseStatusEnum $caseStatus): CaseRecord
    {
        $status = CaseStatus::query()->where('name', $caseStatus->value)->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.substr(hash('sha256', $report->registration_number), 0, 12),
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'closed_at' => $caseStatus === CaseStatusEnum::Closed ? now() : null,
            'withdrawn_at' => $caseStatus === CaseStatusEnum::Withdrawn ? now() : null,
            'escalated_at' => $caseStatus === CaseStatusEnum::Escalated ? now() : null,
        ]);
    }
}
