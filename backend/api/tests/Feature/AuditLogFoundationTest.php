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
use App\Support\AuditSnapshot;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AuditLogFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_audit_schema_has_public_snapshot_and_retention_columns(): void
    {
        foreach ([
            'public_id',
            'actor_id',
            'actor_kind',
            'actor_role_code',
            'actor_display_name_safe',
            'request_id',
            'result',
            'subject_kind',
            'subject_reference_safe',
            'is_elevated_access',
            'expires_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('audit_logs', $column));
        }

        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'deleted_at'));
    }

    public function test_staff_snapshot_rejects_email_and_identity_number_shaped_names(): void
    {
        $snapshot = app(AuditSnapshot::class);

        $this->assertNull($snapshot->safeStaffName('staff@example.test'));
        $this->assertNull($snapshot->safeStaffName('Petugas 123456789'));
        $this->assertSame('Petugas Kampus Aman', $snapshot->safeStaffName('Petugas Kampus Aman'));
    }

    public function test_strict_catalog_discards_unknown_sensitive_and_non_scalar_fields(): void
    {
        $actor = $this->makeUser('admin', 'Admin Operasional Panjang', 'admin@university.ac.id');

        $auditLog = app(AuditLogService::class)->record(
            action: AuditAction::SecurityAccessDenied,
            category: AuditCategory::Security,
            severity: AuditSeverity::Critical,
            actor: $actor,
            metadata: [
                'operation_code' => 'break_glass.reveal',
                'reason_code' => 'policy_denied',
                'password' => 'SuperSecret',
                'email' => 'person@example.test',
                'nested' => ['unsafe' => true],
            ],
            beforeChanges: ['status' => 'submitted', 'chronology' => 'Sensitive chronology'],
            afterChanges: ['status' => 'under_review'],
            requestId: 'req-123',
            result: AuditResult::Denied,
        );

        $this->assertSame([
            'operation_code' => 'break_glass.reveal',
            'reason_code' => 'policy_denied',
        ], $auditLog->metadata);
        $this->assertSame([], $auditLog->before_changes);
        $this->assertSame([], $auditLog->after_changes);
        $this->assertSame('staff', $auditLog->actor_kind);
        $this->assertSame('admin', $auditLog->actor_role_code);
        $this->assertSame('Admin Operasional Panjang', $auditLog->actor_display_name_safe);
        $this->assertSame(AuditResult::Denied->value, $auditLog->result);
        $this->assertFalse($auditLog->is_elevated_access);
        $this->assertNotNull($auditLog->public_id);

        $rawJson = json_encode(DB::table('audit_logs')->first());
        $this->assertStringNotContainsString('SuperSecret', $rawJson);
        $this->assertStringNotContainsString('person@example.test', $rawJson);
        $this->assertStringNotContainsString('Sensitive chronology', $rawJson);
    }

    public function test_list_and_detail_serialize_only_sanitized_public_contract(): void
    {
        $admin = $this->makeUser('admin', 'Admin Kampus', 'admin@university.ac.id');
        $log = app(AuditLogService::class)->record(
            action: AuditAction::SecurityAccessDenied,
            category: AuditCategory::Security,
            severity: AuditSeverity::Critical,
            actor: $admin,
            metadata: ['operation_code' => 'evidence.download', 'reason_code' => 'policy_denied'],
            result: AuditResult::Denied,
        );

        $this->actingAsApi($admin);
        $response = $this->getJson('/api/v1/audit-logs?severity=critical')
            ->assertOk()
            ->assertJsonPath('data.data.0.public_id', $log->public_id)
            ->assertJsonPath('data.data.0.actor.kind', 'staff')
            ->assertJsonPath('data.data.0.actor.label', 'Admin Kampus')
            ->assertJsonPath('data.data.0.result', 'denied');

        $serialized = $response->json('data.data.0');
        $this->assertArrayNotHasKey('id', $serialized);
        $this->assertArrayNotHasKey('actor_id', $serialized);
        $this->assertArrayNotHasKey('subject_id', $serialized);
        $this->assertArrayNotHasKey('subject_type', $serialized);

        $this->getJson("/api/v1/audit-logs/{$log->public_id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $log->public_id)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.actor_id')
            ->assertJsonMissingPath('data.subject_id')
            ->assertJsonMissingPath('data.subject_type');

        $this->getJson("/api/v1/audit-logs/{$log->id}")->assertNotFound();
    }

    public function test_admin_detail_outside_visibility_scope_returns_not_found(): void
    {
        $admin = $this->makeUser('admin', 'Admin Kampus', 'admin@university.ac.id');
        $privacyLog = app(AuditLogService::class)->record(
            action: AuditAction::BreakGlassIdentityViewed,
            category: AuditCategory::Privacy,
            severity: AuditSeverity::Critical,
            actor: $admin,
            metadata: ['registration_number' => 'SLP-PRIVATE'],
            isElevatedAccess: true,
        );
        app(AuditLogService::class)->record(
            action: AuditAction::SecurityAccessDenied,
            category: AuditCategory::Security,
            actor: $admin,
            metadata: ['operation_code' => 'break_glass.reveal', 'reason_code' => 'policy_denied'],
            result: AuditResult::Denied,
        );

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/audit-logs?category=privacy')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
        $this->getJson("/api/v1/audit-logs/{$privacyLog->public_id}")->assertNotFound();
    }

    public function test_super_admin_sees_privacy_records_but_only_sanitized_data(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'Pimpinan PPKS', 'super@university.ac.id');
        $privacyLog = app(AuditLogService::class)->record(
            action: AuditAction::BreakGlassApproved,
            category: AuditCategory::Privacy,
            severity: AuditSeverity::Critical,
            actor: $superAdmin,
            metadata: [
                'registration_number' => 'SLP-20260718-0001',
                'reason_category' => 'safety_emergency',
                'reporter_email' => 'reporter@example.test',
            ],
            isElevatedAccess: true,
        );

        $this->actingAsApi($superAdmin);
        $response = $this->getJson('/api/v1/audit-logs?category=privacy')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.public_id', $privacyLog->public_id)
            ->assertJsonPath('data.data.0.is_elevated_access', true)
            ->assertJsonMissingPath('data.data.0.metadata.reporter_email');

        $json = json_encode($response->json());
        $this->assertStringNotContainsString('reporter@example.test', $json);
        $this->assertStringNotContainsString('App\\Models', $json);
    }

    public function test_reporter_and_system_actor_labels_never_expose_identity(): void
    {
        $reporter = $this->makeUser('reporter', 'Nama Pelapor Rahasia', 'reporter@example.test');
        $reporterLog = app(AuditLogService::class)->record(
            action: AuditAction::ReportCreated,
            category: AuditCategory::Report,
            actor: $reporter,
            metadata: ['registration_number' => 'SLP-REPORTER', 'status' => 'submitted'],
        );
        $systemLog = app(AuditLogService::class)->record(
            action: AuditAction::SecurityAccessDenied,
            category: AuditCategory::Security,
            metadata: ['operation_code' => 'audit.oversight', 'reason_code' => 'anonymous'],
            result: AuditResult::Denied,
        );
        $superAdmin = $this->makeUser('super_admin', 'Super Admin', 'super@example.test');

        $this->actingAsApi($superAdmin);
        $response = $this->getJson('/api/v1/audit-logs')->assertOk();
        $items = collect($response->json('data.data'))->keyBy('public_id');

        $this->assertSame('Pelapor', $items[$reporterLog->public_id]['actor']['label']);
        $this->assertNull($items[$reporterLog->public_id]['actor']['display_name_safe']);
        $this->assertSame('Sistem', $items[$systemLog->public_id]['actor']['label']);
        $this->assertNull($items[$systemLog->public_id]['actor']['display_name_safe']);

        $json = json_encode($response->json());
        $this->assertStringNotContainsString('Nama Pelapor Rahasia', $json);
        $this->assertStringNotContainsString('reporter@example.test', $json);
    }

    public function test_non_audit_roles_cannot_access_audit_api(): void
    {
        $satgas = $this->makeUser('satgas_ppks', 'Satgas', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'Pelapor', 'reporter@university.ac.id');
        $log = app(AuditLogService::class)->record(
            action: AuditAction::AuthLogin,
            category: AuditCategory::Auth,
        );

        foreach ([$satgas, $reporter] as $user) {
            $this->actingAsApi($user);
            $this->getJson('/api/v1/audit-logs')->assertForbidden();
            $this->getJson("/api/v1/audit-logs/{$log->public_id}")->assertForbidden();
        }
    }

    public function test_public_id_is_immutable_in_application_and_database(): void
    {
        $log = app(AuditLogService::class)->record(
            action: AuditAction::AuthLogin,
            category: AuditCategory::Auth,
        );

        try {
            $log->forceFill(['public_id' => '00000000-0000-4000-8000-000000000000'])->save();
            $this->fail('Application mutation should fail.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update([
            'public_id' => '00000000-0000-4000-8000-000000000000',
        ]);
    }

    public function test_database_rejects_null_public_id_and_invalid_result_values(): void
    {
        $log = app(AuditLogService::class)->record(
            action: AuditAction::AuthLogin,
            category: AuditCategory::Auth,
        );

        try {
            DB::table('audit_logs')->where('id', $log->id)->update(['public_id' => null]);
            $this->fail('Database must reject a null public audit identifier.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['result' => 'unknown']);
    }

    public function test_presentation_suppresses_malformed_reporter_identity_snapshot(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'Super Admin', 'snapshot-viewer@example.test');
        $log = AuditLog::query()->create([
            'actor_kind' => 'reporter',
            'actor_role_code' => 'reporter',
            'actor_display_name_safe' => 'reporter@example.test',
            'action' => AuditAction::ReportCreated->value,
            'category' => AuditCategory::Report->value,
            'severity' => AuditSeverity::Info->value,
            'result' => AuditResult::Succeeded->value,
            'metadata' => ['registration_number' => 'SLP-SAFE', 'status' => 'submitted'],
        ]);
        $this->actingAsApi($superAdmin);

        $this->getJson("/api/v1/audit-logs/{$log->public_id}")
            ->assertOk()
            ->assertJsonPath('data.actor.kind', 'reporter')
            ->assertJsonPath('data.actor.label', 'Pelapor')
            ->assertJsonPath('data.actor.display_name_safe', null);
    }

    public function test_unknown_legacy_action_is_presented_without_unsafe_payload_or_crashing(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'Super Admin', 'legacy-audit@example.test');
        $log = AuditLog::query()->create([
            'action' => 'legacy.unknown_action',
            'category' => AuditCategory::System->value,
            'severity' => AuditSeverity::Info->value,
            'result' => AuditResult::Succeeded->value,
            'metadata' => ['email' => 'secret@example.test'],
            'before_changes' => ['password' => 'secret'],
            'after_changes' => ['token' => 'secret'],
        ]);
        $this->actingAsApi($superAdmin);

        $this->getJson("/api/v1/audit-logs/{$log->public_id}")
            ->assertOk()
            ->assertJsonPath('data.metadata', [])
            ->assertJsonPath('data.changes.before', [])
            ->assertJsonPath('data.changes.after', []);
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
