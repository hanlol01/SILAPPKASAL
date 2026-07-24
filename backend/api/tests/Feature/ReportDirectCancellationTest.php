<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\ReportEvidenceSubmission;
use App\Models\ReportWithdrawal;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use App\Services\AuditLogService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReportDirectCancellationTest extends TestCase
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
        config()->set('withdrawal.formal_withdrawal_enabled', false);
    }

    public function test_owner_can_cancel_submitted_complaint_atomically_with_safe_response_and_capability(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        $reason = 'Saya membatalkan pengaduan sebelum penanganan dimulai.';

        Sanctum::actingAs($reporter, ['*']);

        $response = $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => $reason]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.report_status', ReportStatus::Cancelled->value)
            ->assertJsonPath('data.portal_status', 'cancelled_by_reporter')
            ->assertJsonPath('data.capabilities.can_cancel', false)
            ->assertJsonPath('data.capabilities.cancellation_block_reason_code', 'terminal_state')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.reason')
            ->assertJsonMissingPath('data.requester_id')
            ->assertJsonMissingPath('data.audit');
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );

        $report->refresh();
        $this->assertSame(ReportStatus::Cancelled->value, $report->status);
        $this->assertNotNull($report->cancelled_at);
        $this->assertNull($report->case);

        $withdrawal = ReportWithdrawal::query()->sole();
        $this->assertSame(ReportWithdrawalRequestType::EarlyCancellation, $withdrawal->request_type);
        $this->assertSame(ReportWithdrawalStatus::Completed, $withdrawal->status);
        $this->assertSame(ReportStatus::Submitted->value, $withdrawal->previous_report_status);
        $this->assertSame($reason, $withdrawal->reason);
        $this->assertNotNull($withdrawal->completed_at);
        $this->assertFalse($withdrawal->resubmission_allowed);
        $this->assertNotSame(
            $reason,
            DB::table('report_withdrawals')->where('id', $withdrawal->id)->value('reason')
        );
    }

    public function test_audit_excludes_reason_and_notification_targets_only_authorized_campus_admin(): void
    {
        Notification::fake();
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $otherCampus = University::query()->where('code', 'DEMO-ST')->firstOrFail();
        $otherAdmin = $this->makeUser('admin', 'other-admin@example.test', $otherCampus);
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        $reason = 'Alasan privat yang tidak boleh masuk ke audit atau notifikasi.';

        Sanctum::actingAs($reporter, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => $reason]
        )->assertOk();

        $audit = DB::table('audit_logs')
            ->where('action', AuditAction::ReportDirectCancellationCompleted->value)
            ->first();
        $this->assertNotNull($audit);
        $this->assertNotNull($audit->request_id);
        $this->assertStringNotContainsString($reason, json_encode($audit, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString($report->registration_number, (string) $audit->metadata);

        Notification::assertSentTo(
            $admin,
            WorkflowDatabaseNotification::class,
            function (WorkflowDatabaseNotification $notification) use ($admin, $report, $reason): bool {
                $payload = $notification->toDatabase($admin);

                return $payload['notification_type_code'] === 'NOTIF-25'
                    && $payload['registration_number'] === $report->registration_number
                    && ! str_contains(json_encode($payload, JSON_THROW_ON_ERROR), $reason);
            }
        );
        Notification::assertNotSentTo($satgas, WorkflowDatabaseNotification::class);
        Notification::assertNotSentTo($otherAdmin, WorkflowDatabaseNotification::class);
    }

    public function test_supporting_attachment_and_authenticated_anonymous_owner_remain_eligible(): void
    {
        $reporter = $this->makeUser('reporter', 'anonymous-owner@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted, 'anonymous');
        ReportEvidenceSubmission::query()->create([
            'uuid' => (string) Str::uuid(),
            'report_id' => $report->id,
            'uploaded_by' => $reporter->id,
            'original_filename' => 'support.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'checksum_sha256' => hash('sha256', 'support'),
            'storage_disk' => 'local',
            'storage_path' => "report-evidence/{$report->id}/support.pdf",
            'uploaded_at' => now(),
        ]);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.withdrawal_capabilities.can_cancel', true);

        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => 'Pembatalan dilakukan sebelum penanganan dimulai.']
        )->assertOk();

        $this->assertDatabaseCount('report_evidence_submissions', 1);
        $this->assertDatabaseCount('cases', 0);
    }

    public function test_admin_reading_detail_does_not_consume_the_direct_cancellation_window(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $admin = $this->makeUser('admin', 'admin@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);

        Sanctum::actingAs($admin, ['*']);
        $this->getJson("/api/v1/reports/{$report->id}")->assertOk();
        $this->assertSame(ReportStatus::Submitted->value, $report->fresh()->status);

        Sanctum::actingAs($reporter, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => 'Pembatalan dilakukan sebelum penanganan dimulai.']
        )->assertOk();
    }

    public function test_processed_terminal_or_case_backed_reports_are_rejected_without_partial_write(): void
    {
        $statuses = [
            ReportStatus::UnderReview,
            ReportStatus::NeedInfo,
            ReportStatus::Forwarded,
            ReportStatus::Rejected,
            ReportStatus::Cancelled,
            ReportStatus::Withdrawn,
        ];

        foreach ($statuses as $index => $status) {
            $reporter = $this->makeUser('reporter', "reporter-{$index}@example.test");
            Sanctum::actingAs($reporter, ['*']);
            $report = $this->makeReport(
                $reporter,
                $status,
                'confidential',
                'SLP-20260724-'.str_pad((string) ($index + 10), 4, '0', STR_PAD_LEFT)
            );

            $this->postJson(
                "/api/v1/portal/reports/{$report->registration_number}/cancel",
                ['reason' => 'Alasan pembatalan yang memenuhi panjang minimum.']
            )->assertConflict();
        }

        $reporter = $this->makeUser('reporter', 'case-backed@example.test');
        Sanctum::actingAs($reporter, ['*']);
        $caseBacked = $this->makeReport(
            $reporter,
            ReportStatus::Submitted,
            'confidential',
            'SLP-20260724-0099'
        );
        $this->makeCase($caseBacked);

        $this->postJson(
            "/api/v1/portal/reports/{$caseBacked->registration_number}/cancel",
            ['reason' => 'Alasan pembatalan yang memenuhi panjang minimum.']
        )->assertConflict();

        $this->assertDatabaseCount('report_withdrawals', 0);
        $this->assertSame(ReportStatus::Submitted->value, $caseBacked->fresh()->status);
    }

    public function test_wrong_owner_legacy_owner_and_non_reporter_or_inactive_actor_are_denied(): void
    {
        $owner = $this->makeUser('reporter', 'owner@example.test');
        $other = $this->makeUser('reporter', 'other@example.test');
        $report = $this->makeReport($owner, ReportStatus::Submitted);
        $legacy = $this->makeReport(
            null,
            ReportStatus::Submitted,
            'anonymous',
            'SLP-20260724-0002'
        );

        Sanctum::actingAs($other, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => 'Alasan pembatalan yang memenuhi panjang minimum.']
        )->assertNotFound();
        $this->postJson(
            "/api/v1/portal/reports/{$legacy->registration_number}/cancel",
            ['reason' => 'Alasan pembatalan yang memenuhi panjang minimum.']
        )->assertNotFound();

        foreach (['admin', 'satgas_ppks', 'super_admin'] as $index => $role) {
            $actor = $this->makeUser($role, "{$role}@example.test");
            Sanctum::actingAs($actor, ['*']);
            $this->postJson(
                "/api/v1/portal/reports/{$report->registration_number}/cancel",
                ['reason' => 'Alasan pembatalan yang memenuhi panjang minimum.']
            )->assertForbidden();
        }

        $inactive = $this->makeUser('reporter', 'inactive@example.test', $this->campus, false);
        $inactiveReport = $this->makeReport(
            $inactive,
            ReportStatus::Submitted,
            'confidential',
            'SLP-20260724-0003'
        );
        Sanctum::actingAs($inactive, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$inactiveReport->registration_number}/cancel",
            ['reason' => 'Alasan pembatalan yang memenuhi panjang minimum.']
        )->assertForbidden();

        $this->assertDatabaseCount('report_withdrawals', 0);
    }

    public function test_feature_flag_validation_duplicate_and_guest_contracts_are_fail_closed(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        $url = "/api/v1/portal/reports/{$report->registration_number}/cancel";

        $this->postJson($url, ['reason' => str_repeat('x', 20)])->assertUnauthorized();

        Sanctum::actingAs($reporter, ['*']);
        config()->set('withdrawal.early_cancellation_enabled', false);
        $this->postJson($url, ['reason' => str_repeat('x', 20)])
            ->assertConflict()
            ->assertJsonPath('error_code', 'report_cancellation_feature_disabled');

        config()->set('withdrawal.early_cancellation_enabled', true);
        $this->postJson($url, ['reason' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->postJson($url, ['reason' => str_repeat('x', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson($url, ['reason' => str_repeat('x', 20)])->assertOk();
        $this->postJson($url, ['reason' => str_repeat('y', 20)])->assertConflict();
        $this->assertDatabaseCount('report_withdrawals', 1);
    }

    #[DataProvider('validNormalizedReasons')]
    public function test_reason_is_normalized_before_boundary_validation_and_encrypted_storage(
        string $input,
        string $expected,
    ): void {
        $reporter = $this->makeUser('reporter', 'normalized-'.hash('sha256', $expected).'@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        Sanctum::actingAs($reporter, ['*']);

        $response = $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => $input],
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.reason');
        $withdrawal = ReportWithdrawal::query()->sole();
        $this->assertSame($expected, $withdrawal->reason);
        $this->assertNotSame(
            $expected,
            DB::table('report_withdrawals')->where('id', $withdrawal->id)->value('reason'),
        );
    }

    #[DataProvider('invalidNormalizedReasons')]
    public function test_reason_validation_rejects_invalid_type_or_normalized_boundary(mixed $reason): void
    {
        $reporter = $this->makeUser('reporter', 'invalid-reason@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        Sanctum::actingAs($reporter, ['*']);

        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => $reason],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseCount('report_withdrawals', 0);
    }

    public function test_active_withdrawal_blocks_cancellation_and_partial_unique_index_enforces_one_active_row(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        $this->makeWithdrawal($report, $reporter, ReportWithdrawalStatus::Draft);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.withdrawal_capabilities.can_cancel', false)
            ->assertJsonPath(
                'data.withdrawal_capabilities.cancellation_block_reason_code',
                'active_request'
            );

        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => str_repeat('x', 20)]
        )->assertConflict();

        $this->expectException(QueryException::class);
        $this->makeWithdrawal($report, $reporter, ReportWithdrawalStatus::PendingReview);
    }

    public function test_audit_failure_rolls_back_report_and_withdrawal_without_notification(): void
    {
        Notification::fake();
        $reporter = $this->makeUser('reporter', 'rollback@example.test');
        $admin = $this->makeUser('admin', 'rollback-admin@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new RuntimeException('audit unavailable'));
        });

        Sanctum::actingAs($reporter, ['*']);
        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => str_repeat('x', 20)]
        )->assertServerError();

        $report->refresh();
        $this->assertSame(ReportStatus::Submitted->value, $report->status);
        $this->assertNull($report->cancelled_at);
        $this->assertDatabaseCount('report_withdrawals', 0);
        Notification::assertNotSentTo($admin, WorkflowDatabaseNotification::class);
    }

    public function test_cancelled_status_is_not_active_or_completed_and_timeline_is_reporter_safe(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        Sanctum::actingAs($reporter, ['*']);

        $this->postJson(
            "/api/v1/portal/reports/{$report->registration_number}/cancel",
            ['reason' => str_repeat('x', 20)]
        )->assertOk();

        $this->getJson('/api/v1/portal/summary')
            ->assertOk()
            ->assertJsonPath('data.total_reports', 1)
            ->assertJsonPath('data.active_reports', 0)
            ->assertJsonPath('data.completed_reports', 0);

        $this->getJson('/api/v1/portal/reports')
            ->assertOk()
            ->assertJsonPath('data.0.portal_status', 'cancelled_by_reporter');

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/timeline")
            ->assertOk()
            ->assertJsonPath('data.portal_status', 'cancelled_by_reporter')
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonFragment(['stage' => 'pengaduan_dibatalkan']);
    }

    public function test_route_has_auth_no_store_and_named_throttle_middleware(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'portal.reports.cancel');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('private.no-store', $middleware);
        $this->assertContains('throttle:reporter.cancellation', $middleware);
    }

    public function test_sqlite_foundation_migration_rolls_back_and_reapplies_without_rewriting_reports(): void
    {
        $reporter = $this->makeUser('reporter', 'migration@example.test');
        $report = $this->makeReport($reporter, ReportStatus::Submitted);
        $snapshot = DB::table('reports')->where('id', $report->id)
            ->first(['registration_number', 'status', 'submitted_at']);
        $migration = require database_path(
            'migrations/2026_07_24_000000_create_report_withdrawal_foundation.php'
        );

        $migration->down();

        $this->assertFalse(Schema::hasTable('report_withdrawals'));
        $this->assertFalse(Schema::hasTable('report_withdrawal_attachments'));
        $this->assertFalse(Schema::hasColumn('reports', 'cancelled_at'));
        $this->assertFalse(Schema::hasColumn('cases', 'withdrawn_at'));
        $this->assertEquals(
            $snapshot,
            DB::table('reports')->where('id', $report->id)
                ->first(['registration_number', 'status', 'submitted_at'])
        );

        $migration->up();

        $this->assertTrue(Schema::hasTable('report_withdrawals'));
        $this->assertTrue(Schema::hasTable('report_withdrawal_attachments'));
        $this->assertTrue(Schema::hasColumn('reports', 'cancelled_at'));
        $this->assertTrue(Schema::hasColumn('reports', 'withdrawn_at'));
        $this->assertTrue(Schema::hasColumn('cases', 'withdrawn_at'));
        $this->assertEquals(
            $snapshot,
            DB::table('reports')->where('id', $report->id)
                ->first(['registration_number', 'status', 'submitted_at'])
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
        ReportStatus $status,
        string $reportType = 'confidential',
        string $registrationNumber = 'SLP-20260724-0001',
    ): Report {
        return Report::query()->create([
            'reporter_id' => $reporter?->id,
            'registration_number' => $registrationNumber,
            'tracking_code' => $reportType === 'anonymous'
                ? strtoupper(substr(hash('sha256', $registrationNumber), 0, 16))
                : null,
            'report_type' => $reportType,
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi aman untuk pengujian pembatalan langsung.',
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Lokasi kejadian',
            'status' => $status->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'reviewed_at' => $status === ReportStatus::Submitted ? null : now(),
            'forwarded_at' => $status === ReportStatus::Forwarded ? now() : null,
            'cancelled_at' => $status === ReportStatus::Cancelled ? now() : null,
            'withdrawn_at' => $status === ReportStatus::Withdrawn ? now() : null,
        ]);
    }

    private function makeCase(Report $report): CaseRecord
    {
        $status = CaseStatus::query()
            ->where('name', CaseStatusEnum::Forwarded->value)
            ->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.substr(hash('sha256', $report->registration_number), 0, 12),
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
        ]);
    }

    private function makeWithdrawal(
        Report $report,
        User $requester,
        ReportWithdrawalStatus $status,
    ): ReportWithdrawal {
        return ReportWithdrawal::query()->create([
            'report_id' => $report->id,
            'requester_id' => $requester->id,
            'request_type' => ReportWithdrawalRequestType::FormalWithdrawal,
            'status' => $status,
            'reason' => str_repeat('x', 20),
            'previous_report_status' => $report->status,
            'resubmission_allowed' => false,
            'lock_version' => 0,
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validNormalizedReasons(): array
    {
        return [
            'twenty characters with trailing Unicode space' => [
                str_repeat('a', 20)."\u{00A0}",
                str_repeat('a', 20),
            ],
            'two thousand characters with trailing spaces' => [
                str_repeat('b', 2000).'   ',
                str_repeat('b', 2000),
            ],
            'twenty Unicode characters' => [
                str_repeat('é', 20),
                str_repeat('é', 20),
            ],
            'internal newline is preserved' => [
                '  Baris pertama aman'."\n".'baris kedua tetap ada.  ',
                'Baris pertama aman'."\n".'baris kedua tetap ada.',
            ],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNormalizedReasons(): array
    {
        return [
            'nineteen characters with trailing spaces' => [str_repeat('a', 19).'   '],
            'two thousand and one characters' => [str_repeat('b', 2001)],
            'whitespace only' => [" \t\u{00A0}\n "],
            'array' => [['not', 'text']],
            'object' => [(object) ['not' => 'text']],
            'nineteen Unicode characters with whitespace' => [str_repeat('é', 19)."\u{2003}"],
        ];
    }
}
