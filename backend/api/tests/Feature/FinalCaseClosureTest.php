<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Recovery;
use App\Models\RecoveryMonitoring;
use App\Models\RecoveryStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\University;
use App\Services\AuditLogService;
use App\Support\ApiErrorCode;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class FinalCaseClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_migration_and_final_outcome_storage_are_safe_and_encrypted(): void
    {
        $this->assertTrue(Schema::hasTable('case_final_summaries'));
        $this->assertTrue(Schema::hasColumn('recoveries', 'discontinuation_reason'));
        $this->assertTrue(Schema::hasColumn('case_final_summaries', 'published_at'));

        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->actingAsApi($admin);
        $invalidPayload = $this->summaryPayload();
        unset($invalidPayload['official_statement']);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('official_statement');
        $summaryId = $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())
            ->assertCreated()
            ->json('data.id');

        $this->assertNotSame(
            $this->summaryPayload()['official_statement'],
            DB::table('case_final_summaries')->where('id', $summaryId)->value('official_statement'),
        );
        $this->assertDatabaseCount('case_final_summaries', 1);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())
            ->assertUnprocessable();
    }

    public function test_summary_mutation_is_same_campus_admin_only_and_read_only_roles_can_view(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->scenario();
        $otherUniversity = University::query()->create([
            'code' => 'OTHER-UNIV',
            'name' => 'Other University',
            'type' => 'universitas',
            'is_active' => true,
        ]);
        $otherAdmin = $this->makeUser('admin', 'other-admin@example.test', $otherUniversity);
        $superAdmin = $this->makeUser('super_admin', 'oversight@example.test', null);

        $this->actingAsApi($otherAdmin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertForbidden();
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertForbidden();
        $this->actingAsApi($superAdmin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertForbidden();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertCreated();
        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/cases/{$case->id}/final-summary")->assertOk()->assertJsonPath('data.summary.is_published', false);
        $this->actingAsApi($superAdmin);
        config()->set('oversight.cross_campus_sensitive_read', true);
        $this->getJson("/api/v1/cases/{$case->id}/final-summary")->assertOk();
    }

    public function test_reporter_sees_only_published_safe_summary_and_never_raw_discontinuation_reason(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertCreated();

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/portal/reports/{$case->registration_number}/handling-progress")
            ->assertOk()
            ->assertJsonPath('data.final_summary', null);

        $this->makeRecoveryCompleted($recovery, $satgas);
        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->actingAsApi($reporter);
        $response = $this->getJson("/api/v1/portal/reports/{$case->registration_number}/handling-progress")
            ->assertOk()
            ->assertJsonPath('data.final_summary.state', 'published')
            ->assertJsonPath('data.final_summary.outcome_code', 'resolved')
            ->assertJsonPath('data.final_summary.official_statement', $this->summaryPayload()['official_statement']);
        $payload = $response->getContent();
        $this->assertStringNotContainsString('discontinuation_reason', $payload);
        $this->assertStringNotContainsString((string) $admin->email, $payload);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload(['closing_explanation' => 'Changed']))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::FinalSummaryImmutable);
    }

    public function test_publication_requires_terminal_compatible_recovery_and_blocks_anonymous_identity(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario('anonymous');
        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload([
            'official_statement' => "Penanganan untuk {$reporter->name} telah selesai.",
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::FinalSummaryAnonymousIdentityDetected);

        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertCreated();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseClosureRecoveryRequired);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
            'discontinuation_reason' => 'Layanan dihentikan berdasarkan evaluasi kebutuhan.',
        ])->assertOk();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::FinalOutcomeIncompatible);
        $this->patchJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload(['outcome_code' => 'discontinued']))
            ->assertOk();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")->assertOk();

        $this->actingAsApi($reporter);
        $content = $this->getJson("/api/v1/portal/reports/{$case->registration_number}/handling-progress")
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString($reporter->name, $content);
        $this->assertStringNotContainsString($reporter->email, $content);
    }

    public function test_discontinued_recovery_requires_encrypted_reason_and_is_terminal(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
        ])->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::RecoveryDiscontinuationReasonRequired);

        $reason = 'Pemulihan dihentikan setelah penilaian kebutuhan layanan.';
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
            'discontinuation_reason' => $reason,
        ])->assertOk()->assertJsonPath('data.discontinuation_reason', $reason);
        $this->assertNotSame($reason, DB::table('recoveries')->where('id', $recovery->id)->value('discontinuation_reason'));
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Ongoing->value,
        ])->assertUnprocessable();

        $audit = AuditLog::query()->where('action', AuditAction::RecoveryDiscontinued->value)->firstOrFail();
        $this->assertStringNotContainsString($reason, json_encode($audit->metadata));
    }

    public function test_monitoring_ownership_and_completed_recovery_gate_remain_enforced(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $superAdmin = $this->makeUser('super_admin', 'super-monitor@example.test', null);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::RecoveryMonitoringRequired);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())->assertForbidden();
        $this->actingAsApi($superAdmin);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())->assertForbidden();
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())->assertCreated();

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertOk();
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())->assertUnprocessable();
    }

    public function test_completed_closure_requires_stage_recovery_monitoring_summary_and_assignment(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->makeRecoveryCompleted($recovery, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::CaseClosureStageInvalid);
        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => CaseStatusEnum::Monitoring->value])->assertOk();
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::CaseClosureSummaryRequired);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertCreated();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")->assertOk();
        $unassigned = $this->makeUser('satgas_ppks', 'unassigned@example.test', $admin->university);
        $this->actingAsApi($unassigned);
        $this->postJson("/api/v1/cases/{$case->id}/close")->assertForbidden();

        $satgas->forceFill(['is_active' => false])->save();
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")->assertForbidden();
        $satgas->forceFill(['is_active' => true])->save();

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Closed->value);
        $this->assertNotNull($case->fresh()->closed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CaseClosed->value]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $reporter->id]);
    }

    public function test_discontinued_closure_is_direct_and_does_not_require_monitoring(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
            'discontinuation_reason' => 'Penanganan dihentikan berdasarkan evaluasi layanan.',
        ])->assertOk();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload(['outcome_code' => 'discontinued']))->assertCreated();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")->assertOk();
        $this->assertDatabaseCount('recovery_monitorings', 0);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Closed->value);
    }

    public function test_published_summary_prevents_a_new_recovery_from_reopening_finalized_handling(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Discontinued->value,
            'discontinuation_reason' => 'Penanganan dihentikan berdasarkan evaluasi layanan.',
        ])->assertOk();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload(['outcome_code' => 'discontinued']))
            ->assertCreated();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")->assertOk();

        $this->postJson("/api/v1/decisions/{$recovery->decision_id}/recoveries", [
            'recovery_type_code' => 'RCV-01',
            'recovery_plan' => 'Rencana baru tidak boleh membuka kembali penanganan yang telah difinalisasi.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::FinalSummaryImmutable);

        $this->assertDatabaseCount('recoveries', 1);
    }

    public function test_closure_rechecks_recovery_monitoring_reason_and_outcome_inside_transaction(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $monitoringCaseStatus = CaseStatus::query()->where('name', CaseStatusEnum::Monitoring->value)->firstOrFail();
        $case->forceFill(['status_code' => $monitoringCaseStatus->code])->save();
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseClosureRecoveryRequired);

        $completed = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Completed->value)->firstOrFail();
        $recovery->forceFill(['status_code' => $completed->code, 'completed_at' => now()])->save();
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseClosureMonitoringRequired);

        $recoveryCaseStatus = CaseStatus::query()->where('name', CaseStatusEnum::Recovery->value)->firstOrFail();
        $discontinued = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Discontinued->value)->firstOrFail();
        $case->forceFill(['status_code' => $recoveryCaseStatus->code])->save();
        $recovery->forceFill([
            'status_code' => $discontinued->code,
            'completed_at' => null,
            'discontinued_at' => now(),
            'discontinuation_reason' => null,
        ])->save();
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::RecoveryDiscontinuationReasonRequired);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertCreated();
        $summary = CaseFinalSummary::query()->where('case_id', $case->id)->firstOrFail();
        $summary->forceFill(['published_by' => $admin->id, 'published_at' => now()])->save();
        $recovery->forceFill(['discontinuation_reason' => 'Alasan terminal tersedia.'])->save();

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::FinalOutcomeIncompatible);
    }

    public function test_generic_closure_is_rejected_and_failed_closure_rolls_back(): void
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->readyCompletedClosure();
        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => CaseStatusEnum::Closed->value])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseGenericClosureForbidden);

        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });
        $this->postJson("/api/v1/cases/{$case->id}/close")->assertServerError();
        $this->assertSame(CaseStatusEnum::Monitoring->value, $case->fresh()->status?->name);
        $this->assertNull($case->fresh()->closed_at);
    }

    public function test_historical_closed_case_without_summary_remains_readable_with_legacy_fallback(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->scenario();
        $closed = CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail();
        $case->forceFill(['status_code' => $closed->code, 'closed_at' => now()])->save();
        $superAdmin = $this->makeUser('super_admin', 'legacy-super@example.test', null);

        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.facts.finalization_path', 'legacy_completion');
        $this->assertDatabaseMissing('case_final_summaries', ['case_id' => $case->id]);

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/portal/reports/{$case->registration_number}/handling-progress")
            ->assertOk()
            ->assertJsonPath('data.case.state', 'completed')
            ->assertJsonPath('data.final_summary.state', 'legacy_completion');
    }

    public function test_workflow_context_keeps_decision_capability_and_exposes_finalization_reasons(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->scenario();
        $this->actingAsApi($admin);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.actions.create_final_summary.allowed', true)
            ->assertJsonPath('data.workflow_context.actions.complete_recovery.reason_code', ApiErrorCode::RecoveryMonitoringRequired);

        $decisionStatus = CaseStatus::query()->where('name', CaseStatusEnum::Decision->value)->firstOrFail();
        $case->forceFill(['status_code' => $decisionStatus->code])->save();
        $case->recommendation?->decision?->delete();
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.actions.create_decision.allowed', true);
    }

    public function test_closure_audit_and_reporter_notification_exclude_sensitive_narratives(): void
    {
        [$admin, $satgas, $reporter, $case] = $this->readyCompletedClosure();
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/close")->assertOk();

        $audit = AuditLog::query()->where('action', AuditAction::CaseClosed->value)->firstOrFail();
        $auditJson = json_encode([$audit->metadata, $audit->before_changes, $audit->after_changes]);
        $this->assertStringNotContainsString($this->summaryPayload()['official_statement'], $auditJson);
        $this->assertStringNotContainsString($reporter->email, $auditJson);

        $notification = DB::table('notifications')->where('notifiable_id', $reporter->id)->latest('created_at')->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString($this->summaryPayload()['official_statement'], (string) $notification->data);
        $this->assertStringNotContainsString($reporter->email, (string) $notification->data);
    }

    /** @return array{User, User, User, CaseRecord, Recovery} */
    private function readyCompletedClosure(): array
    {
        [$admin, $satgas, $reporter, $case, $recovery] = $this->scenario();
        $this->makeRecoveryCompleted($recovery, $satgas);
        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => CaseStatusEnum::Monitoring->value])->assertOk();
        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/final-summary", $this->summaryPayload())->assertCreated();
        $this->postJson("/api/v1/cases/{$case->id}/final-summary/publish")->assertOk();

        return [$admin, $satgas, $reporter, $case->fresh('status'), $recovery->fresh('status')];
    }

    private function makeRecoveryCompleted(Recovery $recovery, User $satgas): void
    {
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recoveries/{$recovery->id}/monitoring", $this->monitoringPayload())->assertCreated();
        $admin = $recovery->creator;
        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/recoveries/{$recovery->id}/status", [
            'status' => RecoveryStatusEnum::Completed->value,
        ])->assertOk();
    }

    /** @return array{User, User, User, CaseRecord, Recovery} */
    private function scenario(string $reportType = 'confidential'): array
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $admin = $this->makeUser('admin', 'admin-'.uniqid().'@example.test', $university);
        $satgas = $this->makeUser('satgas_ppks', 'satgas-'.uniqid().'@example.test', $university);
        $reporter = $this->makeUser('reporter', 'reporter-'.uniqid().'@example.test', $university, 'Demo Reporter Secret');
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.uniqid(),
            'tracking_code' => null,
            'report_type' => $reportType,
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi aman untuk pengujian finalisasi kasus dan projection Pelapor.',
            'incident_date' => now()->subMonth()->toDateString(),
            'incident_location' => 'Lokasi pengujian',
            'status' => ReportStatus::Forwarded->value,
            'submitted_at' => now()->subMonth(),
            'forwarded_at' => now()->subWeeks(3),
        ]);
        $caseStatus = CaseStatus::query()->where('name', CaseStatusEnum::Recovery->value)->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.uniqid(),
            'status_code' => $caseStatus->code,
            'current_stage' => $caseStatus->workflow_stage,
            'forwarded_at' => now()->subWeeks(3),
            'decision_at' => now()->subWeek(),
        ]);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now()->subWeeks(3),
        ]);
        $investigationStatus = InvestigationStatus::query()->where('name', 'completed')->firstOrFail();
        $investigation = Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus->code,
            'plan_summary' => 'Rencana investigasi pengujian.',
            'started_at' => now()->subWeeks(3),
            'completed_at' => now()->subWeeks(2),
        ]);
        $recommendationStatus = RecommendationStatus::query()->where('name', RecommendationStatusEnum::Accepted->value)->firstOrFail();
        $recommendation = Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $recommendationStatus->code,
            'conclusion' => 'Kesimpulan rekomendasi.',
            'recommended_actions' => 'Tindakan rekomendasi.',
            'submitted_at' => now()->subWeeks(2),
            'approved_at' => now()->subDays(10),
            'approved_by' => $admin->id,
        ]);
        $decisionStatus = DecisionStatus::query()->where('name', DecisionStatusEnum::Finalized->value)->firstOrFail();
        $decision = Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => $decisionStatus->code,
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_number' => 'PUT-'.uniqid(),
            'decision_date' => now()->subWeek()->toDateString(),
            'decision_summary' => 'Ringkasan putusan.',
            'decision_content' => 'Konten putusan.',
            'recorded_at' => now()->subWeek(),
            'finalized_at' => now()->subWeek(),
        ]);
        $recoveryStatus = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Ongoing->value)->firstOrFail();
        $recovery = Recovery::query()->create([
            'decision_id' => $decision->id,
            'recovery_type_code' => 'RCV-01',
            'status_code' => $recoveryStatus->code,
            'created_by' => $admin->id,
            'recovery_plan' => 'Rencana pemulihan pengujian.',
            'started_at' => now()->subDays(5),
        ]);

        return [$admin, $satgas, $reporter, $case->load('status'), $recovery->load(['status', 'creator'])];
    }

    /** @param array<string, mixed> $overrides */
    private function summaryPayload(array $overrides = []): array
    {
        return array_merge([
            'outcome_code' => 'resolved',
            'completion_date' => now()->toDateString(),
            'official_statement' => 'PPKS menyatakan proses penanganan telah diselesaikan sesuai prosedur.',
            'investigation_summary' => 'Investigasi telah diselesaikan dan ditinjau secara internal.',
            'recommendation_result' => 'Rekomendasi telah ditinjau oleh Admin Kampus.',
            'decision_result' => 'Putusan institusi telah difinalisasi.',
            'recovery_result' => 'Tahap pemulihan telah mencapai hasil yang dicatat.',
            'actions_completed' => 'Tindakan penanganan utama telah diselesaikan.',
            'actions_uncompleted' => null,
            'follow_up_or_referral' => null,
            'closing_explanation' => 'Kasus ditutup setelah seluruh prasyarat penanganan dipenuhi.',
        ], $overrides);
    }

    private function monitoringPayload(): array
    {
        return [
            'monitoring_date' => now()->toDateString(),
            'condition_summary' => 'Kondisi telah dimonitor oleh Satgas yang ditugaskan.',
            'follow_up_plan' => 'Tidak ada tindak lanjut tambahan saat ini.',
        ];
    }

    private function makeUser(
        string $roleCode,
        string $email,
        ?University $university,
        ?string $name = null,
    ): User {
        return User::query()->create([
            'role_id' => Role::query()->where('code', $roleCode)->firstOrFail()->id,
            'university_id' => $university?->id,
            'name' => $name ?? "{$roleCode} User",
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
