<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseMinuteStatus;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseMinute;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Support\ApiErrorCode;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CaseMinuteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_schema_permissions_and_unique_case_version_constraint_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('case_minutes'));
        $this->assertTrue(Schema::hasColumn('case_minutes', 'anonymized_summary'));
        $this->assertDatabaseHas('permissions', ['code' => 'case_minutes.read']);
        $this->assertDatabaseHas('permissions', ['code' => 'case_minutes.write']);
        $this->assertDatabaseHas('permissions', ['code' => 'case_minutes.finalize']);
        $this->assertDatabaseHas('notification_types', ['code' => NotificationService::TYPE_CASE_MINUTE_FINALIZED]);

        $admin = $this->makeUser('admin', 'minute-unique-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-unique-satgas@university.ac.id');
        $case = $this->makeCase($admin, $satgas);
        $first = $this->makeMinute($case, $admin, 1);

        try {
            $this->makeMinute($case, $admin, 1);
            $this->fail('The unique Case/version constraint must reject duplicates.');
        } catch (QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505']);
        }

        $this->assertSame(1, $first->version);
    }

    public function test_admin_can_create_update_and_finalize_a_versioned_encrypted_case_minute(): void
    {
        $admin = $this->makeUser('admin', 'minute-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-satgas@university.ac.id');
        $case = $this->makeCase($admin, $satgas);
        $this->actingAsApi($admin);

        $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload([
            'status' => CaseMinuteStatus::Finalized->value,
            'version' => 99,
            'created_by' => $satgas->id,
        ]))->assertUnprocessable();
        $this->assertSame(0, CaseMinute::query()->where('case_id', $case->id)->count());

        $created = $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.projection', 'internal')
            ->assertJsonPath('data.status', CaseMinuteStatus::Draft->value)
            ->assertJsonPath('data.version', 1);
        $publicId = (string) $created->json('data.public_id');
        $lock = (string) $created->json('data.lock_version');

        $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())
            ->assertConflict()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteDraftExists);

        $this->assertNotSame(
            'Ringkasan internal yang bersifat rahasia.',
            DB::table('case_minutes')->where('public_id', $publicId)->value('internal_summary'),
        );
        $storedNarratives = DB::table('case_minutes')->where('public_id', $publicId)->first([
            'anonymized_summary',
            'outcome',
            'follow_up',
        ]);
        $this->assertNotSame('Ringkasan aman tanpa identitas langsung.', $storedNarratives->anonymized_summary);
        $this->assertNotSame('Hasil penanganan awal.', $storedNarratives->outcome);
        $this->assertNotSame('Tindak lanjut yang disepakati.', $storedNarratives->follow_up);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseMinuteCreated->value]);

        $updated = $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $lock,
            'outcome' => 'Hasil penanganan diperbarui.',
        ])->assertOk()->assertJsonPath('data.outcome', 'Hasil penanganan diperbarui.');

        $finalized = $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", [
            'lock_version' => $updated->json('data.lock_version'),
        ])->assertOk()
            ->assertJsonPath('data.status', CaseMinuteStatus::Finalized->value)
            ->assertJsonPath('data.finalizer.id', $admin->id);

        $auditCount = AuditLog::query()->where('action', AuditAction::CaseMinuteFinalized->value)->count();
        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", [
            'lock_version' => $lock,
        ])->assertOk()->assertJsonPath('data.status', CaseMinuteStatus::Finalized->value);
        $this->assertSame($auditCount, AuditLog::query()->where('action', AuditAction::CaseMinuteFinalized->value)->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CaseMinuteFinalized->value,
            'subject_id' => CaseMinute::query()->where('public_id', $publicId)->value('id'),
        ]);
        $this->assertStringNotContainsString(
            'Ringkasan internal yang bersifat rahasia.',
            json_encode(AuditLog::query()->where('action', AuditAction::CaseMinuteFinalized->value)->value('metadata')),
        );
        $this->assertNotNull($finalized->json('data.finalized_at'));
        $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $finalized->json('data.lock_version'),
            'outcome' => 'Finalized rows must remain immutable.',
        ])->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::CaseMinuteImmutable);
    }

    public function test_revision_supersedes_only_the_prior_finalized_version_and_keeps_history(): void
    {
        $admin = $this->makeUser('admin', 'minute-revision-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-revision-satgas@university.ac.id');
        $case = $this->makeCase($admin, $satgas, CaseStatusEnum::Recommendation);
        $this->actingAsApi($admin);

        $first = $this->createAndFinalize($case, $admin);
        $revision = $this->postJson("/api/v1/case-minutes/{$first->public_id}/revisions")
            ->assertCreated()
            ->assertJsonPath('data.status', CaseMinuteStatus::Draft->value)
            ->assertJsonPath('data.version', 2);
        $revisionId = (string) $revision->json('data.public_id');
        $this->assertDatabaseHas('case_minutes', [
            'public_id' => $revisionId,
            'supersedes_id' => $first->id,
            'status' => CaseMinuteStatus::Draft->value,
        ]);

        $finalizedRevision = $this->postJson("/api/v1/case-minutes/{$revisionId}/finalize", [
            'lock_version' => $revision->json('data.lock_version'),
        ])->assertOk()->assertJsonPath('data.status', CaseMinuteStatus::Finalized->value);

        $this->assertSame(CaseMinuteStatus::Superseded, $first->refresh()->status);
        $this->assertSame(CaseMinuteStatus::Finalized, CaseMinute::query()->where('public_id', $revisionId)->firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseMinuteRevisionCreated->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseMinuteSuperseded->value]);
        $this->assertSame(2, CaseMinute::query()->where('case_id', $case->id)->count());
        $this->assertNotNull($finalizedRevision->json('data.finalized_at'));
        $this->patchJson("/api/v1/case-minutes/{$first->public_id}", [
            'lock_version' => $first->refresh()->lockVersion(),
            'outcome' => 'Superseded rows must remain immutable.',
        ])->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::CaseMinuteImmutable);
        $this->postJson("/api/v1/case-minutes/{$first->public_id}/revisions")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteRevisionSourceInvalid);
    }

    public function test_optimistic_lock_required_fields_and_anonymized_identifier_safeguard_fail_closed(): void
    {
        $admin = $this->makeUser('admin', 'minute-guard-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-guard-satgas@university.ac.id');
        $case = $this->makeCase($admin, $satgas, CaseStatusEnum::Investigation, true);
        $this->actingAsApi($admin);

        $created = $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload([
            'anonymized_summary' => null,
        ]))->assertCreated();
        $publicId = (string) $created->json('data.public_id');
        $initialLock = (string) $created->json('data.lock_version');

        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", ['lock_version' => $initialLock])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteFinalizationRequired);

        $updated = $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $initialLock,
            'anonymized_summary' => 'Pelapor Bernama digunakan sebagai contoh yang tidak boleh lolos.',
        ])->assertOk();
        $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $initialLock,
            'outcome' => 'Tidak boleh memakai lock lama.',
        ])->assertStatus(409)->assertJsonPath('error_code', ApiErrorCode::CaseMinuteStale);

        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", ['lock_version' => $updated->json('data.lock_version')])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteAnonymizedIdentityDetected);

        $reporter = $case->report()->firstOrFail()->reporter()->firstOrFail();
        $reporter->forceFill(['name' => 'Pélapor Bernama'])->save();
        $unicodeUpdated = $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $updated->json('data.lock_version'),
            'anonymized_summary' => "Pe\u{301}lapor Bernama tidak boleh lolos hanya karena bentuk Unicode berbeda.",
        ])->assertOk();
        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", ['lock_version' => $unicodeUpdated->json('data.lock_version')])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteAnonymizedIdentityDetected)
            ->assertDontSee('Pélapor Bernama');

        $phoneUpdated = $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $unicodeUpdated->json('data.lock_version'),
            'anonymized_summary' => 'Nomor 0812-3456-7890 tidak boleh lolos karena tanda baca.',
        ])->assertOk();
        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", ['lock_version' => $phoneUpdated->json('data.lock_version')])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteAnonymizedIdentityDetected);
        $this->assertSame(CaseMinuteStatus::Draft, CaseMinute::query()->where('public_id', $publicId)->firstOrFail()->status);
    }

    public function test_role_campus_and_projection_rules_are_enforced_without_lead_shortcut(): void
    {
        $admin = $this->makeUser('admin', 'minute-projection-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-projection-satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'minute-projection-other-satgas@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'minute-projection-super@university.ac.id');
        $reporter = $this->makeUser('reporter', 'minute-projection-reporter@university.ac.id');
        $crossCampusAdmin = $this->makeUser('admin', 'minute-projection-cross@university.ac.id', 'DEMO-ST');
        $case = $this->makeCase($admin, $satgas);
        CaseAssignment::query()->where('case_id', $case->id)->update(['is_lead' => false]);

        $this->actingAsApi($satgas);
        $draft = $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.internal_summary', 'Ringkasan internal yang bersifat rahasia.');
        $publicId = (string) $draft->json('data.public_id');
        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", ['lock_version' => $draft->json('data.lock_version')])
            ->assertForbidden();

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/case-minutes/{$publicId}")->assertForbidden();
        $this->actingAsApi($crossCampusAdmin);
        $this->getJson("/api/v1/case-minutes/{$publicId}")->assertForbidden();

        CaseAssignment::query()->where('case_id', $case->id)->where('satgas_id', $satgas->id)->update(['is_active' => false]);
        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/case-minutes/{$publicId}")->assertForbidden();
        $this->patchJson("/api/v1/case-minutes/{$publicId}", [
            'lock_version' => $draft->json('data.lock_version'),
            'outcome' => 'Former assignee cannot write.',
        ])->assertForbidden();
        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/case-minutes/{$publicId}")->assertForbidden();

        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/cases/{$case->id}/minutes")
            ->assertOk()
            ->assertJsonPath('data.projection', 'metadata')
            ->assertJsonPath('data.items.0.projection', 'metadata')
            ->assertJsonMissingPath('data.items.0.internal_summary')
            ->assertJsonMissingPath('data.items.0.anonymized_summary')
            ->assertJsonMissingPath('data.items.0.outcome')
            ->assertJsonMissingPath('data.items.0.follow_up')
            ->assertJsonMissingPath('data.items.0.capabilities')
            ->assertJsonMissingPath('data.items.0.creator');
    }

    public function test_non_eligible_and_terminal_case_stages_cannot_create_case_minutes(): void
    {
        $admin = $this->makeUser('admin', 'minute-terminal-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-terminal-satgas@university.ac.id');
        $this->actingAsApi($admin);

        foreach ([
            CaseStatusEnum::Decision,
            CaseStatusEnum::Decided,
            CaseStatusEnum::Recovery,
            CaseStatusEnum::Monitoring,
            CaseStatusEnum::Closed,
            CaseStatusEnum::Escalated,
        ] as $status) {
            $case = $this->makeCase($admin, $satgas, $status);

            $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())
                ->assertUnprocessable();
            $this->assertSame(0, CaseMinute::query()->where('case_id', $case->id)->count());
        }

        $withdrawn = $this->makeCase($admin, $satgas, CaseStatusEnum::Withdrawn);
        $this->postJson("/api/v1/cases/{$withdrawn->id}/minutes", $this->payload())
            ->assertConflict()
            ->assertJsonPath('error_code', ApiErrorCode::CaseOperationallyTerminal);
        $this->assertSame(0, CaseMinute::query()->where('case_id', $withdrawn->id)->count());
    }

    public function test_case_stage_and_pending_formal_withdrawal_guard_ba_mutations(): void
    {
        $admin = $this->makeUser('admin', 'minute-state-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-state-satgas@university.ac.id');
        $invalidCase = $this->makeCase($admin, $satgas, CaseStatusEnum::Decision);
        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$invalidCase->id}/minutes", $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseMinuteStageUnavailable);

        $case = $this->makeCase($admin, $satgas);
        $report = $case->report()->firstOrFail();
        ReportWithdrawal::query()->create([
            'report_id' => $report->id,
            'case_id' => $case->id,
            'requester_id' => $report->reporter_id,
            'registration_number_snapshot' => $report->registration_number,
            'requester_display_name_snapshot' => 'Pelapor Uji',
            'request_type' => ReportWithdrawalRequestType::FormalWithdrawal,
            'status' => ReportWithdrawalStatus::PendingReview,
            'reason' => 'Permohonan pencabutan formal sedang diverifikasi.',
            'previous_report_status' => $report->status,
            'previous_case_status' => $case->status?->name,
            'lock_version' => 0,
        ]);
        $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('error_code', ApiErrorCode::WithdrawalPendingReview);
    }

    public function test_audit_failure_rolls_back_finalization_and_suppresses_after_commit_notification(): void
    {
        $admin = $this->makeUser('admin', 'minute-audit-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-audit-satgas@university.ac.id');
        $case = $this->makeCase($admin, $satgas);
        $this->actingAsApi($admin);
        $created = $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())->assertCreated();
        $publicId = (string) $created->json('data.public_id');

        $audit = Mockery::mock(AuditLogService::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit persistence failed'));
        $this->app->instance(AuditLogService::class, $audit);
        Notification::fake();

        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", [
            'lock_version' => $created->json('data.lock_version'),
        ])->assertStatus(500);

        $minute = CaseMinute::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertSame(CaseMinuteStatus::Draft, $minute->status);
        $this->assertNull($minute->finalized_at);
        $this->assertNull($minute->finalized_by);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::CaseMinuteFinalized->value)->count());
        Notification::assertNothingSent();
    }

    public function test_finalization_notification_targets_draft_creator_and_active_assignees_without_narratives(): void
    {
        $admin = $this->makeUser('admin', 'minute-notify-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'minute-notify-satgas@university.ac.id');
        $creator = $this->makeUser('satgas_ppks', 'minute-notify-creator@university.ac.id');
        $formerSatgas = $this->makeUser('satgas_ppks', 'minute-notify-former@university.ac.id');
        $inactiveSatgas = $this->makeUser('satgas_ppks', 'minute-notify-inactive@university.ac.id');
        $inactiveSatgas->forceFill(['is_active' => false])->save();
        $case = $this->makeCase($admin, $satgas);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $formerSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => false,
            'assigned_at' => now(),
        ]);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $inactiveSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        $minute = $this->makeMinute($case, $creator, 1);
        $minute->forceFill([
            'status' => CaseMinuteStatus::Finalized,
            'finalized_by' => $admin->id,
            'finalized_at' => now(),
        ])->save();

        Notification::fake();
        app(NotificationService::class)->caseMinuteFinalized($minute, $admin);

        Notification::assertSentTo($creator, WorkflowDatabaseNotification::class, function ($notification) use ($creator, $minute): bool {
            $data = $notification->toDatabase($creator);

            return ($data['case_minute_public_id'] ?? null) === $minute->public_id
                && ($data['case_minute_version'] ?? null) === 1
                && ! array_key_exists('internal_summary', $data)
                && ! array_key_exists('anonymized_summary', $data);
        });
        Notification::assertSentTo($satgas, WorkflowDatabaseNotification::class);
        Notification::assertNotSentTo($admin, WorkflowDatabaseNotification::class);
        Notification::assertNotSentTo($formerSatgas, WorkflowDatabaseNotification::class);
        Notification::assertNotSentTo($inactiveSatgas, WorkflowDatabaseNotification::class);
        $this->assertCount(1, Notification::sent($satgas, WorkflowDatabaseNotification::class));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'occurred_at' => now()->toDateString(),
            'internal_summary' => 'Ringkasan internal yang bersifat rahasia.',
            'anonymized_summary' => 'Ringkasan aman tanpa identitas langsung.',
            'outcome' => 'Hasil penanganan awal.',
            'follow_up' => 'Tindak lanjut yang disepakati.',
        ], $overrides);
    }

    private function createAndFinalize(CaseRecord $case, User $admin): CaseMinute
    {
        $created = $this->postJson("/api/v1/cases/{$case->id}/minutes", $this->payload())->assertCreated();
        $publicId = (string) $created->json('data.public_id');
        $this->postJson("/api/v1/case-minutes/{$publicId}/finalize", [
            'lock_version' => $created->json('data.lock_version'),
        ])->assertOk();

        return CaseMinute::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function makeMinute(CaseRecord $case, User $actor, int $version): CaseMinute
    {
        return CaseMinute::query()->forceCreate([
            'case_id' => $case->id,
            'version' => $version,
            'status' => CaseMinuteStatus::Draft,
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function makeCase(
        User $admin,
        User $satgas,
        CaseStatusEnum $statusName = CaseStatusEnum::Investigation,
        bool $anonymous = false,
    ): CaseRecord {
        $reporter = $this->makeUser('reporter', 'minute-reporter-'.(Report::query()->count() + 1).'@university.ac.id');
        $reporter->forceFill([
            'name' => $anonymous ? 'Pelapor Bernama' : 'Pelapor Uji',
            'nim' => 'NIM-TEST-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'phone_number' => '081234567890',
        ])->save();
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-MINUTE-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => $anonymous ? 'anonymous' : 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan untuk pengujian Berita Acara.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung kampus',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Terlapor Uji',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor uji.',
            'witness_info' => 'Saksi uji.',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-MINUTE-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'investigation_started_at' => $statusName === CaseStatusEnum::Investigation ? now() : null,
            'recommendation_at' => $statusName === CaseStatusEnum::Recommendation ? now() : null,
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

    private function makeUser(string $roleCode, string $email, string $universityCode = 'DEMO-UNIV'): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
            'university_id' => University::query()->where('code', $universityCode)->firstOrFail()->id,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
