<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Enums\ReportStatus;
use App\Models\BreakGlassRequest;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\Investigation;
use App\Models\Permission;
use App\Models\Recommendation;
use App\Models\Role;
use App\Models\User;
use App\Models\Report;
use App\Services\AuditLogService;
use App\Services\BusinessDayClock;
use App\Services\OversightProjection;
use Carbon\CarbonImmutable;
use Database\Seeders\Foundation\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditOversightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RbacSeeder::class, MasterDataSeeder::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_oversight_permissions_belong_only_to_super_admin_and_stale_permission_cannot_grant_access(): void
    {
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();

        foreach (['system.audit_log.oversight', 'system.audit_log.export'] as $permissionCode) {
            $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();
            $this->assertTrue($superAdminRole->permissions()->whereKey($permission->id)->exists());
            $this->assertFalse($adminRole->permissions()->whereKey($permission->id)->exists());
            $adminRole->permissions()->syncWithoutDetaching([$permission->id => ['created_at' => now()]]);
        }

        $admin = $this->makeUser('admin', 'Admin Stale Permission');
        $this->actingAsApi($admin);

        $this->getJson('/api/v1/audit-logs/summary')->assertForbidden();
        $this->getJson('/api/v1/audit-logs/oversight')->assertForbidden();
        $this->getJson('/api/v1/audit-logs/export')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AuditExport->value,
            'result' => AuditResult::Denied->value,
            'actor_role_code' => 'admin',
        ]);
    }

    public function test_queue_card_and_filtered_list_use_the_same_projection_and_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00');
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        $this->makeReport(ReportStatus::Submitted->value, now()->subDay());
        $this->makeReport(ReportStatus::NeedInfo->value, now()->subDays(3));
        $this->makeCase('forwarded', activeAssignment: false, startedAt: now()->subDays(2));
        $this->actingAsApi($superAdmin);

        $summary = $this->getJson('/api/v1/audit-logs/summary')
            ->assertOk()
            ->assertJsonPath('data.queues.waiting_admin', 2)
            ->assertJsonPath('data.queues.waiting_satgas', 0);
        $cutoff = (string) $summary->json('data.generated_at');

        $response = $this->getJson('/api/v1/audit-logs/oversight?'.http_build_query([
            'queue' => 'waiting_admin',
            'cutoff' => $cutoff,
        ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.actor_id')
            ->assertJsonMissingPath('data.0.subject_id');

        $this->assertSame(
            collect($response->json('data'))->pluck('reference')->sort()->values()->all(),
            collect([
                Report::query()->where('status', ReportStatus::Submitted->value)->value('registration_number'),
                CaseRecord::query()->value('case_number'),
            ])->sort()->values()->all(),
        );
    }

    public function test_locked_queue_ownership_excludes_paused_reports_historical_assignments_and_recovery(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00');
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        $satgas = $this->makeUser('satgas_ppks', 'Satgas Aktif');

        $activeCase = $this->makeCase('investigation', activeAssignment: true, startedAt: now()->subDays(2), satgas: $satgas);
        $historicalCase = $this->makeCase('investigation', activeAssignment: false, startedAt: now()->subDays(2), satgas: $satgas, historicalAssignment: true);
        $recoveryCase = $this->makeCase('recovery', activeAssignment: true, startedAt: now()->subDays(2), satgas: $satgas);
        $leaderCase = $this->makeRecommendationCase('submitted_to_leader', $satgas);
        $decisionCase = $this->makeRecommendationCase('accepted', $satgas);

        $this->actingAsApi($superAdmin);
        $response = $this->getJson('/api/v1/audit-logs/oversight?per_page=50')->assertOk();
        $items = collect($response->json('data'))->keyBy('reference');

        $this->assertSame('waiting_satgas', $items[$activeCase->case_number]['queue']);
        $this->assertFalse($items->has($historicalCase->case_number));
        $this->assertFalse($items->has($recoveryCase->case_number));
        $this->assertSame('waiting_leader', $items[$leaderCase->case_number]['queue']);
        $this->assertSame('waiting_admin', $items[$decisionCase->case_number]['queue']);
    }

    public function test_business_day_clock_uses_asia_jakarta_weekdays_and_partial_days(): void
    {
        $clock = app(BusinessDayClock::class);
        $start = CarbonImmutable::parse('2026-07-17 12:00:00', 'Asia/Jakarta');
        $end = CarbonImmutable::parse('2026-07-20 12:00:00', 'Asia/Jakarta');

        $this->assertSame(86400, $clock->elapsedSeconds($start, $end));
        $this->assertSame('2026-07-20 12:00:00', $clock->dueAt($start, 86400)->format('Y-m-d H:i:s'));
    }

    public function test_history_rejects_more_than_ninety_days_and_export_neutralizes_formulas(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        $formulaValues = ['=2+2', '+2+2', '-2+2', '@SUM(A1)', "\t=2+2", "\r=2+2"];

        foreach ($formulaValues as $formulaValue) {
            app(AuditLogService::class)->record(
                action: AuditAction::AuthLogin,
                category: AuditCategory::Auth,
                actor: $superAdmin,
                metadata: ['authentication_method' => 'password'],
                requestId: $formulaValue,
            );
        }
        $this->actingAsApi($superAdmin);

        $this->getJson('/api/v1/audit-logs?date_from=2026-01-01&date_to=2026-04-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');

        $response = $this->get('/api/v1/audit-logs/export?'.http_build_query([
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertOk();

        $content = (string) $response->getContent();
        foreach ($formulaValues as $formulaValue) {
            $this->assertStringContainsString("'{$formulaValue}", $content);
        }
        $this->assertStringNotContainsString(AuditAction::AuditExport->value, $content);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AuditExport->value,
            'result' => AuditResult::Succeeded->value,
        ]);
    }

    public function test_export_fails_explicitly_above_ten_thousand_rows_without_truncating(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        $now = now()->format('Y-m-d H:i:s');

        foreach (array_chunk(range(1, 10001), 500) as $chunk) {
            DB::table('audit_logs')->insert(array_map(static fn (int $number): array => [
                'public_id' => (string) Str::uuid(),
                'actor_id' => null,
                'actor_kind' => 'system',
                'actor_role_code' => null,
                'actor_display_name_safe' => null,
                'request_id' => null,
                'action' => AuditAction::SecurityAccessDenied->value,
                'category' => AuditCategory::Security->value,
                'severity' => AuditSeverity::Info->value,
                'result' => AuditResult::Denied->value,
                'subject_type' => null,
                'subject_id' => null,
                'subject_kind' => null,
                'subject_reference_safe' => null,
                'is_elevated_access' => false,
                'metadata' => json_encode(['operation_code' => 'audit.oversight', 'reason_code' => "bulk-{$number}"]),
                'before_changes' => null,
                'after_changes' => null,
                'expires_at' => null,
                'created_at' => $now,
            ], $chunk));
        }

        $this->actingAsApi($superAdmin);
        $this->getJson('/api/v1/audit-logs/export?'.http_build_query([
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'audit_export.too_many_rows');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AuditExport->value,
            'result' => AuditResult::Failed->value,
        ]);
    }

    public function test_projection_has_no_per_item_queries_and_required_indexes_exist(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00');
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        foreach (range(1, 8) as $index) {
            $this->makeReport(ReportStatus::Submitted->value, now()->subHours($index));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $summary = app(OversightProjection::class)->summary($superAdmin, CarbonImmutable::now());
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(8, $summary['queues']['waiting_admin']);
        $this->assertLessThanOrEqual(2, $queryCount);

        $indexes = collect(Schema::getIndexes('audit_logs'))->pluck('name');
        $this->assertContains('audit_logs_category_severity_created_id_index', $indexes);
        $this->assertContains('audit_logs_actor_kind_created_id_index', $indexes);
        $this->assertContains('audit_logs_elevated_created_id_index', $indexes);
    }

    public function test_assigned_forwarded_case_enters_satgas_queue_and_urgency_uses_seventy_five_percent(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 12:00:00');
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        $satgas = $this->makeUser('satgas_ppks', 'Satgas Aktif');
        $case = $this->makeCase(
            'forwarded',
            activeAssignment: true,
            startedAt: now()->subWeekdays(3)->subHours(18),
            satgas: $satgas,
        );
        $this->actingAsApi($superAdmin);

        $this->getJson('/api/v1/audit-logs/oversight?queue=waiting_satgas&urgency=attention&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference', $case->case_number)
            ->assertJsonPath('data.0.urgency', 'attention');
    }

    public function test_denied_export_creates_one_action_specific_terminal_event(): void
    {
        $admin = $this->makeUser('admin', 'Admin');
        $this->actingAsApi($admin);

        $this->getJson('/api/v1/audit-logs/export')->assertForbidden();

        $this->assertSame(1, AuditLog::query()
            ->where('actor_id', $admin->id)
            ->where('action', AuditAction::AuditExport->value)
            ->where('result', AuditResult::Denied->value)
            ->count());
        $securityDenials = AuditLog::query()
            ->where('actor_id', $admin->id)
            ->where('action', AuditAction::SecurityAccessDenied->value)
            ->get();
        $this->assertSame(0, $securityDenials->count(), $securityDenials->toJson());
    }

    public function test_every_queue_and_urgency_filter_matches_its_summary_card(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 12:00:00');
        $superAdmin = $this->makeUser('super_admin', 'Super Admin');
        $admin = $this->makeUser('admin', 'Admin');
        $satgas = $this->makeUser('satgas_ppks', 'Satgas');

        $this->makeReport(ReportStatus::Submitted->value, now()->subHour());
        $this->makeCase(
            'forwarded',
            activeAssignment: true,
            startedAt: now()->subWeekdays(3)->subHours(18),
            satgas: $satgas,
        );
        $this->makeRecommendationCase('submitted_to_leader', $satgas);
        BreakGlassRequest::query()->create([
            'requestor_id' => $admin->id,
            'report_id' => $this->makeReport(ReportStatus::Submitted->value, now())->id,
            'reason_category' => 'safety_emergency',
            'reason' => 'Documented emergency access reason for oversight queue parity verification.',
            'status' => BreakGlassRequest::STATUS_PENDING,
            'requested_at' => now()->subHour(),
        ]);
        app(AuditLogService::class)->record(
            action: AuditAction::SecurityAccessDenied,
            category: AuditCategory::Security,
            severity: AuditSeverity::Critical,
            metadata: ['operation_code' => 'audit.oversight', 'reason_code' => 'parity_test'],
            result: AuditResult::Denied,
        )->forceFill(['created_at' => now()->subWeekdays(2)])->save();

        $this->actingAsApi($superAdmin);
        foreach (OversightProjection::URGENCIES as $urgency) {
            $summary = $this->getJson('/api/v1/audit-logs/summary?urgency='.$urgency)->assertOk();
            $cutoff = (string) $summary->json('data.generated_at');

            foreach (OversightProjection::QUEUES as $queue) {
                $this->getJson('/api/v1/audit-logs/oversight?'.http_build_query([
                    'queue' => $queue,
                    'urgency' => $urgency,
                    'cutoff' => $cutoff,
                    'per_page' => 50,
                ]))
                    ->assertOk()
                    ->assertJsonPath('meta.total', $summary->json("data.queues.{$queue}"));
            }
        }
    }

    private function makeRecommendationCase(string $recommendationStatus, User $satgas): CaseRecord
    {
        $case = $this->makeCase('recommendation', activeAssignment: true, startedAt: now()->subDays(2), satgas: $satgas);
        $investigationStatus = DB::table('investigation_statuses')->where('name', 'completed')->value('code');
        $investigation = Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus,
            'plan_summary' => 'Safe plan',
            'findings' => 'Safe findings',
            'conclusion' => 'Safe conclusion',
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(2),
        ]);
        $statusCode = DB::table('recommendation_statuses')->where('name', $recommendationStatus)->value('code');
        Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $statusCode,
            'conclusion' => 'Safe conclusion',
            'recommended_actions' => 'Safe recommendation',
            'submitted_at' => now()->subDay(),
            'approved_at' => $recommendationStatus === 'accepted' ? now()->subDay() : null,
            'approved_by' => $recommendationStatus === 'accepted' ? $this->makeUser('super_admin', 'Leader')->id : null,
        ]);

        return $case;
    }

    private function makeCase(
        string $statusName,
        bool $activeAssignment,
        \DateTimeInterface $startedAt,
        ?User $satgas = null,
        bool $historicalAssignment = false,
    ): CaseRecord {
        $report = $this->makeReport(ReportStatus::Forwarded->value, $startedAt);
        $status = DB::table('case_statuses')->where('name', $statusName)->first();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.Str::upper(Str::random(12)),
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => $startedAt,
            'assessment_at' => $startedAt,
            'investigation_started_at' => $startedAt,
            'recommendation_at' => $statusName === 'recommendation' ? $startedAt : null,
        ]);

        if ($activeAssignment || $historicalAssignment) {
            $satgas ??= $this->makeUser('satgas_ppks', 'Satgas '.Str::random(5));
            $assigner = $this->makeUser('admin', 'Admin '.Str::random(5));
            CaseAssignment::query()->create([
                'case_id' => $case->id,
                'satgas_id' => $satgas->id,
                'assigned_by' => $assigner->id,
                'is_lead' => true,
                'is_active' => $activeAssignment,
                'assigned_at' => $startedAt,
                'unassigned_at' => $historicalAssignment ? now()->subDay() : null,
            ]);
        }

        return $case;
    }

    private function makeReport(string $status, \DateTimeInterface $submittedAt): Report
    {
        return Report::query()->create([
            'registration_number' => 'SLP-'.Str::upper(Str::random(12)),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Encrypted chronology',
            'incident_date' => $submittedAt,
            'incident_time' => '10:00',
            'incident_location' => 'Encrypted location',
            'status' => $status,
            'submitted_at' => $submittedAt,
            'forwarded_at' => $status === ReportStatus::Forwarded->value ? $submittedAt : null,
        ]);
    }

    private function makeUser(string $roleCode, string $name): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => $name,
            'email' => Str::uuid().'@example.test',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
