<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_audit_logs_table_is_append_only_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'actor_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'request_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'subject_type'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'subject_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'metadata'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'before_changes'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'after_changes'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'created_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'deleted_at'));
    }

    public function test_audit_service_records_safe_deltas_and_redacts_sensitive_values(): void
    {
        $actor = $this->makeUser('admin', 'admin@university.ac.id');

        app(AuditLogService::class)->record(
            action: AuditAction::SecurityAccessDenied,
            category: AuditCategory::Security,
            severity: AuditSeverity::Critical,
            actor: $actor,
            metadata: [
                'reason' => 'policy_denied',
                'password' => 'SuperSecret',
                'plain_text_token' => 'token-value',
                'nested' => ['safe' => 'ok', 'file_content' => 'raw-file-bytes'],
            ],
            beforeChanges: [
                'status' => 'submitted',
                'chronology' => 'Sensitive chronology text',
                'snapshot' => ['full' => 'object'],
            ],
            afterChanges: [
                'status' => 'under_review',
                'encrypted_payload' => 'cipher-text',
            ],
            requestId: 'req-123'
        );

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame(AuditAction::SecurityAccessDenied->value, $auditLog->action);
        $this->assertSame(AuditCategory::Security->value, $auditLog->category);
        $this->assertSame(AuditSeverity::Critical->value, $auditLog->severity);
        $this->assertFalse($auditLog->metadata['is_elevated_access']);
        $this->assertSame('policy_denied', $auditLog->metadata['reason']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['password']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['plain_text_token']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['nested']['file_content']);
        $this->assertSame('submitted', $auditLog->before_changes['status']);
        $this->assertSame('[REDACTED]', $auditLog->before_changes['chronology']);
        $this->assertSame('[REDACTED_NON_SCALAR]', $auditLog->before_changes['snapshot']);
        $this->assertSame('[REDACTED]', $auditLog->after_changes['encrypted_payload']);

        $rawRow = DB::table('audit_logs')->first();
        $rawJson = json_encode($rawRow);

        $this->assertStringNotContainsString('SuperSecret', $rawJson);
        $this->assertStringNotContainsString('token-value', $rawJson);
        $this->assertStringNotContainsString('Sensitive chronology text', $rawJson);
        $this->assertStringNotContainsString('cipher-text', $rawJson);
        $this->assertStringNotContainsString('raw-file-bytes', $rawJson);
    }

    public function test_admin_and_super_admin_can_view_all_audit_severities(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');

        $log = AuditLog::query()->create([
            'actor_id' => null,
            'request_id' => 'req-critical',
            'action' => AuditAction::SecurityAccessDenied->value,
            'category' => AuditCategory::Security->value,
            'severity' => AuditSeverity::Critical->value,
            'metadata' => ['is_elevated_access' => false],
            'before_changes' => [],
            'after_changes' => [],
        ]);

        foreach ([$admin, $superAdmin] as $user) {
            $this->actingAsApi($user);
            $this->getJson('/api/v1/audit-logs?severity=critical')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.data.0.id', $log->id)
                ->assertJsonPath('data.data.0.action', AuditAction::SecurityAccessDenied->value);

            $this->getJson("/api/v1/audit-logs/{$log->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $log->id)
                ->assertJsonPath('data.severity', AuditSeverity::Critical->value);
        }
    }

    public function test_satgas_and_reporter_have_no_audit_api_access(): void
    {
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');

        $log = AuditLog::query()->create([
            'action' => AuditAction::AuthLogin->value,
            'category' => AuditCategory::Auth->value,
            'severity' => AuditSeverity::Info->value,
            'metadata' => ['is_elevated_access' => false],
            'before_changes' => [],
            'after_changes' => [],
        ]);

        foreach ([$satgas, $reporter] as $user) {
            $this->actingAsApi($user);
            $this->getJson('/api/v1/audit-logs')->assertForbidden();
            $this->getJson("/api/v1/audit-logs/{$log->id}")->assertForbidden();
        }
    }

    public function test_audit_query_filters_by_safe_fields(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');

        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'request_id' => 'req-auth',
            'action' => AuditAction::AuthLogin->value,
            'category' => AuditCategory::Auth->value,
            'severity' => AuditSeverity::Info->value,
            'metadata' => ['is_elevated_access' => false],
            'before_changes' => [],
            'after_changes' => [],
        ]);

        AuditLog::query()->create([
            'actor_id' => null,
            'request_id' => 'req-denied',
            'action' => AuditAction::SecurityAccessDenied->value,
            'category' => AuditCategory::Security->value,
            'severity' => AuditSeverity::Warning->value,
            'metadata' => ['is_elevated_access' => false],
            'before_changes' => [],
            'after_changes' => [],
        ]);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/audit-logs?category=security&request_id=req-denied')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.action', AuditAction::SecurityAccessDenied->value);
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
