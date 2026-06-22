<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\BreakGlassRequest;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BreakGlassFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_admin_can_request_break_glass_for_anonymous_report_and_only_one_pending_exists_globally(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $anotherAdmin = $this->makeUser('admin', 'admin-2@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $report = $this->makeReport($this->makeUser('reporter', 'reporter@example.test'));

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($report))
            ->assertCreated()
            ->assertJsonPath('data.status', BreakGlassRequest::STATUS_PENDING)
            ->assertJsonPath('data.report.registration_number', $report->registration_number)
            ->assertJsonMissingPath('data.reporter');

        $this->assertDatabaseHas('break_glass_requests', [
            'requestor_id' => $admin->id,
            'report_id' => $report->id,
            'status' => BreakGlassRequest::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => AuditAction::BreakGlassRequested->value,
            'category' => AuditCategory::Privacy->value,
            'severity' => AuditSeverity::Critical->value,
        ]);

        $this->assertSame(1, $superAdmin->notifications()->where('data->notification_type_code', 'break_glass_request')->count());

        Sanctum::actingAs($anotherAdmin, ['*']);

        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($report))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('break_glass_requests', 1);
    }

    public function test_break_glass_request_validation_requires_anonymous_report_and_forbids_non_admin_roles(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@example.test');
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $openReport = $this->makeReport($reporter, reportType: 'open');
        $anonymousReport = $this->makeReport($reporter, registrationNumber: 'SLP-20260622-2002');

        Sanctum::actingAs($satgas, ['*']);

        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($anonymousReport))
            ->assertForbidden();

        Sanctum::actingAs($reporter, ['*']);

        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($anonymousReport))
            ->assertForbidden();

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($openReport))
            ->assertUnprocessable();

        $this->postJson('/api/v1/break-glass/request', array_merge($this->requestPayload($anonymousReport), [
            'reason' => 'Too short',
        ]))->assertUnprocessable();
    }

    public function test_super_admin_can_list_approve_and_reveal_minimal_identity_with_no_store_header(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $reporter = $this->makeUser('reporter', 'reporter@example.test', [
            'name' => 'Reporter Privacy',
            'nim' => '2200012345',
            'nip' => 'NIP-PRIVATE',
            'phone_number' => '6281234567890',
        ]);
        $report = $this->makeReport($reporter);
        $breakGlassRequest = $this->createBreakGlassRequest($admin, $report);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson('/api/v1/break-glass/pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $breakGlassRequest->id)
            ->assertJsonMissingPath('data.0.reporter');

        $this->patchJson("/api/v1/break-glass/{$breakGlassRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', BreakGlassRequest::STATUS_APPROVED);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $superAdmin->id,
            'action' => AuditAction::BreakGlassApproved->value,
            'category' => AuditCategory::Privacy->value,
            'severity' => AuditSeverity::Critical->value,
        ]);

        $response = $this->getJson("/api/v1/break-glass/{$breakGlassRequest->id}/reveal");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Reporter Privacy')
            ->assertJsonPath('data.email', 'reporter@example.test')
            ->assertJsonMissingPath('data.nim')
            ->assertJsonMissingPath('data.nip')
            ->assertJsonMissingPath('data.phone_number')
            ->assertJsonMissingPath('data.address');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertDatabaseHas('break_glass_requests', [
            'id' => $breakGlassRequest->id,
            'status' => BreakGlassRequest::STATUS_VIEWED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $superAdmin->id,
            'action' => AuditAction::BreakGlassIdentityViewed->value,
            'category' => AuditCategory::Privacy->value,
            'severity' => AuditSeverity::Critical->value,
        ]);

        $secondRevealResponse = $this->getJson("/api/v1/break-glass/{$breakGlassRequest->id}/reveal");
        $secondRevealResponse->assertOk();
        $this->assertStringContainsString('no-store', (string) $secondRevealResponse->headers->get('Cache-Control'));

        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::BreakGlassIdentityViewed->value)->count());
    }

    public function test_reporter_notifications_on_each_approval_are_generic_and_independent(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test', ['name' => 'Approver Hidden']);
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter);

        $firstRequest = $this->createBreakGlassRequest($admin, $report);
        Sanctum::actingAs($superAdmin, ['*']);
        $this->patchJson("/api/v1/break-glass/{$firstRequest->id}/approve")->assertOk();

        $secondRequest = $this->createBreakGlassRequest($admin, $report, 'institutional_compliance');
        Sanctum::actingAs($superAdmin, ['*']);
        $this->patchJson("/api/v1/break-glass/{$secondRequest->id}/approve")->assertOk();

        $notifications = $reporter->notifications()
            ->where('data->notification_type_code', 'privacy_notice')
            ->get();

        $this->assertCount(2, $notifications);

        foreach ($notifications as $notification) {
            $payload = $notification->data;

            $this->assertSame('privacy_notice', $payload['notification_type_code']);
            $this->assertSame('break_glass_approved', $payload['event']);
            $this->assertStringContainsString($report->registration_number, $payload['body']);
            $this->assertStringNotContainsString($superAdmin->name, $payload['body']);
            $this->assertArrayNotHasKey('approver_id', $payload);
            $this->assertArrayNotHasKey('viewer_id', $payload);
            $this->assertArrayNotHasKey('requestor_id', $payload);
        }
    }

    public function test_denied_requests_can_be_retried_but_reporter_is_not_notified(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter);
        $breakGlassRequest = $this->createBreakGlassRequest($admin, $report);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->patchJson("/api/v1/break-glass/{$breakGlassRequest->id}/deny", [
            'denial_reason' => 'Dokumen pendukung belum cukup untuk membuka identitas.',
        ])->assertOk()
            ->assertJsonPath('data.status', BreakGlassRequest::STATUS_DENIED);

        $this->assertSame(0, $reporter->notifications()->where('data->notification_type_code', 'privacy_notice')->count());

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($report, 'victim_consent'))
            ->assertCreated();

        $this->assertDatabaseCount('break_glass_requests', 2);
    }

    public function test_reveal_is_forbidden_after_ttl_and_to_unrelated_super_admin(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $approver = $this->makeUser('super_admin', 'approver@example.test');
        $otherSuperAdmin = $this->makeUser('super_admin', 'other-super@example.test');
        $report = $this->makeReport($this->makeUser('reporter', 'reporter@example.test'));
        $breakGlassRequest = $this->createBreakGlassRequest($admin, $report);

        Sanctum::actingAs($approver, ['*']);
        $this->patchJson("/api/v1/break-glass/{$breakGlassRequest->id}/approve")->assertOk();

        Sanctum::actingAs($otherSuperAdmin, ['*']);
        $this->getJson("/api/v1/break-glass/{$breakGlassRequest->id}/reveal")
            ->assertForbidden();

        $breakGlassRequest->refresh()->forceFill([
            'status' => BreakGlassRequest::STATUS_VIEWED,
            'viewed_at' => now()->subHours(9),
        ])->save();

        Sanctum::actingAs($approver, ['*']);
        $this->getJson("/api/v1/break-glass/{$breakGlassRequest->id}/reveal")
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeUser(string $roleCode, string $email, array $overrides = []): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create(array_merge([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ], $overrides));
    }

    private function makeReport(
        User $reporter,
        string $registrationNumber = 'SLP-20260622-2001',
        string $reportType = 'anonymous',
    ): Report {
        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => $registrationNumber,
            'tracking_code' => $reportType === 'anonymous' ? $this->trackingCode($registrationNumber) : null,
            'report_type' => $reportType,
            'category_code' => 'RCAT-01',
            'chronology' => 'Sensitive chronology must remain hidden from break-glass request resources.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Sensitive incident location',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Sensitive respondent',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-01',
            'respondent_details' => 'Sensitive respondent details',
            'witness_info' => 'Sensitive witness info',
            'status' => 'submitted',
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(Report $report, string $reasonCategory = 'legal_requirement'): array
    {
        return [
            'report_id' => $report->id,
            'reason_category' => $reasonCategory,
            'reason' => 'A formally documented privacy exception is required for this fictional anonymous report review.',
            'acknowledgment' => true,
        ];
    }

    private function createBreakGlassRequest(User $requestor, Report $report, string $reasonCategory = 'legal_requirement'): BreakGlassRequest
    {
        Sanctum::actingAs($requestor, ['*']);

        $response = $this->postJson('/api/v1/break-glass/request', $this->requestPayload($report, $reasonCategory));

        $response->assertCreated();

        return BreakGlassRequest::query()->findOrFail($response->json('data.id'));
    }

    private function trackingCode(string $registrationNumber): string
    {
        return implode('-', str_split(strtoupper(substr(md5($registrationNumber), 0, 16)), 4));
    }
}
