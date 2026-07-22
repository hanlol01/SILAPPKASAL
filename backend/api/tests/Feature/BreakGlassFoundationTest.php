<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\BreakGlassRequest;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\University;
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

    public function test_active_assigned_same_campus_satgas_can_request_for_anonymous_case(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();

        $response = $this->actingAsApi($satgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case, 60))
            ->assertCreated()
            ->assertJsonPath('data.status', BreakGlassRequest::STATUS_PENDING)
            ->assertJsonPath('data.requested_duration_minutes', 60)
            ->assertJsonPath('data.case.case_number', $case->case_number)
            ->assertJsonMissingPath('data.requestor.id')
            ->assertJsonMissingPath('data.report.id')
            ->assertJsonMissingPath('data.reporter')
            ->assertJsonMissingPath('data.identity');

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('break_glass_requests', [
            'id' => $requestId,
            'requestor_id' => $satgas->id,
            'report_id' => $case->report_id,
            'requested_duration_minutes' => 60,
            'status' => BreakGlassRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $satgas->id,
            'action' => AuditAction::BreakGlassRequested->value,
            'category' => AuditCategory::Privacy->value,
            'severity' => AuditSeverity::Critical->value,
        ]);
        $this->assertSame(1, $this->notificationsByType($admin, 'break_glass_request')->count());
    }

    public function test_request_validation_rejects_invalid_duration_short_reason_and_non_anonymous_case(): void
    {
        [$case, $satgas] = $this->anonymousCase();

        $this->actingAsApi($satgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case, 120))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requested_duration_minutes');

        $this->postJson('/api/v1/break-glass/request', [
            ...$this->requestPayload($case, 30),
            'reason' => 'Terlalu singkat',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $case->report()->update(['report_type' => 'confidential', 'tracking_code' => null]);
        $this->postJson('/api/v1/break-glass/request', $this->requestPayload($case, 30))
            ->assertUnprocessable();
    }

    public function test_each_locked_duration_is_accepted(): void
    {
        foreach (BreakGlassRequest::ALLOWED_DURATIONS as $duration) {
            [$case, $satgas] = $this->anonymousCase();

            $this->actingAsApi($satgas)
                ->postJson('/api/v1/break-glass/request', $this->requestPayload($case, $duration))
                ->assertCreated()
                ->assertJsonPath('data.requested_duration_minutes', $duration);
        }
    }

    public function test_only_active_assigned_same_campus_satgas_can_request(): void
    {
        [$case, $assignedSatgas, $admin, $reporter, $university, $otherUniversity] = $this->anonymousCase();
        $unassigned = $this->makeUser('satgas_ppks', 'unassigned@example.test', $university);
        $crossCampus = $this->makeUser('satgas_ppks', 'cross-campus@example.test', $otherUniversity);
        $inactive = $this->makeUser('satgas_ppks', 'inactive@example.test', $university, ['is_active' => false]);
        $superAdmin = $this->makeUser('super_admin', 'request-super@example.test');

        foreach ([$unassigned, $crossCampus, $inactive, $admin, $reporter, $superAdmin] as $actor) {
            $this->actingAsApi($actor)
                ->postJson('/api/v1/break-glass/request', $this->requestPayload($case))
                ->assertForbidden();
        }

        $case->activeAssignments()->where('satgas_id', $assignedSatgas->id)->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);
        $this->actingAsApi($assignedSatgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case))
            ->assertForbidden();
    }

    public function test_uniqueness_is_per_report_and_requester_and_retry_is_allowed_after_denial(): void
    {
        [$case, $satgas, $admin, , $university] = $this->anonymousCase();
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@example.test', $university);
        $this->assign($case, $otherSatgas, $admin, false);

        $first = $this->createRequest($case, $satgas);
        $this->actingAsApi($satgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case))
            ->assertUnprocessable();

        $this->actingAsApi($otherSatgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case, 30))
            ->assertCreated();

        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$first->id}/deny", [
                'denial_reason' => 'Kebutuhan akses belum memiliki dukungan yang cukup.',
            ])->assertOk();

        $this->actingAsApi($satgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case, 240))
            ->assertCreated();
    }

    public function test_admin_queue_is_same_campus_scoped_and_super_admin_has_no_operational_access(): void
    {
        [$case, $satgas, $admin, , , $otherUniversity] = $this->anonymousCase();
        $request = $this->createRequest($case, $satgas);
        $otherAdmin = $this->makeUser('admin', 'other-admin@example.test', $otherUniversity);
        $superAdmin = $this->makeUser('super_admin', 'super@example.test');

        $this->actingAsApi($admin)
            ->getJson('/api/v1/break-glass/pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $request->id);

        $this->actingAsApi($otherAdmin)
            ->getJson('/api/v1/break-glass/pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->patchJson("/api/v1/break-glass/{$request->id}/approve")->assertForbidden();

        $this->actingAsApi($superAdmin);
        $this->getJson('/api/v1/break-glass/pending')->assertForbidden();
        $this->patchJson("/api/v1/break-glass/{$request->id}/approve")->assertForbidden();
        $this->patchJson("/api/v1/break-glass/{$request->id}/deny", [
            'denial_reason' => 'Super Admin tidak boleh memproses permintaan ini.',
        ])->assertForbidden();
        $this->patchJson("/api/v1/break-glass/{$request->id}/revoke", [
            'revocation_reason' => 'Super Admin tidak boleh mencabut akses ini.',
        ])->assertForbidden();
        $this->postJson("/api/v1/break-glass/{$request->id}/reveal")->assertForbidden();
    }

    public function test_approval_revalidates_assignment_starts_grant_and_sends_generic_notifications(): void
    {
        [$case, $satgas, $admin, $reporter] = $this->anonymousCase();
        $request = $this->createRequest($case, $satgas, 30);

        $response = $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', BreakGlassRequest::STATUS_APPROVED)
            ->assertJsonPath('data.can_reveal', false)
            ->assertJsonPath('data.can_revoke', true);

        $request->refresh();
        $this->assertNotNull($request->grant_starts_at);
        $this->assertEquals(30, $request->grant_starts_at->diffInMinutes($request->expires_at));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => AuditAction::BreakGlassApproved->value,
        ]);

        $privacyNotice = $this->notificationsByType($reporter, 'privacy_notice')->firstOrFail()->data;
        $this->assertArrayNotHasKey('requestor_id', $privacyNotice);
        $this->assertArrayNotHasKey('approver_id', $privacyNotice);
        $this->assertArrayNotHasKey('reason', $privacyNotice);
        $this->assertStringNotContainsString($satgas->name, $privacyNotice['body']);
        $this->assertStringNotContainsString($admin->name, $privacyNotice['body']);
        $response->assertJsonMissingPath('data.identity');
    }

    public function test_approval_fails_when_requester_assignment_is_no_longer_active(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->createRequest($case, $satgas);
        $case->activeAssignments()->where('satgas_id', $satgas->id)->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/approve")
            ->assertForbidden();
        $this->assertSame(BreakGlassRequest::STATUS_PENDING, $request->refresh()->status);
    }

    public function test_only_requester_can_reveal_minimal_identity_and_every_reveal_is_audited(): void
    {
        [$case, $satgas, $admin, $reporter, $university] = $this->anonymousCase([
            'name' => 'Demo Pelapor Aman',
            'nim' => 'DEMO-2026-001',
            'phone_number' => '081234567890',
            'nip' => 'NIP-SECRET',
        ]);
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-reveal@example.test', $university);
        $this->assign($case, $otherSatgas, $admin, false);
        $request = $this->createRequest($case, $satgas);
        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/approve")
            ->assertOk();

        $this->actingAsApi($admin)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertForbidden();
        $this->actingAsApi($otherSatgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertForbidden();

        $response = $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertOk()
            ->assertJsonPath('data.name', 'Demo Pelapor Aman')
            ->assertJsonPath('data.nim', 'DEMO-2026-001')
            ->assertJsonPath('data.email', $reporter->email)
            ->assertJsonPath('data.phone_number', '081234567890')
            ->assertJsonMissingPath('data.nip')
            ->assertJsonMissingPath('data.address')
            ->assertJsonMissingPath('data.reason')
            ->assertHeader('Pragma', 'no-cache');
        $this->assertEqualsCanonicalizing([
            'name',
            'nim',
            'email',
            'phone_number',
            'faculty',
            'study_program',
            'university',
        ], array_keys($response->json('data')));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->postJson("/api/v1/break-glass/{$request->id}/reveal")->assertOk();
        $request->refresh();
        $this->assertSame(BreakGlassRequest::STATUS_APPROVED, $request->status);
        $this->assertSame(2, $request->view_count);
        $this->assertNotNull($request->last_viewed_at);
        $this->assertSame(2, AuditLog::query()
            ->where('action', AuditAction::BreakGlassIdentityViewed->value)
            ->where('subject_id', $request->id)
            ->count());
    }

    public function test_reveal_revalidates_active_actor_assignment_anonymous_integrity_and_grant_state(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->approvedRequest($case, $satgas, $admin);

        $satgas->forceFill(['is_active' => false])->save();
        $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertForbidden();

        $satgas->forceFill(['is_active' => true])->save();
        $case->activeAssignments()->where('satgas_id', $satgas->id)->update(['is_active' => false]);
        $this->postJson("/api/v1/break-glass/{$request->id}/reveal")->assertForbidden();

        $case->activeAssignments()->where('satgas_id', $satgas->id)->update(['is_active' => true]);
        $case->report()->update(['report_type' => 'confidential', 'tracking_code' => null]);
        $this->postJson("/api/v1/break-glass/{$request->id}/reveal")->assertForbidden();
        $this->assertSame(0, $request->refresh()->view_count);
    }

    public function test_reveal_is_denied_before_grant_start(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->approvedRequest($case, $satgas, $admin);
        $request->forceFill([
            'grant_starts_at' => now()->addMinutes(5),
            'expires_at' => now()->addHour(),
        ])->save();

        $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertForbidden();
        $this->assertSame(0, $request->refresh()->view_count);
    }

    public function test_admin_can_revoke_active_grant_and_reveal_then_fails(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->approvedRequest($case, $satgas, $admin);

        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/revoke", [
                'revocation_reason' => 'Penugasan berubah dan akses tidak lagi diperlukan.',
            ])->assertOk()
            ->assertJsonPath('data.status', BreakGlassRequest::STATUS_REVOKED)
            ->assertJsonPath('data.can_revoke', false);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => AuditAction::BreakGlassRevoked->value,
        ]);
        $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertForbidden();
    }

    public function test_stale_approve_revoke_and_reveal_operations_preserve_one_transactional_state(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->createRequest($case, $satgas);

        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/approve")
            ->assertOk();
        $expiresAt = $request->refresh()->expires_at?->toJSON();
        $this->patchJson("/api/v1/break-glass/{$request->id}/approve")
            ->assertUnprocessable();
        $this->assertSame($expiresAt, $request->refresh()->expires_at?->toJSON());
        $this->assertSame(1, AuditLog::query()
            ->where('action', AuditAction::BreakGlassApproved->value)
            ->where('subject_id', $request->id)
            ->count());

        $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertOk();
        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/revoke", [
                'revocation_reason' => 'Akses segera dicabut setelah kebutuhan investigasi selesai.',
            ])->assertOk();
        $this->patchJson("/api/v1/break-glass/{$request->id}/revoke", [
            'revocation_reason' => 'Percobaan pencabutan stale harus ditolak secara aman.',
        ])->assertUnprocessable();
        $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$request->id}/reveal")
            ->assertForbidden();

        $this->assertSame(1, $request->refresh()->view_count);
        $this->assertSame(1, AuditLog::query()
            ->where('action', AuditAction::BreakGlassRevoked->value)
            ->where('subject_id', $request->id)
            ->count());
    }

    public function test_expiry_is_normalized_once_without_scheduler_and_never_reveals(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->approvedRequest($case, $satgas, $admin);
        $request->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAsApi($satgas)
            ->getJson("/api/v1/break-glass/mine?case_id={$case->id}")
            ->assertOk()
            ->assertJsonPath('data.0.status', BreakGlassRequest::STATUS_EXPIRED)
            ->assertJsonPath('data.0.can_reveal', false);
        $this->getJson("/api/v1/break-glass/mine?case_id={$case->id}")->assertOk();

        $this->assertSame(BreakGlassRequest::STATUS_EXPIRED, $request->refresh()->status);
        $this->assertSame(1, AuditLog::query()
            ->where('action', AuditAction::BreakGlassExpired->value)
            ->where('subject_id', $request->id)
            ->count());
        $this->postJson("/api/v1/break-glass/{$request->id}/reveal")->assertForbidden();
    }

    public function test_legacy_viewed_grant_remains_readable_but_does_not_fabricate_audit_history(): void
    {
        [$case, $satgas, $admin] = $this->anonymousCase();
        $request = $this->createRequest($case, $satgas);
        $request->forceFill([
            'approver_id' => $admin->id,
            'status' => BreakGlassRequest::STATUS_VIEWED,
            'approved_at' => now()->subMinutes(5),
            'grant_starts_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(25),
            'viewed_at' => now()->subMinutes(4),
            'view_count' => 1,
            'last_viewed_at' => now()->subMinutes(4),
        ])->save();
        AuditLog::query()->where('subject_id', $request->id)->delete();

        $this->actingAsApi($satgas)
            ->getJson("/api/v1/break-glass/mine?case_id={$case->id}")
            ->assertOk()
            ->assertJsonPath('data.0.status', BreakGlassRequest::STATUS_VIEWED)
            ->assertJsonPath('data.0.can_reveal', true);

        $this->assertSame(0, AuditLog::query()->where('subject_id', $request->id)->count());
    }

    public function test_audit_projection_does_not_store_identity_or_free_text_reason(): void
    {
        [$case, $satgas, $admin, $reporter] = $this->anonymousCase();
        $reason = 'Alasan sangat rahasia yang hanya boleh tampil bagi reviewer kampus dan pemohon.';
        $denialReason = 'Narasi penolakan privat yang tidak boleh masuk metadata audit.';
        $revocationReason = 'Narasi pencabutan privat yang tidak boleh masuk metadata audit.';
        $firstId = $this->actingAsApi($satgas)->postJson('/api/v1/break-glass/request', [
            ...$this->requestPayload($case),
            'reason' => $reason,
        ])->assertCreated()->json('data.id');
        $this->actingAsApi($admin)->patchJson("/api/v1/break-glass/{$firstId}/deny", [
            'denial_reason' => $denialReason,
        ])->assertOk();

        $second = $this->createRequest($case, $satgas);
        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$second->id}/approve")
            ->assertOk();
        $this->actingAsApi($satgas)
            ->postJson("/api/v1/break-glass/{$second->id}/reveal")
            ->assertOk();
        $this->actingAsApi($admin)->patchJson("/api/v1/break-glass/{$second->id}/revoke", [
            'revocation_reason' => $revocationReason,
        ])->assertOk();

        $encoded = AuditLog::query()
            ->whereIn('action', [
                AuditAction::BreakGlassRequested->value,
                AuditAction::BreakGlassDenied->value,
                AuditAction::BreakGlassApproved->value,
                AuditAction::BreakGlassIdentityViewed->value,
                AuditAction::BreakGlassRevoked->value,
            ])
            ->get(['metadata', 'before_changes', 'after_changes'])
            ->toJson();

        $this->assertStringNotContainsString($reason, (string) $encoded);
        $this->assertStringNotContainsString($denialReason, (string) $encoded);
        $this->assertStringNotContainsString($revocationReason, (string) $encoded);
        $this->assertStringNotContainsString($reporter->email, (string) $encoded);
        $this->assertStringNotContainsString($reporter->name, (string) $encoded);
    }

    private function actingAsApi(User $user): self
    {
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $reporterOverrides
     * @return array{CaseRecord, User, User, User, University, University}
     */
    private function anonymousCase(array $reporterOverrides = []): array
    {
        $suffix = str_pad((string) (University::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        $university = University::query()->create([
            'code' => 'UNI-R2-'.$suffix,
            'name' => 'Universitas R2 Utama',
            'is_active' => true,
        ]);
        $otherUniversity = University::query()->create([
            'code' => 'UNI-R2-O'.$suffix,
            'name' => 'Universitas R2 Lain',
            'is_active' => true,
        ]);
        $admin = $this->makeUser('admin', 'admin-'.uniqid().'@example.test', $university);
        $satgas = $this->makeUser('satgas_ppks', 'satgas-'.uniqid().'@example.test', $university);
        $reporter = $this->makeUser('reporter', 'reporter-'.uniqid().'@example.test', $university, $reporterOverrides);
        $report = $this->makeReport($reporter);
        $status = CaseStatus::query()->where('name', 'investigation')->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-R2-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'investigation_started_at' => now(),
        ]);
        $this->assign($case, $satgas, $admin, true);

        return [$case, $satgas, $admin, $reporter, $university, $otherUniversity];
    }

    /** @param array<string, mixed> $overrides */
    private function makeUser(
        string $roleCode,
        string $email,
        ?University $university = null,
        array $overrides = [],
    ): User {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create(array_merge([
            'role_id' => $role->id,
            'university_id' => $university?->id,
            'name' => $roleCode.' R2 User',
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ], $overrides));
    }

    private function makeReport(User $reporter): Report
    {
        $sequence = Report::query()->count() + 1;

        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-R2-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'tracking_code' => 'R2AA-BBCC-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'report_type' => 'anonymous',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi sensitif fiktif yang tidak boleh tampil pada metadata akses darurat.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Lokasi fiktif sensitif',
            'location_type' => 'LOC-01',
            'status' => 'forwarded',
            'priority' => 'PRIO-03',
            'submitted_at' => now()->subHour(),
            'forwarded_at' => now(),
        ]);
    }

    private function assign(CaseRecord $case, User $satgas, User $admin, bool $isLead): CaseAssignment
    {
        return CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => $isLead,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function requestPayload(CaseRecord $case, int $duration = 60): array
    {
        return [
            'case_id' => $case->id,
            'reason_category' => 'investigation_necessity',
            'reason' => 'Akses identitas dibutuhkan untuk kebutuhan investigasi spesifik yang terdokumentasi pada kasus fiktif ini.',
            'requested_duration_minutes' => $duration,
            'acknowledgment' => true,
        ];
    }

    private function createRequest(CaseRecord $case, User $satgas, int $duration = 60): BreakGlassRequest
    {
        $response = $this->actingAsApi($satgas)
            ->postJson('/api/v1/break-glass/request', $this->requestPayload($case, $duration))
            ->assertCreated();

        return BreakGlassRequest::query()->findOrFail($response->json('data.id'));
    }

    private function approvedRequest(CaseRecord $case, User $satgas, User $admin): BreakGlassRequest
    {
        $request = $this->createRequest($case, $satgas);
        $this->actingAsApi($admin)
            ->patchJson("/api/v1/break-glass/{$request->id}/approve")
            ->assertOk();

        return $request->refresh();
    }
}
