<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalStatus;
use App\Models\BreakGlassRequest;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Permission;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReportWithdrawalReviewTest extends TestCase
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
        config()->set('withdrawal.formal_withdrawal_enabled', true);
        Storage::fake('withdrawal');
    }

    public function test_admin_queue_is_own_campus_pending_by_default_and_super_admin_is_metadata_only(): void
    {
        $reporter = $this->user('reporter', 'review-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $admin = $this->user('admin', 'review-admin@example.test');

        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/v1/report-withdrawals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.withdrawal_reference', $withdrawal->public_id)
            ->assertJsonMissingPath('data.0.reason')
            ->assertJsonMissingPath('data.0.attachments');

        $otherCampus = University::query()->whereKeyNot($this->campus->id)->firstOrFail();
        $otherAdmin = $this->user('admin', 'other-admin@example.test', $otherCampus);
        Sanctum::actingAs($otherAdmin, ['*']);
        $this->getJson('/api/v1/report-withdrawals')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")->assertNotFound();

        $super = $this->user('super_admin', 'super-monitor@example.test');
        Sanctum::actingAs($super, ['*']);
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_review', false)
            ->assertJsonMissingPath('data.reason')
            ->assertJsonMissingPath('data.rejection_reason')
            ->assertJsonMissingPath('data.attachments');
        $attachment = $withdrawal->currentSignedAttachment();
        $this->get("/api/v1/report-withdrawals/{$withdrawal->public_id}/signed-document/{$attachment->public_id}")
            ->assertForbidden();
    }

    public function test_review_routes_fail_closed_for_unauthorized_or_inactive_roles(): void
    {
        $reporter = $this->user('reporter', 'authorization-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);

        foreach (['reporter', 'satgas_ppks'] as $role) {
            Sanctum::actingAs($this->user($role, "{$role}-review-denied@example.test"), ['*']);
            $this->getJson('/api/v1/report-withdrawals')->assertForbidden();
            $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
                'lock_version' => $withdrawal->lock_version,
                'confirmed' => true,
            ])->assertForbidden();
        }

        $inactiveAdmin = $this->user('admin', 'inactive-reviewer@example.test');
        $inactiveAdmin->forceFill(['is_active' => false])->save();
        Sanctum::actingAs($inactiveAdmin, ['*']);
        $this->getJson('/api/v1/report-withdrawals')->assertForbidden();

        $super = $this->user('super_admin', 'readonly-super@example.test');
        Sanctum::actingAs($super, ['*']);
        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $withdrawal->lock_version,
            'rejection_reason' => 'Alasan penolakan yang cukup panjang untuk validasi.',
            'resubmission_allowed' => false,
        ])->assertForbidden();
    }

    public function test_admin_can_preview_latest_private_document_and_access_is_safely_audited(): void
    {
        $reporter = $this->user('reporter', 'document-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $attachment = $withdrawal->currentSignedAttachment();
        $admin = $this->user('admin', 'document-admin@example.test');
        Sanctum::actingAs($admin, ['*']);

        $this->get("/api/v1/report-withdrawals/{$withdrawal->public_id}/signed-document/{$attachment->public_id}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Disposition', 'inline; filename="surat-pencabutan-v1.pdf"')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox; frame-ancestors 'none'");

        $audit = DB::table('audit_logs')
            ->where('action', AuditAction::ReportWithdrawalSignedDocumentReviewed->value)
            ->first();
        $this->assertNotNull($audit);
        $serialized = json_encode($audit, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('formal/', $serialized);
        $this->assertStringNotContainsString('sha256', $serialized);
        $this->assertStringNotContainsString('signed-statement.pdf', $serialized);
    }

    public function test_approval_atomically_withdraws_report_and_case_preserves_assignment_and_is_stale_safe(): void
    {
        Notification::fake();
        $reporter = $this->user('reporter', 'approve-owner@example.test');
        $report = $this->report($reporter);
        $case = $this->case($report);
        $satgas = $this->user('satgas_ppks', 'assigned@example.test');
        $admin = $this->user('admin', 'approve-admin@example.test');
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        $relevantGrant = BreakGlassRequest::query()->create([
            'requestor_id' => $satgas->id,
            'approver_id' => $admin->id,
            'report_id' => $report->id,
            'reason_category' => 'investigation_necessity',
            'reason' => 'Keperluan pengujian pencabutan akses aktif.',
            'requested_duration_minutes' => 60,
            'status' => BreakGlassRequest::STATUS_APPROVED,
            'requested_at' => now()->subMinute(),
            'approved_at' => now(),
            'grant_starts_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
        ]);
        $unrelatedReporter = $this->user('reporter', 'unrelated-grant-owner@example.test');
        $unrelatedReport = $this->report($unrelatedReporter);
        $unrelatedGrant = BreakGlassRequest::query()->create([
            'requestor_id' => $satgas->id,
            'approver_id' => $admin->id,
            'report_id' => $unrelatedReport->id,
            'reason_category' => 'investigation_necessity',
            'reason' => 'Akses lain tidak boleh ikut dicabut.',
            'requested_duration_minutes' => 60,
            'status' => BreakGlassRequest::STATUS_APPROVED,
            'requested_at' => now()->subMinute(),
            'approved_at' => now(),
            'grant_starts_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
        ]);
        $expiredSatgas = $this->user('satgas_ppks', 'expired-grant@example.test');
        $expiredGrant = BreakGlassRequest::query()->create([
            'requestor_id' => $expiredSatgas->id,
            'approver_id' => $admin->id,
            'report_id' => $report->id,
            'reason_category' => 'investigation_necessity',
            'reason' => 'Akses kedaluwarsa harus tetap menjadi histori.',
            'requested_duration_minutes' => 60,
            'status' => BreakGlassRequest::STATUS_APPROVED,
            'requested_at' => now()->subHours(2),
            'approved_at' => now()->subHours(2),
            'grant_starts_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $lockVersion = $withdrawal->lock_version;
        config()->set('withdrawal.formal_withdrawal_enabled', false);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $lockVersion,
            'confirmed' => true,
        ])->assertOk()->assertJsonPath('data.status', ReportWithdrawalStatus::Approved->value);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => ReportStatus::Withdrawn->value,
        ]);
        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'status_code' => CaseStatus::query()->where('name', CaseStatusEnum::Withdrawn->value)->value('code'),
            'closed_at' => null,
        ]);
        $this->assertDatabaseHas('case_assignments', ['case_id' => $case->id, 'satgas_id' => $satgas->id]);
        $this->assertNotNull($report->fresh()->withdrawn_at);
        $this->assertNotNull($case->fresh()->withdrawn_at);
        $this->assertSame(BreakGlassRequest::STATUS_REVOKED, $relevantGrant->fresh()->status);
        $this->assertNotNull($relevantGrant->fresh()->revoked_at);
        $this->assertSame(BreakGlassRequest::STATUS_APPROVED, $unrelatedGrant->fresh()->status);
        $this->assertSame(BreakGlassRequest::STATUS_APPROVED, $expiredGrant->fresh()->status);
        $this->assertNull($expiredGrant->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::BreakGlassRevoked->value]);
        Notification::assertSentTo(
            $reporter,
            WorkflowDatabaseNotification::class,
            function (WorkflowDatabaseNotification $notification) use ($reporter): bool {
                $payload = $notification->toDatabase($reporter);

                return $payload['notification_type_code']
                    === NotificationService::TYPE_REPORT_FORMAL_WITHDRAWAL_APPROVED
                    && ! array_key_exists('reason', $payload)
                    && ! array_key_exists('rejection_reason', $payload)
                    && ! array_key_exists('subject_id', $payload)
                    && ! array_key_exists('case_id', $payload);
            },
        );
        Notification::assertNotSentTo(
            $satgas,
            WorkflowDatabaseNotification::class,
            fn (WorkflowDatabaseNotification $notification): bool => $notification
                ->toDatabase($satgas)['notification_type_code']
                === NotificationService::TYPE_REPORT_FORMAL_WITHDRAWAL_APPROVED,
        );

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $lockVersion,
            'confirmed' => true,
        ])->assertConflict()->assertJsonPath('reason_code', 'stale_update');
        $this->assertSame(1, DB::table('audit_logs')->where('action', AuditAction::ReportMarkedWithdrawn->value)->count());

        Sanctum::actingAs($satgas, ['*']);
        $this->getJson("/api/v1/cases/{$case->id}")->assertOk()->assertJsonPath('data.status', CaseStatusEnum::Withdrawn->value);
        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => 'assessment'])->assertStatus(409);
    }

    public function test_rejection_keeps_lifecycle_and_owner_can_create_fresh_resubmission_without_attachment(): void
    {
        Notification::fake();
        $reporter = $this->user('reporter', 'reject-owner@example.test');
        $report = $this->report($reporter);
        $case = $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $admin = $this->user('admin', 'reject-admin@example.test');
        Sanctum::actingAs($admin, ['*']);
        $reason = 'Dokumen perlu diperbaiki dan alasan pencabutan perlu dibuat lebih jelas.';

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $withdrawal->lock_version,
            'rejection_reason' => "  {$reason}\n",
            'resubmission_allowed' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', ReportWithdrawalStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', $reason)
            ->assertJsonPath('data.resubmission_allowed', true);

        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertSame(CaseStatusEnum::Assessment->value, $case->fresh('status')->status->name);
        Notification::assertSentTo(
            $reporter,
            WorkflowDatabaseNotification::class,
            function (WorkflowDatabaseNotification $notification) use ($reporter, $reason): bool {
                $payload = $notification->toDatabase($reporter);

                return $payload['notification_type_code']
                    === NotificationService::TYPE_REPORT_FORMAL_WITHDRAWAL_REJECTED
                    && ! str_contains(json_encode($payload, JSON_THROW_ON_ERROR), $reason);
            },
        );

        Sanctum::actingAs($reporter, ['*']);
        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.withdrawal_capabilities.latest_withdrawal.status', 'rejected')
            ->assertJsonPath('data.withdrawal_capabilities.latest_withdrawal.rejection_reason', $reason)
            ->assertJsonPath('data.withdrawal_capabilities.latest_withdrawal.capabilities.can_resubmit', true);
        $response = $this->postJson("/api/v1/portal/withdrawals/{$withdrawal->public_id}/resubmit", [
            'lock_version' => $withdrawal->fresh()->lock_version,
            'reason' => 'Alasan baru untuk pengajuan ulang pencabutan formal Pengaduan.',
        ])->assertCreated()
            ->assertJsonPath('data.status', ReportWithdrawalStatus::Draft->value)
            ->assertJsonPath('data.has_signed_document', false);

        $new = ReportWithdrawal::query()->where('public_id', $response->json('data.withdrawal_reference'))->firstOrFail();
        $this->assertSame($withdrawal->id, $new->supersedes_id);
        $this->assertSame(ReportWithdrawalStatus::Rejected, $withdrawal->fresh()->status);
        $this->assertCount(0, $new->attachments);
    }

    public function test_invalid_latest_document_blocks_approval_without_partial_finalization(): void
    {
        $reporter = $this->user('reporter', 'invalid-document-owner@example.test');
        $report = $this->report($reporter);
        $case = $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $attachment = $withdrawal->currentSignedAttachment();
        Storage::disk($attachment->disk)->delete($attachment->path);
        $admin = $this->user('admin', 'invalid-document-admin@example.test');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $withdrawal->lock_version,
            'confirmed' => true,
        ])->assertConflict()->assertJsonPath('reason_code', 'signed_document_unavailable');

        $this->assertSame(ReportWithdrawalStatus::PendingReview, $withdrawal->fresh()->status);
        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertSame(CaseStatusEnum::Assessment->value, $case->fresh('status')->status->name);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ReportMarkedWithdrawn->value,
            'subject_id' => $report->id,
        ]);
    }

    public function test_queue_filters_search_sort_pagination_and_permission_are_fail_closed(): void
    {
        $reporterA = $this->user('reporter', 'queue-a@example.test');
        $reportA = $this->report($reporterA);
        $this->case($reportA);
        $withdrawalA = $this->pendingWithdrawal($reporterA, $reportA);
        $withdrawalA->forceFill(['submitted_at' => now()->subHour()])->save();

        $reporterB = $this->user('reporter', 'queue-b@example.test');
        $reportB = $this->report($reporterB);
        $this->case($reportB);
        $withdrawalB = $this->pendingWithdrawal($reporterB, $reportB);
        $admin = $this->user('admin', 'queue-admin@example.test');
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/report-withdrawals?per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.withdrawal_reference', $withdrawalA->public_id)
            ->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/report-withdrawals?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.withdrawal_reference', $withdrawalB->public_id)
            ->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/report-withdrawals?status=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/report-withdrawals?search=%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/report-withdrawals?search='.urlencode($reportB->registration_number))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.withdrawal_reference', $withdrawalB->public_id);

        $permissionId = Permission::query()
            ->where('code', 'reports.withdraw.review.own_campus')
            ->value('id');
        DB::table('role_permissions')
            ->where('role_id', $admin->role_id)
            ->where('permission_id', $permissionId)
            ->delete();
        $admin->unsetRelation('role');

        $this->getJson('/api/v1/report-withdrawals')->assertForbidden();
    }

    public function test_cross_campus_mutations_and_attachment_substitution_do_not_enumerate_or_audit(): void
    {
        $reporterA = $this->user('reporter', 'binding-a@example.test');
        $reportA = $this->report($reporterA);
        $this->case($reportA);
        $withdrawalA = $this->pendingWithdrawal($reporterA, $reportA);

        $reporterB = $this->user('reporter', 'binding-b@example.test');
        $reportB = $this->report($reporterB);
        $this->case($reportB);
        $withdrawalB = $this->pendingWithdrawal($reporterB, $reportB);
        $attachmentB = $withdrawalB->currentSignedAttachment();

        $admin = $this->user('admin', 'binding-admin@example.test');
        Sanctum::actingAs($admin, ['*']);
        $this->get(
            "/api/v1/report-withdrawals/{$withdrawalA->public_id}/signed-document/{$attachmentB->public_id}"
        )->assertConflict();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ReportWithdrawalSignedDocumentReviewed->value,
            'subject_id' => $reportA->id,
        ]);

        $otherCampus = University::query()->whereKeyNot($this->campus->id)->firstOrFail();
        $otherAdmin = $this->user('admin', 'binding-other-admin@example.test', $otherCampus);
        Sanctum::actingAs($otherAdmin, ['*']);
        $wrongCampus = $this->postJson(
            "/api/v1/report-withdrawals/{$withdrawalA->public_id}/approve",
            ['lock_version' => $withdrawalA->lock_version, 'confirmed' => true],
        )->assertNotFound();
        $nonexistent = $this->postJson(
            '/api/v1/report-withdrawals/00000000-0000-4000-8000-000000000000/approve',
            ['lock_version' => 0, 'confirmed' => true],
        )->assertNotFound();
        $this->assertSame($wrongCampus->json('error_code'), $nonexistent->json('error_code'));
        $this->assertSame(ReportWithdrawalStatus::PendingReview, $withdrawalA->fresh()->status);
    }

    public function test_document_readiness_failure_does_not_create_success_audit(): void
    {
        $reporter = $this->user('reporter', 'readiness-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $attachment = $withdrawal->currentSignedAttachment();
        $disk = Storage::disk($attachment->disk);
        $firstValidationStream = $disk->readStream($attachment->path);
        $secondValidationStream = $disk->readStream($attachment->path);
        $this->assertIsResource($firstValidationStream);
        $this->assertIsResource($secondValidationStream);
        $diskMock = Mockery::mock($disk)->makePartial();
        $diskMock->shouldReceive('exists')->twice()->andReturnTrue();
        $diskMock->shouldReceive('readStream')
            ->times(3)
            ->andReturn($firstValidationStream, $secondValidationStream, false);
        Storage::shouldReceive('disk')->with($attachment->disk)->andReturn($diskMock);

        Sanctum::actingAs($this->user('admin', 'readiness-admin@example.test'), ['*']);
        $this->get(
            "/api/v1/report-withdrawals/{$withdrawal->public_id}/signed-document/{$attachment->public_id}"
        )->assertConflict();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ReportWithdrawalSignedDocumentReviewed->value,
            'subject_id' => $report->id,
        ]);
    }

    public function test_soft_deleted_or_tampered_context_cannot_be_decided(): void
    {
        $reporter = $this->user('reporter', 'deleted-case-owner@example.test');
        $report = $this->report($reporter);
        $case = $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $admin = $this->user('admin', 'deleted-case-admin@example.test');
        $case->delete();
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $withdrawal->lock_version,
            'rejection_reason' => 'Penolakan ini tidak boleh memutus context yang sudah dihapus.',
            'resubmission_allowed' => false,
        ])->assertConflict()->assertJsonPath('reason_code', 'record_unavailable');
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")->assertNotFound();
        $this->assertSame(ReportWithdrawalStatus::PendingReview, $withdrawal->fresh()->status);

        $case->restore();
        $otherReporter = $this->user('reporter', 'tampered-case-owner@example.test');
        $otherReport = $this->report($otherReporter);
        $otherCase = $this->case($otherReport);
        $withdrawal->forceFill(['case_id' => $otherCase->id])->save();

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $withdrawal->lock_version,
            'confirmed' => true,
        ])->assertConflict()->assertJsonPath('reason_code', 'ownership_changed');
        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertSame(ReportWithdrawalStatus::PendingReview, $withdrawal->fresh()->status);
    }

    public function test_super_admin_detail_projection_is_strictly_metadata_only(): void
    {
        $reporter = $this->user('reporter', 'projection-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        Sanctum::actingAs($this->user('super_admin', 'projection-super@example.test'), ['*']);

        $data = $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")
            ->assertOk()
            ->json('data');

        foreach ([
            'reporter_display_name',
            'reason',
            'rejection_reason',
            'resubmission_allowed',
            'attachments',
            'lock_version',
            'report_status',
            'case_status',
            'created_at',
        ] as $forbiddenField) {
            $this->assertArrayNotHasKey($forbiddenField, $data);
        }
        $this->assertFalse($data['capabilities']['can_review']);
        $this->assertSame($withdrawal->public_id, $data['withdrawal_reference']);
    }

    public function test_rejection_normalizes_unicode_and_keeps_sensitive_text_out_of_monitoring_audit_and_notification(): void
    {
        Notification::fake();
        $reporter = $this->user('reporter', 'unicode-reject-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $admin = $this->user('admin', 'unicode-reject-admin@example.test');
        $reason = str_repeat("\u{00E9}", 20);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $withdrawal->lock_version,
            'rejection_reason' => "\u{00A0}{$reason}\u{2003}",
            'resubmission_allowed' => true,
        ])->assertOk()->assertJsonPath('data.rejection_reason', $reason);

        $stored = DB::table('report_withdrawals')->where('id', $withdrawal->id)->value('rejection_reason');
        $this->assertNotSame($reason, $stored);
        $serializedAudit = json_encode(
            DB::table('audit_logs')->where('action', AuditAction::ReportWithdrawalRejected->value)->get(),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString($reason, $serializedAudit);
        Notification::assertSentTo(
            $reporter,
            WorkflowDatabaseNotification::class,
            function (WorkflowDatabaseNotification $notification) use ($reporter, $reason): bool {
                $payload = $notification->toDatabase($reporter);
                $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

                return ! str_contains($serialized, $reason)
                    && ! array_key_exists('subject_id', $payload)
                    && ! array_key_exists('case_id', $payload);
            },
        );

        Sanctum::actingAs($this->user('super_admin', 'unicode-super@example.test'), ['*']);
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")
            ->assertOk()
            ->assertJsonMissingPath('data.rejection_reason');
        Sanctum::actingAs($reporter, ['*']);
        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.withdrawal_capabilities.latest_withdrawal.rejection_reason', $reason);
    }

    public function test_a_rejected_request_can_only_be_superseded_once_even_after_child_cancellation(): void
    {
        $reporter = $this->user('reporter', 'single-resubmit-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        Sanctum::actingAs($this->user('admin', 'single-resubmit-admin@example.test'), ['*']);
        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $withdrawal->lock_version,
            'rejection_reason' => 'Permohonan boleh diperbaiki satu kali melalui record pengganti.',
            'resubmission_allowed' => true,
        ])->assertOk();

        Sanctum::actingAs($reporter, ['*']);
        $normalizedReason = str_repeat("\u{00F1}", 20);
        $created = $this->postJson("/api/v1/portal/withdrawals/{$withdrawal->public_id}/resubmit", [
            'lock_version' => $withdrawal->fresh()->lock_version,
            'reason' => "\u{00A0}{$normalizedReason}\u{2003}",
        ])->assertCreated()
            ->assertJsonPath('data.reason', $normalizedReason);
        $child = ReportWithdrawal::query()
            ->where('public_id', $created->json('data.withdrawal_reference'))
            ->firstOrFail();
        $this->postJson("/api/v1/portal/withdrawals/{$child->public_id}/cancel", [
            'lock_version' => $child->lock_version,
        ])->assertOk();

        $this->postJson("/api/v1/portal/withdrawals/{$withdrawal->public_id}/resubmit", [
            'lock_version' => $withdrawal->fresh()->lock_version,
            'reason' => 'Pengajuan ulang kedua tidak boleh membuat cabang histori baru.',
        ])->assertConflict()->assertJsonPath('reason_code', 'resubmission_not_allowed');
        $this->assertSame(
            1,
            ReportWithdrawal::query()->where('supersedes_id', $withdrawal->id)->count(),
        );
    }

    public function test_approval_audit_failure_rolls_back_lifecycle_and_notification(): void
    {
        Notification::fake();
        $reporter = $this->user('reporter', 'approval-rollback-owner@example.test');
        $report = $this->report($reporter);
        $case = $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException('audit unavailable'));
        });
        Sanctum::actingAs($this->user('admin', 'approval-rollback-admin@example.test'), ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $withdrawal->lock_version,
            'confirmed' => true,
        ])->assertServerError();

        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertNull($report->fresh()->withdrawn_at);
        $this->assertSame(CaseStatusEnum::Assessment->value, $case->fresh('status')->status->name);
        $this->assertNull($case->fresh()->withdrawn_at);
        $this->assertSame(ReportWithdrawalStatus::PendingReview, $withdrawal->fresh()->status);
        Notification::assertNotSentTo($reporter, WorkflowDatabaseNotification::class);
    }

    public function test_feature_flag_off_keeps_submitted_backlog_reviewable_but_blocks_resubmission(): void
    {
        $reporter = $this->user('reporter', 'flag-backlog-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $attachment = $withdrawal->currentSignedAttachment();
        config()->set('withdrawal.formal_withdrawal_enabled', false);
        $admin = $this->user('admin', 'flag-backlog-admin@example.test');
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/report-withdrawals')
            ->assertOk()
            ->assertJsonPath('data.0.withdrawal_reference', $withdrawal->public_id);
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_reject', true);
        $this->get(
            "/api/v1/report-withdrawals/{$withdrawal->public_id}/signed-document/{$attachment->public_id}"
        )->assertOk();
        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $withdrawal->lock_version,
            'rejection_reason' => 'Permohonan ditolak saat backlog tetap dapat diselesaikan.',
            'resubmission_allowed' => true,
        ])->assertOk();

        Sanctum::actingAs($reporter, ['*']);
        $this->postJson("/api/v1/portal/withdrawals/{$withdrawal->public_id}/resubmit", [
            'lock_version' => $withdrawal->fresh()->lock_version,
            'reason' => 'Pengajuan ulang baru harus mengikuti feature flag yang nonaktif.',
        ])->assertConflict()->assertJsonPath('reason_code', 'feature_disabled');
    }

    public function test_changed_terminal_lifecycle_blocks_approval_and_leaves_request_pending(): void
    {
        $reporter = $this->user('reporter', 'lifecycle-conflict-owner@example.test');
        $report = $this->report($reporter);
        $case = $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $closedStatus = CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail();
        $case->forceFill(['status_code' => $closedStatus->code, 'closed_at' => now()])->save();
        Sanctum::actingAs($this->user('admin', 'lifecycle-conflict-admin@example.test'), ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $withdrawal->lock_version,
            'confirmed' => true,
        ])->assertConflict()->assertJsonPath('reason_code', 'case_state_changed');

        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertSame(CaseStatusEnum::Closed->value, $case->fresh('status')->status->name);
        $this->assertSame(ReportWithdrawalStatus::PendingReview, $withdrawal->fresh()->status);
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_review', false);
    }

    public function test_competing_cancel_reject_and_approve_paths_have_one_unambiguous_winner(): void
    {
        $reporter = $this->user('reporter', 'race-owner@example.test');
        $report = $this->report($reporter);
        $this->case($report);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $initialLock = $withdrawal->lock_version;

        Sanctum::actingAs($reporter, ['*']);
        $this->postJson("/api/v1/portal/withdrawals/{$withdrawal->public_id}/cancel", [
            'lock_version' => $initialLock,
        ])->assertOk();

        Sanctum::actingAs($this->user('admin', 'race-admin@example.test'), ['*']);
        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $initialLock,
            'confirmed' => true,
        ])->assertConflict()->assertJsonPath('reason_code', 'stale_update');
        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/reject", [
            'lock_version' => $initialLock,
            'rejection_reason' => 'Keputusan terlambat tidak boleh mengalahkan pembatalan Pelapor.',
            'resubmission_allowed' => false,
        ])->assertConflict()->assertJsonPath('reason_code', 'stale_update');

        $this->assertSame(ReportWithdrawalStatus::Cancelled, $withdrawal->fresh()->status);
        $this->assertSame(ReportStatus::Forwarded->value, $report->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ReportMarkedWithdrawn->value,
            'subject_id' => $report->id,
        ]);
    }

    public function test_approval_without_case_never_creates_case_and_historical_document_remains_reviewable(): void
    {
        $reporter = $this->user('reporter', 'no-case-owner@example.test');
        $report = $this->report($reporter);
        $withdrawal = $this->pendingWithdrawal($reporter, $report);
        $attachment = $withdrawal->currentSignedAttachment();
        $admin = $this->user('admin', 'no-case-admin@example.test');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/report-withdrawals/{$withdrawal->public_id}/approve", [
            'lock_version' => $withdrawal->lock_version,
            'confirmed' => true,
        ])->assertOk();

        $this->assertDatabaseMissing('cases', ['report_id' => $report->id]);
        $this->getJson("/api/v1/report-withdrawals/{$withdrawal->public_id}")
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_review', false)
            ->assertJsonPath('data.capabilities.can_view_signed_document', true);
        $this->get(
            "/api/v1/report-withdrawals/{$withdrawal->public_id}/signed-document/{$attachment->public_id}"
        )->assertOk();
    }

    public function test_review_routes_use_auth_no_store_uuid_constraints_and_named_throttles(): void
    {
        $expected = [
            'report-withdrawals.index' => 'throttle:withdrawal.review.read',
            'report-withdrawals.show' => 'throttle:withdrawal.review.read',
            'report-withdrawals.signed-document' => 'throttle:withdrawal.review.document',
            'report-withdrawals.approve' => 'throttle:withdrawal.review.mutate',
            'report-withdrawals.reject' => 'throttle:withdrawal.review.mutate',
            'portal.withdrawals.resubmit' => 'throttle:reporter.withdrawal.create',
        ];

        foreach ($expected as $routeName => $throttle) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('private.no-store', $middleware);
            $this->assertContains($throttle, $middleware);
        }

        $this->getJson('/api/v1/report-withdrawals')->assertUnauthorized();
    }

    public function test_review_support_migration_rolls_back_and_reapplies_on_sqlite(): void
    {
        $migration = require database_path(
            'migrations/2026_07_24_030000_add_report_withdrawal_review_support.php'
        );

        $this->assertTrue(Schema::hasIndex(
            'report_withdrawals',
            'report_withdrawals_review_queue_idx'
        ));
        $this->assertTrue(Schema::hasIndex(
            'report_withdrawals',
            'report_withdrawals_supersedes_unique'
        ));
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-28']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-29']);

        $migration->down();

        $this->assertFalse(Schema::hasIndex(
            'report_withdrawals',
            'report_withdrawals_review_queue_idx'
        ));
        $this->assertFalse(Schema::hasIndex(
            'report_withdrawals',
            'report_withdrawals_supersedes_unique'
        ));
        $this->assertDatabaseMissing('notification_types', ['code' => 'NOTIF-28']);
        $this->assertDatabaseMissing('notification_types', ['code' => 'NOTIF-29']);

        $migration->up();

        $this->assertTrue(Schema::hasIndex(
            'report_withdrawals',
            'report_withdrawals_review_queue_idx'
        ));
        $this->assertTrue(Schema::hasIndex(
            'report_withdrawals',
            'report_withdrawals_supersedes_unique'
        ));
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-28']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-29']);
    }

    private function pendingWithdrawal(User $reporter, Report $report): ReportWithdrawal
    {
        Sanctum::actingAs($reporter, ['*']);
        $publicId = $this->postJson("/api/v1/portal/reports/{$report->registration_number}/withdrawals", [
            'reason' => 'Alasan pencabutan formal yang memenuhi batas minimum.',
        ])->assertCreated()->json('data.withdrawal_reference');
        $this->get("/api/v1/portal/withdrawals/{$publicId}/draft-document")->assertOk();
        $withdrawal = ReportWithdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $this->post("/api/v1/portal/withdrawals/{$publicId}/signed-document", [
            'file' => UploadedFile::fake()->createWithContent(
                'signed-statement.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF",
            ),
            'lock_version' => $withdrawal->lock_version,
        ], ['Accept' => 'application/json'])->assertCreated();
        $withdrawal->refresh();
        $this->postJson("/api/v1/portal/withdrawals/{$publicId}/submit", [
            'lock_version' => $withdrawal->lock_version,
        ])->assertOk();

        return $withdrawal->fresh('attachments');
    }

    private function user(string $roleCode, string $email, ?University $campus = null): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'university_id' => $roleCode === 'super_admin' ? null : ($campus ?? $this->campus)->id,
            'name' => $roleCode.' User',
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function report(User $reporter): Report
    {
        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.strtoupper(substr(hash('sha256', $reporter->email), 0, 16)),
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi aman untuk pengujian review pencabutan.',
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Lokasi kejadian',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'forwarded_at' => now(),
        ]);
    }

    private function case(Report $report): CaseRecord
    {
        $status = CaseStatus::query()->where('name', CaseStatusEnum::Assessment->value)->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.substr(hash('sha256', $report->registration_number), 0, 12),
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
        ]);
    }
}
