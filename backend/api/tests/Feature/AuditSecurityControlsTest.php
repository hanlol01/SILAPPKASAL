<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CampusMasterDataService;
use App\Services\ReporterSelfServiceService;
use App\Services\UserManagementService;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AuditSecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Cache::flush();
    }

    public function test_server_request_id_ignores_untrusted_header_and_correlates_login_failure(): void
    {
        config()->set('audit.login_failure_fingerprint.keys.v1', 'dedicated-test-hmac-secret');
        config()->set('audit.login_failure_retention_days', 30);

        $response = $this->withHeaders([
            'X-Request-ID' => 'attacker-controlled-id',
            'X-Forwarded-For' => '203.0.113.45',
        ])->postJson('/api/v1/auth/login', [
            'identifier' => 'victim@example.test',
            'password' => 'WrongPassword123',
        ])->assertUnauthorized();

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertTrue(Str::isUuid($requestId));
        $this->assertNotSame('attacker-controlled-id', $requestId);

        $audit = AuditLog::query()->where('action', AuditAction::AuthLoginFailed->value)->firstOrFail();
        $this->assertSame($requestId, $audit->request_id);
        $this->assertSame(AuditResult::Failed->value, $audit->result);
        $this->assertNull($audit->actor_id);
        $this->assertNotNull($audit->expires_at);
        $this->assertTrue($audit->expires_at->equalTo($audit->created_at->copy()->addDays(7)));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $audit->metadata['identifier_fingerprint']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $audit->metadata['network_fingerprint']);

        $raw = json_encode(DB::table('audit_logs')->first());
        $this->assertStringNotContainsString('victim@example.test', $raw);
        $this->assertStringNotContainsString('203.0.113.45', $raw);
    }

    public function test_missing_fingerprint_key_never_breaks_login_or_creates_unsafe_fallback(): void
    {
        config()->set('audit.login_failure_fingerprint.keys.v1', null);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'unknown@example.test',
            'password' => 'WrongPassword123',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_successful_login_and_audit_share_one_transaction(): void
    {
        $user = $this->makeUser('admin', 'Admin', 'admin@example.test');
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'SecurePass123',
        ])->assertServerError();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_user_management_mutation_rolls_back_when_audit_fails(): void
    {
        $actor = $this->makeUser('admin', 'Admin', 'admin-manager@example.test');
        $target = $this->makeUser('reporter', 'Pelapor', 'target@example.test');
        $target->forceFill(['is_active' => false])->save();
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });

        try {
            app(UserManagementService::class)->activate($target, $actor);
            $this->fail('The audited mutation should fail.');
        } catch (RuntimeException) {
            $this->assertFalse($target->refresh()->is_active);
        }
    }

    public function test_campus_mutation_rolls_back_when_audit_fails(): void
    {
        $actor = $this->makeUser('super_admin', 'Super Admin', 'campus-audit@example.test');
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });

        try {
            app(CampusMasterDataService::class)->createUniversity([
                'code' => 'ROLLBACK-UNIV',
                'name' => 'Universitas Rollback',
                'type' => 'universitas',
                'has_faculties' => true,
            ], $actor);
            $this->fail('The audited campus mutation should fail.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('universities', ['code' => 'ROLLBACK-UNIV']);
        }
    }

    public function test_reporter_profile_mutation_rolls_back_when_audit_fails(): void
    {
        $reporter = $this->makeUser('reporter', 'Nama Awal', 'profile@example.test');
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });

        try {
            app(ReporterSelfServiceService::class)->updateProfile($reporter, ['name' => 'Nama Baru']);
            $this->fail('The audited mutation should fail.');
        } catch (RuntimeException) {
            $this->assertSame('Nama Awal', $reporter->refresh()->name);
        }
    }

    public function test_expiry_constraint_accepts_only_anonymous_login_failures(): void
    {
        $valid = app(AuditLogService::class)->record(
            action: AuditAction::AuthLoginFailed,
            category: AuditCategory::Auth,
            severity: AuditSeverity::Warning,
            metadata: [
                'fingerprint_version' => 'v1',
                'identifier_fingerprint' => str_repeat('a', 64),
                'network_fingerprint' => str_repeat('b', 64),
                'reason_code' => 'invalid_credentials',
            ],
            result: AuditResult::Failed,
            expiresAt: now()->addDay(),
        );
        $this->assertNotNull($valid->expires_at);

        try {
            app(AuditLogService::class)->record(
                action: AuditAction::SecurityAccessDenied,
                category: AuditCategory::Security,
                metadata: ['operation_code' => 'audit.detail', 'reason_code' => 'denied'],
                result: AuditResult::Denied,
                expiresAt: now()->addDay(),
            );
            $this->fail('A non-login audit expiry must be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $actor = $this->makeUser('admin', 'Admin', 'admin2@example.test');
        $this->expectException(QueryException::class);
        app(AuditLogService::class)->record(
            action: AuditAction::AuthLoginFailed,
            category: AuditCategory::Auth,
            actor: $actor,
            result: AuditResult::Failed,
            expiresAt: now()->addDay(),
        );
    }

    public function test_retention_purge_deletes_only_expired_anonymous_login_failures(): void
    {
        $expired = app(AuditLogService::class)->record(
            action: AuditAction::AuthLoginFailed,
            category: AuditCategory::Auth,
            result: AuditResult::Failed,
            expiresAt: now()->subMinute(),
        );
        $future = app(AuditLogService::class)->record(
            action: AuditAction::AuthLoginFailed,
            category: AuditCategory::Auth,
            result: AuditResult::Failed,
            expiresAt: now()->addDay(),
        );
        $business = app(AuditLogService::class)->record(
            action: AuditAction::ReportCreated,
            category: AuditCategory::Report,
            metadata: ['registration_number' => 'SLP-KEEP', 'status' => 'submitted'],
        );

        Artisan::call('audit:purge-expired-login-failures', ['--execute' => true]);

        $this->assertDatabaseMissing('audit_logs', ['id' => $expired->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $future->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $business->id]);
    }

    public function test_privacy_scrub_dry_run_is_bounded_private_and_non_mutating(): void
    {
        Storage::fake('local');
        $log = AuditLog::query()->create([
            'action' => AuditAction::SecurityAccessDenied->value,
            'category' => AuditCategory::Security->value,
            'severity' => AuditSeverity::Warning->value,
            'result' => AuditResult::Denied->value,
            'metadata' => [
                'operation_code' => 'audit.detail',
                'reason_code' => 'denied',
                'raw_email' => 'secret@example.test',
            ],
            'before_changes' => [],
            'after_changes' => [],
        ]);

        $exit = Artisan::call('audit:privacy-scrub', ['--batch' => 1]);
        $this->assertSame(0, $exit);
        $this->assertSame('secret@example.test', $log->refresh()->metadata['raw_email']);
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::AuditPrivacyScrub->value]);

        $files = Storage::disk('local')->allFiles('audit-scrub/reports');
        $this->assertCount(1, $files);
        $report = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('secret@example.test', $report);
        $this->assertStringContainsString('"mode": "dry_run"', $report);
        $this->assertStringContainsString('"changed": 1', $report);
        $this->assertStringNotContainsString('resume_cursor', $report);
    }

    public function test_privacy_scrub_preserves_event_time_reporter_snapshot(): void
    {
        Storage::fake('local');
        $reporter = $this->makeUser('reporter', 'Nama Pelapor Rahasia', 'snapshot@example.test');
        $log = AuditLog::query()->create([
            'actor_id' => $reporter->id,
            'actor_kind' => 'reporter',
            'actor_role_code' => 'reporter',
            'actor_display_name_safe' => null,
            'action' => AuditAction::ReportCreated->value,
            'category' => AuditCategory::Report->value,
            'severity' => AuditSeverity::Info->value,
            'result' => AuditResult::Succeeded->value,
            'metadata' => ['registration_number' => 'SLP-SNAPSHOT', 'status' => 'submitted', 'email' => 'secret@example.test'],
        ]);
        $reporter->forceFill([
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
        ])->save();

        $this->assertSame(0, Artisan::call('audit:privacy-scrub', ['--execute' => true, '--batch' => 1]));

        $log->refresh();
        $this->assertSame('reporter', $log->actor_kind);
        $this->assertSame('reporter', $log->actor_role_code);
        $this->assertNull($log->actor_display_name_safe);
        $this->assertArrayNotHasKey('email', $log->metadata);
    }

    public function test_sensitive_access_denials_are_allowlisted_and_deduplicated(): void
    {
        $reporter = $this->makeUser('reporter', 'Pelapor Rahasia', 'reporter@example.test');
        $visibleLog = app(AuditLogService::class)->record(
            action: AuditAction::AuthLogin,
            category: AuditCategory::Auth,
        );
        $this->actingAsApi($reporter);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->getJson("/api/v1/audit-logs/{$visibleLog->public_id}")->assertForbidden();
        }

        $denials = AuditLog::query()->where('action', AuditAction::SecurityAccessDenied->value)->get();
        $this->assertCount(1, $denials);
        $this->assertSame('audit.detail', $denials->first()->metadata['operation_code']);
        $this->assertSame(AuditResult::Denied->value, $denials->first()->result);
    }

    public function test_denial_audit_failure_never_weakens_authorization(): void
    {
        $reporter = $this->makeUser('reporter', 'Pelapor', 'reporter@example.test');
        $log = AuditLog::query()->create([
            'action' => AuditAction::AuthLogin->value,
            'category' => AuditCategory::Auth->value,
            'severity' => AuditSeverity::Info->value,
            'result' => AuditResult::Succeeded->value,
        ]);
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('audit unavailable'));
        });
        $this->actingAsApi($reporter);

        $this->getJson("/api/v1/audit-logs/{$log->public_id}")->assertForbidden();
    }

    public function test_denial_cache_failure_keeps_authorization_effective_without_unbounded_writes(): void
    {
        $reporter = $this->makeUser('reporter', 'Pelapor', 'cache-failure@example.test');
        $log = app(AuditLogService::class)->record(
            action: AuditAction::AuthLogin,
            category: AuditCategory::Auth,
        );
        Cache::shouldReceive('add')->andThrow(new RuntimeException('cache unavailable'));
        $this->actingAsApi($reporter);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->getJson("/api/v1/audit-logs/{$log->public_id}")->assertForbidden();
        }

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::SecurityAccessDenied->value,
        ]);
    }

    public function test_ordinary_forbidden_route_does_not_create_security_audit(): void
    {
        $reporter = $this->makeUser('reporter', 'Pelapor', 'ordinary-forbidden@example.test');
        $this->actingAsApi($reporter);

        $this->getJson('/api/v1/cases')->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::SecurityAccessDenied->value,
        ]);
    }

    private function makeUser(string $roleCode, string $name, string $email): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => $name,
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
