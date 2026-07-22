<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Recovery;
use App\Models\RecoveryStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class RecoveryFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_recovery_foundation_tables_and_master_data_exist(): void
    {
        $this->assertTrue(Schema::hasTable('recovery_statuses'));
        $this->assertTrue(Schema::hasTable('recoveries'));
        $this->assertTrue(Schema::hasTable('recovery_status_histories'));
        $this->assertTrue(Schema::hasTable('recovery_monitorings'));
        $this->assertTrue(Schema::hasColumn('recoveries', 'decision_id'));
        $this->assertFalse(Schema::hasColumn('recoveries', 'case_id'));
        $this->assertFalse(Schema::hasColumn('recoveries', 'assigned_satgas_id'));
        $this->assertFalse(Schema::hasColumn('recovery_monitorings', 'deleted_at'));

        $planned = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Planned->value)->firstOrFail();

        $this->assertContains(RecoveryStatusEnum::Ongoing->value, $planned->valid_transitions);
        $this->assertContains(RecoveryStatusEnum::Discontinued->value, $planned->valid_transitions);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.monitor']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-20']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-21']);
    }

    public function test_admin_can_create_multiple_recoveries_for_finalized_decision(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);

        $this->actingAsApi($admin);
        $firstRecoveryId = $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RecoveryStatusEnum::Planned->value)
            ->assertJsonPath('data.recovery_plan', 'Rencana pendampingan korban secara berkala.')
            ->assertJsonMissingPath('data.decision.decision_content')
            ->assertJsonMissingPath('data.recommendation.conclusion')
            ->json('data.id');

        $secondRecoveryId = $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertCreated()
            ->json('data.id');

        Recovery::query()
            ->whereIn('id', [$firstRecoveryId, $secondRecoveryId])
            ->update(['created_at' => now()->startOfSecond()]);

        $this->getJson("/api/v1/decisions/{$decision->id}/recoveries")
            ->assertOk()
            ->assertJsonPath('data.0.id', $secondRecoveryId)
            ->assertJsonPath('data.1.id', $firstRecoveryId);

        $this->assertDatabaseCount('recoveries', 2);
        $this->assertDatabaseCount('recovery_status_histories', 2);
        $this->assertSame(CaseStatusEnum::Recovery->value, $case->refresh()->status->name);
        $this->assertNull($case->closed_at);
        $this->assertSame(DecisionStatusEnum::Finalized->value, $decision->refresh()->status->name);
    }

    public function test_recovery_creation_requires_finalized_decision(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeDecision($this->makeAcceptedRecommendation($case, $satgas), $admin, DecisionStatusEnum::Recorded);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('recoveries', 0);
    }

    public function test_recovery_management_is_same_campus_admin_only_and_monitoring_is_assigned_satgas_only(): void
    {
        $admin = $this->makeUser('admin', 'admin-owner@university.ac.id');
        $otherAdmin = $this->makeUser('admin', 'admin-other@university.ac.id', 'DEMO-ST');
        $superAdmin = $this->makeUser('super_admin', 'super-readonly@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-owner@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertForbidden();

        $this->actingAsApi($otherAdmin);
        $this->getJson("/api/v1/decisions/{$decision->id}/recoveries")->assertForbidden();
        $this->getJson("/api/v1/recoveries/{$recovery->id}")->assertForbidden();
        $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertForbidden();
        $this->patchJson("/api/v1/recoveries/{$recovery->id}", [
            'recovery_plan' => 'Admin kampus lain tidak boleh mengubah rencana pemulihan ini.',
        ])->assertForbidden();
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertForbidden();

        config()->set('oversight.cross_campus_sensitive_read', true);
        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/recoveries/{$recovery->id}")
            ->assertOk()
            ->assertJsonPath('data.recovery_plan', 'Rencana pendampingan korban secara berkala.');
        $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertForbidden();
        $this->patchJson("/api/v1/recoveries/{$recovery->id}", [
            'recovery_plan' => 'Super Admin tetap hanya-baca meskipun flag sensitif aktif.',
        ])->assertForbidden();
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertForbidden();
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertForbidden();
    }

    public function test_assigned_satgas_can_read_recovery_and_create_monitoring_but_cannot_complete(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recoveries/{$recovery->id}")
            ->assertOk()
            ->assertJsonPath('data.recovery_plan', 'Rencana pendampingan korban secara berkala.');

        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertCreated()
            ->assertJsonPath('data.condition_summary', 'Kondisi korban stabil dan tetap membutuhkan pendampingan.');

        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertForbidden();

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/recoveries/{$recovery->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('recovery_monitorings', 1);
        $this->assertSame(RecoveryStatusEnum::Ongoing->value, $recovery->refresh()->status->name);
    }

    public function test_monitoring_requires_ongoing_recovery_and_never_completes_recovery(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertUnprocessable();

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertCreated();

        $this->assertSame(RecoveryStatusEnum::Ongoing->value, $recovery->refresh()->status->name);
    }

    public function test_admin_status_transitions_are_terminal_and_never_mutate_case_or_decision(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'recovery_monitoring_required');

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertCreated();

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RecoveryStatusEnum::Completed->value);

        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
        ])->assertUnprocessable();

        $recovery->refresh();
        $this->assertNotNull($recovery->completed_at);
        $this->assertSame(CaseStatusEnum::Recovery->value, $case->refresh()->status->name);
        $this->assertNull($case->closed_at);
        $this->assertSame(DecisionStatusEnum::Finalized->value, $decision->refresh()->status->name);
        $this->assertDatabaseCount('recovery_status_histories', 3);
    }

    public function test_case_monitoring_and_closure_require_completed_recovery_with_monitoring(): void
    {
        $admin = $this->makeUser('admin', 'admin-gates@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-gates@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Monitoring->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_recovery_completion_required');

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.primary_tip_code', 'recovery_needs_monitoring');
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertCreated();
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.primary_tip_code', 'wait_for_campus_admin_recovery_completion');
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Monitoring->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_recovery_completion_required');

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.primary_tip_code', 'recovery_can_complete');
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertOk();
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.primary_tip_code', 'wait_for_satgas_monitoring_stage');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.primary_tip_code', 'recovery_completed_advance_monitoring');
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Monitoring->value,
        ])->assertOk()->assertJsonPath('data.status', CaseStatusEnum::Monitoring->value);

        $ongoing = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Ongoing->value)->firstOrFail();
        $recovery->forceFill([
            'status_code' => $ongoing->code,
            'completed_at' => null,
        ])->save();

        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Closed->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_generic_closure_forbidden');

        $completed = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Completed->value)->firstOrFail();
        $recovery->forceFill([
            'status_code' => $completed->code,
            'completed_at' => now(),
        ])->save();

        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Closed->value,
        ])->assertUnprocessable()->assertJsonPath('error_code', 'case_generic_closure_forbidden');
    }

    public function test_discontinued_recovery_does_not_satisfy_case_progression_gate(): void
    {
        $admin = $this->makeUser('admin', 'admin-discontinued@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-discontinued@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
            'discontinuation_reason' => 'Penanganan dihentikan berdasarkan evaluasi Admin Kampus.',
        ])->assertOk();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Monitoring->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'case_recovery_completion_required');
    }

    public function test_recovery_status_options_follow_access_scope_and_soft_warning_metadata(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);
        $recovery = $this->makeRecovery($decision, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->getJson("/api/v1/recoveries/{$recovery->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', RecoveryStatusEnum::Ongoing->value)
            ->assertJsonPath('data.valid_transitions.0.name', RecoveryStatusEnum::Completed->value)
            ->assertJsonPath('data.valid_transitions.0.soft_warning', 'SOP recommends 3-6 months of monitoring before completing recovery. This is advisory and does not block completion.')
            ->assertJsonPath('data.valid_transitions.1.name', RecoveryStatusEnum::Discontinued->value);

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recoveries/{$recovery->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', RecoveryStatusEnum::Ongoing->value)
            ->assertJsonCount(0, 'data.valid_transitions');

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/recoveries/{$recovery->id}/status-options")
            ->assertForbidden();
    }

    public function test_recovery_workflow_dispatches_audit_logs_and_notifications(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $otherSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);

        $this->actingAsApi($admin);
        $recoveryId = $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertCreated()
            ->json('data.id');

        $recovery = Recovery::query()->findOrFail($recoveryId);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecoveryCreated->value,
            'category' => AuditCategory::Recovery->value,
            'subject_type' => $recovery->getMorphClass(),
            'subject_id' => $recovery->id,
        ]);

        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-20')->count());
        $this->assertSame(1, $this->notificationsByType($otherSatgas, 'NOTIF-20')->count());
        $this->assertSame(0, $this->notificationsByType($admin, 'NOTIF-20')->count());

        $this->patchJson("/api/v1/recoveries/{$recovery->id}", [
            'recovery_plan' => 'Rencana pemulihan yang diperbarui dan tetap sensitif.',
            'support_needs' => 'Kebutuhan dukungan lanjutan yang tidak boleh bocor.',
            'notes' => 'Catatan pemulihan pembaruan rahasia.',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecoveryUpdated->value,
            'category' => AuditCategory::Recovery->value,
            'subject_type' => $recovery->getMorphClass(),
            'subject_id' => $recovery->id,
        ]);

        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecoveryStatusChanged->value,
            'category' => AuditCategory::Recovery->value,
            'subject_type' => $recovery->getMorphClass(),
            'subject_id' => $recovery->id,
        ]);

        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-21')->count());
        $this->assertSame(1, $this->notificationsByType($otherSatgas, 'NOTIF-21')->count());
        $this->assertSame(0, $this->notificationsByType($admin, 'NOTIF-21')->count());

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecoveryMonitoringCreated->value,
            'category' => AuditCategory::Recovery->value,
        ]);

        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-21')->count());

        $auditJson = AuditLog::query()
            ->whereIn('action', [
                AuditAction::RecoveryCreated->value,
                AuditAction::RecoveryUpdated->value,
                AuditAction::RecoveryStatusChanged->value,
                AuditAction::RecoveryMonitoringCreated->value,
            ])
            ->get()
            ->toJson();

        $this->assertStringNotContainsString('Rencana pendampingan korban secara berkala', $auditJson);
        $this->assertStringNotContainsString('Kebutuhan dukungan psikologis', $auditJson);
        $this->assertStringNotContainsString('Catatan pemulihan rahasia', $auditJson);
        $this->assertStringNotContainsString('Kondisi korban stabil', $auditJson);
        $this->assertStringNotContainsString('Jadwalkan sesi lanjutan', $auditJson);
    }

    public function test_monitoring_creation_rolls_back_when_audit_persistence_fails(): void
    {
        $admin = $this->makeUser('admin', 'monitoring-admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'monitoring-satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeFinalizedDecision($case, $admin, $satgas);

        $this->actingAsApi($admin);
        $recoveryId = $this->postJson("/api/v1/decisions/{$decision->id}/recoveries", $this->recoveryPayload())
            ->assertCreated()
            ->json('data.id');
        $this->patchJson("/api/v1/recoveries/{$recoveryId}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertOk();

        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recoveryId}/monitoring", $this->monitoringPayload())
            ->assertServerError();

        $this->assertDatabaseCount('recovery_monitorings', 0);
    }

    private function recoveryPayload(array $overrides = []): array
    {
        return array_merge([
            'recovery_type_code' => 'RCV-01',
            'recovery_plan' => 'Rencana pendampingan korban secara berkala.',
            'support_needs' => 'Kebutuhan dukungan psikologis.',
            'notes' => 'Catatan pemulihan rahasia.',
        ], $overrides);
    }

    private function monitoringPayload(array $overrides = []): array
    {
        return array_merge([
            'monitoring_date' => now()->toDateString(),
            'condition_summary' => 'Kondisi korban stabil dan tetap membutuhkan pendampingan.',
            'follow_up_plan' => 'Jadwalkan sesi lanjutan.',
            'notes' => 'Catatan monitoring rahasia.',
        ], $overrides);
    }

    private function makeRecovery(Decision $decision, User $admin): Recovery
    {
        $status = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Planned->value)->firstOrFail();

        $recovery = Recovery::query()->create([
            'decision_id' => $decision->id,
            'recovery_type_code' => 'RCV-01',
            'status_code' => $status->code,
            'created_by' => $admin->id,
            'recovery_plan' => 'Rencana pendampingan korban secara berkala.',
            'support_needs' => 'Kebutuhan dukungan psikologis.',
            'notes' => 'Catatan pemulihan rahasia.',
        ]);

        $recovery->statusHistories()->create([
            'from_status_code' => null,
            'to_status_code' => $status->code,
            'changed_by' => $admin->id,
            'changed_at' => now(),
        ]);

        return $recovery;
    }

    private function makeFinalizedDecision(CaseRecord $case, User $admin, User $satgas): Decision
    {
        return $this->makeDecision($this->makeAcceptedRecommendation($case, $satgas), $admin, DecisionStatusEnum::Finalized);
    }

    private function makeDecision(Recommendation $recommendation, User $admin, DecisionStatusEnum $statusName): Decision
    {
        $status = DecisionStatus::query()->where('name', $statusName->value)->firstOrFail();

        $decision = Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => $status->code,
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_number' => 'SK-2026-001',
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'Ringkasan keputusan institusi.',
            'decision_content' => 'Isi keputusan lengkap yang terenkripsi saat tersimpan.',
            'recorded_at' => now(),
            'finalized_at' => $statusName === DecisionStatusEnum::Finalized ? now() : null,
        ]);

        $decision->statusHistories()->create([
            'from_status_code' => null,
            'to_status_code' => $status->code,
            'changed_by' => $admin->id,
            'changed_at' => now(),
        ]);

        return $decision;
    }

    private function makeAcceptedRecommendation(CaseRecord $case, User $satgas): Recommendation
    {
        $status = RecommendationStatus::query()->where('name', RecommendationStatusEnum::Accepted->value)->firstOrFail();
        $investigation = $this->makeCompletedInvestigation($case, $satgas);

        return Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $status->code,
            'conclusion' => 'Kesimpulan rekomendasi rahasia.',
            'recommended_actions' => 'Tindakan rekomendasi untuk penanganan kasus.',
            'sanction_recommendation' => 'Rekomendasi sanksi.',
            'recovery_recommendation' => 'Rekomendasi pemulihan.',
            'prevention_recommendation' => 'Rekomendasi pencegahan.',
            'submitted_at' => now(),
        ]);
    }

    private function makeCompletedInvestigation(CaseRecord $case, User $satgas): Investigation
    {
        $status = InvestigationStatus::query()->where('name', 'completed')->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Plan investigasi rahasia.',
            'findings' => 'Temuan investigasi rahasia.',
            'conclusion' => 'Kesimpulan investigasi rahasia.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function makeDecisionCase(User $admin, User $satgas): CaseRecord
    {
        $report = $this->makeReport();
        $status = CaseStatus::query()->where('name', CaseStatusEnum::Recovery->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'decision_at' => now(),
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

    private function makeReport(): Report
    {
        $reporter = $this->makeUser('reporter', 'recovery-reporter-'.(Report::query()->count() + 1).'@university.ac.id');

        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji recovery foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian recovery.',
            'witness_info' => 'Informasi saksi untuk pengujian recovery.',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);
    }

    private function makeUser(
        string $roleCode,
        string $email,
        string $universityCode = 'DEMO-UNIV',
    ): User {
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
