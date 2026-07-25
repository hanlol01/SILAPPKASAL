<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Permission;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DecisionService;
use App\Support\ApiErrorCode;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DecisionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_decision_foundation_tables_and_master_data_exist(): void
    {
        $this->assertTrue(Schema::hasTable('decision_statuses'));
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertTrue(Schema::hasTable('decision_status_histories'));
        $this->assertTrue(Schema::hasTable('decision_number_sequences'));
        $this->assertTrue(Schema::hasColumn('decision_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasColumn('decisions', 'recommendation_id'));
        $this->assertFalse(Schema::hasColumn('decisions', 'case_id'));
        $this->assertTrue(Schema::hasTable('recovery_monitorings'));
        $this->assertDatabaseCount('recovery_monitorings', 0);
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertDatabaseCount('evidences', 0);

        $draft = DecisionStatus::query()->where('name', DecisionStatusEnum::Draft->value)->firstOrFail();

        $this->assertContains(DecisionStatusEnum::Recorded->value, $draft->valid_transitions);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.record_decision']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-18', 'template_key' => 'decision.created.internal']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-19', 'template_key' => 'decision.status_changed.internal']);
    }

    public function test_admin_can_create_decision_for_accepted_recommendation_and_case_in_decision_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/decisions", $this->decisionPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', DecisionStatusEnum::Draft->value)
            ->assertJsonPath('data.outcome_code', DecisionOutcome::Accepted->value)
            ->assertJsonPath('data.decision_number', null)
            ->assertJsonPath('data.decision_summary', 'Ringkasan keputusan institusi.')
            ->assertJsonMissingPath('data.recommendation.conclusion')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->assertDatabaseHas('decisions', [
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => 'DECS-01',
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_number' => null,
        ]);
        $this->assertDatabaseCount('decision_status_histories', 1);
        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
        $this->assertNull($case->closed_at);
        $this->assertSame(RecommendationStatusEnum::Accepted->value, $recommendation->refresh()->status->name);
    }

    public function test_decision_creation_requires_accepted_recommendation_and_case_decision_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $decisionCase = $this->makeDecisionCase($admin, $satgas);
        $draftRecommendation = $this->makeRecommendation($decisionCase, $satgas, RecommendationStatusEnum::Drafting);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$draftRecommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();

        $submittedRecommendation = $this->makeRecommendation(
            $this->makeDecisionCase($admin, $satgas),
            $satgas,
            RecommendationStatusEnum::SubmittedForReview,
        );
        $this->postJson("/api/v1/recommendations/{$submittedRecommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();

        $recommendationCase = $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Recommendation);
        $acceptedRecommendation = $this->makeRecommendation($recommendationCase, $satgas, RecommendationStatusEnum::Accepted);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$acceptedRecommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('decisions', 0);
    }

    public function test_satgas_can_read_assigned_decision_detail_but_cannot_mutate(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);
        $decision = $this->makeDecision($recommendation, $admin);

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertOk()
            ->assertJsonPath('data.decision_summary', 'Ringkasan keputusan institusi.')
            ->assertJsonPath('data.decision_content', 'Isi keputusan lengkap yang terenkripsi saat tersimpan.')
            ->assertJsonMissingPath('data.recommendation.conclusion');

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/decisions/{$decision->id}", [
            'decision_summary' => 'Satgas tidak boleh mengubah.',
        ])
            ->assertForbidden();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])
            ->assertForbidden();

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertForbidden();
    }

    public function test_super_admin_is_read_only_and_only_active_admin_can_manage_decisions(): void
    {
        config()->set('oversight.cross_campus_sensitive_read', true);
        $admin = $this->makeUser('admin', 'admin-manager@university.ac.id');
        $inactiveAdmin = $this->makeUser('admin', 'inactive-admin@university.ac.id');
        $inactiveAdmin->forceFill(['is_active' => false])->save();
        $otherAdmin = $this->makeUser('admin', 'other-campus-admin@university.ac.id', 'DEMO-ST');
        $superAdmin = $this->makeUser('super_admin', 'super-readonly@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-manager@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);
        $decision = $this->makeDecision($recommendation, $admin);

        $superAdmin->role->permissions()->syncWithoutDetaching([
            Permission::query()->where('code', 'cases.record_decision')->firstOrFail()->id,
        ]);
        $superAdmin->unsetRelation('role');

        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertOk()
            ->assertJsonPath('data.decision_number', null)
            ->assertJsonPath('data.sensitive_details_available', false)
            ->assertJsonMissingPath('data.decision_summary')
            ->assertJsonMissingPath('data.decision_content');
        $this->patchJson("/api/v1/decisions/{$decision->id}", [
            'decision_summary' => 'Super Admin tidak boleh mengubah.',
        ])->assertForbidden();
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])->assertForbidden();

        $otherCase = $this->makeDecisionCase($admin, $satgas);
        $otherRecommendation = $this->makeRecommendation($otherCase, $satgas, RecommendationStatusEnum::Accepted);
        $this->postJson("/api/v1/recommendations/{$otherRecommendation->id}/decisions", $this->decisionPayload())
            ->assertForbidden();

        $this->actingAsApi($inactiveAdmin);
        $this->postJson("/api/v1/recommendations/{$otherRecommendation->id}/decisions", $this->decisionPayload())
            ->assertForbidden();

        $this->actingAsApi($otherAdmin);
        $this->postJson("/api/v1/recommendations/{$otherRecommendation->id}/decisions", $this->decisionPayload())
            ->assertForbidden();
        $this->patchJson("/api/v1/decisions/{$decision->id}", [
            'decision_summary' => 'Admin kampus lain tidak boleh mengubah.',
        ])->assertForbidden();
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])->assertForbidden();
    }

    public function test_decision_status_options_follow_view_policy_and_actor_authority(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);
        $decision = $this->makeDecision($recommendation, $admin);

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/decisions/{$decision->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', DecisionStatusEnum::Draft->value)
            ->assertJsonCount(1, 'data.valid_transitions')
            ->assertJsonPath('data.valid_transitions.0.name', DecisionStatusEnum::Recorded->value)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/decisions/{$decision->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', DecisionStatusEnum::Draft->value)
            ->assertJsonCount(0, 'data.valid_transitions');

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/decisions/{$decision->id}/status-options")
            ->assertForbidden();
    }

    public function test_admin_can_update_draft_decision_but_cannot_write_decision_number(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $caseA = $this->makeDecisionCase($admin, $satgas);
        $recommendationA = $this->makeRecommendation($caseA, $satgas, RecommendationStatusEnum::Accepted);
        $decisionA = $this->makeDecision($recommendationA, $admin, ['decision_number' => null]);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decisionA->id}", [
            'outcome_code' => DecisionOutcome::Deferred->value,
            'decision_summary' => 'Ringkasan keputusan diperbarui.',
        ])
            ->assertOk()
            ->assertJsonPath('data.decision_number', null)
            ->assertJsonPath('data.outcome_code', DecisionOutcome::Deferred->value);

        $this->patchJson("/api/v1/decisions/{$decisionA->id}", [
            'decision_number' => 'SK-SAMA',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_number');
    }

    public function test_finalization_advances_case_to_decided_and_locks_decision(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);
        $decision = $this->makeDecision($recommendation, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DecisionStatusEnum::Recorded->value);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Finalized->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DecisionStatusEnum::Finalized->value)
            ->assertJsonPath('data.decision_number', 'SK/PPKS/'.now()->format('Y').'/001')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $decision->refresh();
        $this->assertNotNull($decision->finalized_at);
        $this->assertSame(CaseStatusEnum::Decided->value, $case->refresh()->status->name);
        $this->assertNull($case->closed_at);
        $this->assertDatabaseCount('decision_status_histories', 3);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CaseStatusChanged->value,
            'subject_id' => $case->id,
        ]);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])
            ->assertUnprocessable();

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decision->id}", [
            'decision_summary' => 'Tidak boleh berubah setelah finalized.',
        ])
            ->assertUnprocessable();
    }

    public function test_failed_finalization_rolls_back_decision_case_history_audit_and_notification(): void
    {
        $admin = $this->makeUser('admin', 'admin-rollback@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-rollback@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);
        $decision = $this->makeDecision($recommendation, $admin);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])->assertOk();

        $recommendationStatus = CaseStatus::query()
            ->where('name', CaseStatusEnum::Recommendation->value)
            ->firstOrFail();
        $case->forceFill(['status_code' => $recommendationStatus->code])->save();
        $historyCount = $decision->statusHistories()->count();
        $auditCount = AuditLog::query()->count();
        $notificationCount = $satgas->notifications()->count();

        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Finalized->value,
        ])->assertUnprocessable();

        $this->assertSame(DecisionStatusEnum::Recorded->value, $decision->refresh()->status->name);
        $this->assertSame(CaseStatusEnum::Recommendation->value, $case->refresh()->status->name);
        $this->assertSame($historyCount, $decision->statusHistories()->count());
        $this->assertSame($auditCount, AuditLog::query()->count());
        $this->assertSame($notificationCount, $satgas->notifications()->count());
    }

    public function test_decision_workflow_dispatches_audit_logs_and_notifications(): void
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

        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);

        $this->actingAsApi($admin);
        $decisionId = $this->postJson("/api/v1/recommendations/{$recommendation->id}/decisions", $this->decisionPayload())
            ->assertCreated()
            ->json('data.id');

        $decision = Decision::query()->findOrFail($decisionId);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DecisionCreated->value,
            'category' => AuditCategory::Decision->value,
            'subject_type' => $decision->getMorphClass(),
            'subject_id' => $decision->id,
        ]);

        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-18')->count());
        $this->assertSame(1, $this->notificationsByType($otherSatgas, 'NOTIF-18')->count());
        $this->assertSame(0, $this->notificationsByType($admin, 'NOTIF-18')->count());

        $this->patchJson("/api/v1/decisions/{$decision->id}", [
            'decision_summary' => 'Ringkasan keputusan yang diperbarui dan tetap sensitif.',
            'decision_content' => 'Isi keputusan yang diperbarui dan tidak boleh bocor ke audit.',
        ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DecisionUpdated->value,
            'category' => AuditCategory::Decision->value,
            'subject_type' => $decision->getMorphClass(),
            'subject_id' => $decision->id,
        ]);

        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Recorded->value,
        ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DecisionStatusChanged->value,
            'category' => AuditCategory::Decision->value,
            'subject_type' => $decision->getMorphClass(),
            'subject_id' => $decision->id,
        ]);

        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-19')->count());
        $this->assertSame(1, $this->notificationsByType($otherSatgas, 'NOTIF-19')->count());
        $this->assertSame(0, $this->notificationsByType($admin, 'NOTIF-19')->count());

        $this->patchJson("/api/v1/decisions/{$decision->id}/status", [
            'status' => DecisionStatusEnum::Finalized->value,
        ])
            ->assertOk();

        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-15')->count());
        $this->assertSame(1, $this->notificationsByType($otherSatgas, 'NOTIF-15')->count());
        $this->assertSame(1, $this->notificationsByType($satgas, 'NOTIF-19')->count());

        $auditJson = AuditLog::query()
            ->whereIn('action', [
                AuditAction::DecisionCreated->value,
                AuditAction::DecisionUpdated->value,
                AuditAction::DecisionStatusChanged->value,
            ])
            ->get()
            ->toJson();

        $this->assertStringNotContainsString('Ringkasan keputusan institusi', $auditJson);
        $this->assertStringNotContainsString('Isi keputusan lengkap', $auditJson);
        $this->assertStringNotContainsString('Ringkasan keputusan yang diperbarui', $auditJson);
        $this->assertStringNotContainsString('Isi keputusan yang diperbarui', $auditJson);
    }

    public function test_deferred_decision_does_not_update_recommendation_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/decisions", $this->decisionPayload([
            'outcome_code' => DecisionOutcome::Deferred->value,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.outcome_code', DecisionOutcome::Deferred->value);

        $this->assertSame(RecommendationStatusEnum::Accepted->value, $recommendation->refresh()->status->name);
    }

    public function test_reporter_cannot_access_decisions_and_duplicate_decision_is_rejected(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted);
        $decision = $this->makeDecision($recommendation, $admin);

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertForbidden();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();
    }

    public function test_formal_code_sequence_is_server_generated_yearly_and_uses_application_timezone(): void
    {
        config()->set('app.timezone', 'Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2025-12-31 17:00:00', 'UTC'));

        try {
            $admin = $this->makeUser('admin', 'admin-sequence@university.ac.id');
            $satgas = $this->makeUser('satgas_ppks', 'satgas-sequence@university.ac.id');
            $first = $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
            );
            $second = $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
            );

            $this->markDecisionRecorded($first, $admin);
            $this->markDecisionRecorded($second, $admin);
            $this->actingAsApi($admin);

            $this->patchJson("/api/v1/decisions/{$first->id}/status", ['status' => 'finalized'])
                ->assertOk()
                ->assertJsonPath('data.decision_number', 'SK/PPKS/2026/001');
            $this->patchJson("/api/v1/decisions/{$second->id}/status", ['status' => 'finalized'])
                ->assertOk()
                ->assertJsonPath('data.decision_number', 'SK/PPKS/2026/002');

            $first->refresh();
            $issuanceTimestamp = Carbon::getTestNow();
            $finalizationHistory = $first->statusHistories()
                ->latest('id')
                ->firstOrFail();
            $firstCase = $first->recommendation->case()->firstOrFail();

            $this->assertTrue($first->finalized_at?->equalTo($issuanceTimestamp) ?? false);
            $this->assertTrue($finalizationHistory->changed_at?->equalTo($issuanceTimestamp) ?? false);
            $this->assertTrue($firstCase->decision_at?->equalTo($issuanceTimestamp) ?? false);
            $this->assertSame(
                '2026',
                $first->finalized_at?->copy()->setTimezone('Asia/Jakarta')->format('Y'),
            );
            $this->assertSame(2, DB::table('decision_number_sequences')->where('year', 2026)->value('last_value'));

            Carbon::setTestNow(Carbon::parse('2026-12-31 17:00:00', 'UTC'));
            $third = $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
            );
            $this->markDecisionRecorded($third, $admin);

            $this->patchJson("/api/v1/decisions/{$third->id}/status", ['status' => 'finalized'])
                ->assertOk()
                ->assertJsonPath('data.decision_number', 'SK/PPKS/2027/001');
            $this->assertSame(1, DB::table('decision_number_sequences')->where('year', 2027)->value('last_value'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_finalization_retry_is_idempotent_for_issued_and_legacy_null_decisions(): void
    {
        $admin = $this->makeUser('admin', 'admin-retry@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-retry@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $decision = $this->makeDecision(
            $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted),
            $admin,
        );
        $this->markDecisionRecorded($decision, $admin);
        $this->actingAsApi($admin);

        $firstResponse = $this->patchJson("/api/v1/decisions/{$decision->id}/status", ['status' => 'finalized'])
            ->assertOk();
        $issuedNumber = $firstResponse->json('data.decision_number');
        $historyCount = $decision->statusHistories()->count();
        $auditCount = AuditLog::query()
            ->where('action', AuditAction::DecisionStatusChanged->value)
            ->where('subject_id', $decision->id)
            ->count();
        $notificationCount = $this->notificationsByType($satgas, 'NOTIF-15')->count();
        $sequenceValue = DB::table('decision_number_sequences')->value('last_value');

        $this->patchJson("/api/v1/decisions/{$decision->id}/status", ['status' => 'finalized'])
            ->assertOk()
            ->assertJsonPath('data.decision_number', $issuedNumber);

        $this->assertSame($historyCount, $decision->statusHistories()->count());
        $this->assertSame($auditCount, AuditLog::query()
            ->where('action', AuditAction::DecisionStatusChanged->value)
            ->where('subject_id', $decision->id)
            ->count());
        $this->assertSame($notificationCount, $this->notificationsByType($satgas, 'NOTIF-15')->count());
        $this->assertSame($sequenceValue, DB::table('decision_number_sequences')->value('last_value'));

        $legacyCase = $this->makeDecisionCase($admin, $satgas);
        $legacy = $this->makeDecision(
            $this->makeRecommendation($legacyCase, $satgas, RecommendationStatusEnum::Accepted),
            $admin,
        );
        $finalized = DecisionStatus::query()->where('name', DecisionStatusEnum::Finalized->value)->firstOrFail();
        $legacy->forceFill([
            'status_code' => $finalized->code,
            'decision_number' => null,
            'finalized_at' => now(),
        ])->save();

        $this->patchJson("/api/v1/decisions/{$legacy->id}/status", ['status' => 'finalized'])
            ->assertOk()
            ->assertJsonPath('data.decision_number', null);
        $this->assertSame($sequenceValue, DB::table('decision_number_sequences')->value('last_value'));
    }

    public function test_legacy_number_is_preserved_and_canonical_collision_is_skipped_without_rewrite(): void
    {
        config()->set('app.timezone', 'Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-06-01 03:00:00', 'UTC'));

        try {
            $admin = $this->makeUser('admin', 'admin-legacy@university.ac.id');
            $satgas = $this->makeUser('satgas_ppks', 'satgas-legacy@university.ac.id');
            $legacy = $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
                ['decision_number' => 'LEGACY/KEPUTUSAN/77'],
            );
            $this->markDecisionRecorded($legacy, $admin);
            $this->actingAsApi($admin);
            $this->patchJson("/api/v1/decisions/{$legacy->id}/status", ['status' => 'finalized'])
                ->assertOk()
                ->assertJsonPath('data.decision_number', 'LEGACY/KEPUTUSAN/77');
            $this->assertDatabaseCount('decision_number_sequences', 0);

            $occupied = $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
                ['decision_number' => 'SK/PPKS/2026/001'],
            );
            $newDecision = $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
            );
            $this->markDecisionRecorded($newDecision, $admin);

            $this->patchJson("/api/v1/decisions/{$newDecision->id}/status", ['status' => 'finalized'])
                ->assertOk()
                ->assertJsonPath('data.decision_number', 'SK/PPKS/2026/002');
            $this->assertSame('SK/PPKS/2026/001', $occupied->refresh()->decision_number);
            $this->assertSame(2, DB::table('decision_number_sequences')->where('year', 2026)->value('last_value'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sequence_exhaustion_and_audit_failure_roll_back_all_finalization_state(): void
    {
        config()->set('app.timezone', 'Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-06-01 03:00:00', 'UTC'));

        try {
            $admin = $this->makeUser('admin', 'admin-rollback-code@university.ac.id');
            $satgas = $this->makeUser('satgas_ppks', 'satgas-rollback-code@university.ac.id');
            $case = $this->makeDecisionCase($admin, $satgas);
            $decision = $this->makeDecision(
                $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted),
                $admin,
            );
            $this->markDecisionRecorded($decision, $admin);
            DB::table('decision_number_sequences')->insert([
                'year' => 2026,
                'last_value' => 999,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $historyCount = $decision->statusHistories()->count();
            $auditCount = AuditLog::query()->count();
            $this->actingAsApi($admin);

            $this->patchJson("/api/v1/decisions/{$decision->id}/status", ['status' => 'finalized'])
                ->assertStatus(409)
                ->assertJsonPath('error_code', ApiErrorCode::DecisionNumberSequenceExhausted);
            $this->assertSame(DecisionStatusEnum::Recorded->value, $decision->refresh()->status->name);
            $this->assertNull($decision->decision_number);
            $this->assertNull($decision->finalized_at);
            $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
            $this->assertSame($historyCount, $decision->statusHistories()->count());
            $this->assertSame($auditCount, AuditLog::query()->count());
            $this->assertSame(999, DB::table('decision_number_sequences')->where('year', 2026)->value('last_value'));
            $this->assertSame(0, $this->notificationsByType($satgas, 'NOTIF-15')->count());

            DB::table('decision_number_sequences')->where('year', 2026)->update(['last_value' => 0]);
            $this->mock(AuditLogService::class, function (MockInterface $mock): void {
                $mock->shouldReceive('record')->andThrow(new RuntimeException('simulated audit failure'));
            });

            try {
                app(DecisionService::class)->updateStatus($decision->fresh(), $admin->fresh(), 'finalized');
                $this->fail('Expected the simulated audit failure.');
            } catch (RuntimeException $exception) {
                $this->assertSame('simulated audit failure', $exception->getMessage());
            }

            $this->assertSame(DecisionStatusEnum::Recorded->value, $decision->refresh()->status->name);
            $this->assertNull($decision->decision_number);
            $this->assertNull($decision->finalized_at);
            $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
            $this->assertSame(0, DB::table('decision_number_sequences')->where('year', 2026)->value('last_value'));
            $this->assertSame(0, $this->notificationsByType($satgas, 'NOTIF-15')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_decision_code_spoofing_is_rejected_on_create_update_and_status_requests(): void
    {
        $admin = $this->makeUser('admin', 'admin-spoof@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-spoof@university.ac.id');
        $recommendation = $this->makeRecommendation(
            $this->makeDecisionCase($admin, $satgas),
            $satgas,
            RecommendationStatusEnum::Accepted,
        );
        $aliases = [
            'decision_number' => 'SK/PPKS/2099/999',
            'decision_code' => 'spoofed',
            'formal_decision_code' => 'spoofed',
            'sequence' => 9,
            'year' => 2099,
            'nomor_keputusan' => 'spoofed',
            'kode_keputusan' => 'spoofed',
            'decision_no' => 'spoofed',
        ];
        $this->actingAsApi($admin);

        $this->postJson(
            "/api/v1/recommendations/{$recommendation->id}/decisions",
            [...$this->decisionPayload(), ...$aliases],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($aliases));
        $this->assertDatabaseCount('decisions', 0);

        $decision = $this->makeDecision($recommendation, $admin);
        $this->patchJson("/api/v1/decisions/{$decision->id}", $aliases)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($aliases));
        $this->patchJson(
            "/api/v1/decisions/{$decision->id}/status",
            ['status' => 'recorded', ...$aliases],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($aliases));
        $this->assertNull($decision->refresh()->decision_number);
    }

    public function test_finalization_fails_closed_for_invalid_recommendation_withdrawal_and_case_states(): void
    {
        $admin = $this->makeUser('admin', 'admin-guards@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-guards@university.ac.id');
        $this->actingAsApi($admin);

        $draft = $this->makeDecision(
            $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
            $admin,
        );
        $this->patchJson("/api/v1/decisions/{$draft->id}/status", ['status' => 'finalized'])
            ->assertUnprocessable();

        $invalidRecommendation = $this->makeRecommendation(
            $this->makeDecisionCase($admin, $satgas),
            $satgas,
            RecommendationStatusEnum::Accepted,
        );
        $invalidRecommendationDecision = $this->makeDecision($invalidRecommendation, $admin);
        $this->markDecisionRecorded($invalidRecommendationDecision, $admin);
        $draftRecommendationStatus = RecommendationStatus::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail();
        $invalidRecommendation->forceFill(['status_code' => $draftRecommendationStatus->code])->save();
        $this->patchJson("/api/v1/decisions/{$invalidRecommendationDecision->id}/status", ['status' => 'finalized'])
            ->assertUnprocessable();

        $withdrawalCase = $this->makeDecisionCase($admin, $satgas);
        $withdrawalDecision = $this->makeDecision(
            $this->makeRecommendation($withdrawalCase, $satgas, RecommendationStatusEnum::Accepted),
            $admin,
        );
        $this->markDecisionRecorded($withdrawalDecision, $admin);
        $report = $withdrawalCase->report()->firstOrFail();
        ReportWithdrawal::query()->create([
            'report_id' => $report->id,
            'case_id' => $withdrawalCase->id,
            'requester_id' => $report->reporter_id,
            'registration_number_snapshot' => $report->registration_number,
            'requester_display_name_snapshot' => 'Pelapor',
            'request_type' => ReportWithdrawalRequestType::FormalWithdrawal,
            'status' => ReportWithdrawalStatus::PendingReview,
            'reason' => 'Permohonan pencabutan formal yang sedang ditinjau.',
            'previous_report_status' => $report->status,
            'previous_case_status' => $withdrawalCase->status?->name,
            'lock_version' => 0,
        ]);
        $this->getJson("/api/v1/decisions/{$withdrawalDecision->id}/status-options")
            ->assertOk()
            ->assertJsonCount(0, 'data.valid_transitions');
        $this->patchJson("/api/v1/decisions/{$withdrawalDecision->id}/status", ['status' => 'finalized'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', ApiErrorCode::WithdrawalPendingReview);

        $withdrawnReportCase = $this->makeDecisionCase($admin, $satgas);
        $withdrawnReportDecision = $this->makeDecision(
            $this->makeRecommendation($withdrawnReportCase, $satgas, RecommendationStatusEnum::Accepted),
            $admin,
        );
        $this->markDecisionRecorded($withdrawnReportDecision, $admin);
        $withdrawnReportCase->report()->update(['status' => ReportStatus::Withdrawn->value]);
        $this->patchJson("/api/v1/decisions/{$withdrawnReportDecision->id}/status", ['status' => 'finalized'])
            ->assertUnprocessable();

        foreach ([CaseStatusEnum::Withdrawn, CaseStatusEnum::Decided] as $caseStatus) {
            $case = $this->makeDecisionCase($admin, $satgas);
            $decision = $this->makeDecision(
                $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::Accepted),
                $admin,
            );
            $this->markDecisionRecorded($decision, $admin);
            $status = CaseStatus::query()->where('name', $caseStatus->value)->firstOrFail();
            $case->forceFill(['status_code' => $status->code])->save();
            $response = $this->patchJson("/api/v1/decisions/{$decision->id}/status", ['status' => 'finalized']);
            $caseStatus === CaseStatusEnum::Withdrawn
                ? $response->assertStatus(409)
                : $response->assertUnprocessable();
            $this->assertNull($decision->refresh()->decision_number);
        }

        $this->assertDatabaseCount('decision_number_sequences', 0);
    }

    public function test_formal_code_visibility_is_role_scoped_and_get_requests_do_not_mutate(): void
    {
        $admin = $this->makeUser('admin', 'admin-visibility@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-visibility@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-visibility@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter-visibility@university.ac.id');
        $crossCampusAdmin = $this->makeUser('admin', 'cross-visibility@university.ac.id', 'DEMO-ST');
        $decision = $this->makeDecision(
            $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
            $admin,
        );
        $this->markDecisionRecorded($decision, $admin);
        $this->actingAsApi($admin);
        $number = $this->patchJson("/api/v1/decisions/{$decision->id}/status", ['status' => 'finalized'])
            ->assertOk()
            ->json('data.decision_number');
        $sequenceValue = DB::table('decision_number_sequences')->value('last_value');

        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertOk()
            ->assertJsonPath('data.decision_number', $number)
            ->assertJsonPath('data.decision_summary', 'Ringkasan keputusan institusi.')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertOk()
            ->assertJsonPath('data.decision_number', $number);
        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertOk()
            ->assertJsonPath('data.decision_number', $number)
            ->assertJsonPath('data.sensitive_details_available', false)
            ->assertJsonMissingPath('data.decision_summary')
            ->assertJsonMissingPath('data.decision_content');
        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/decisions/{$decision->id}")->assertForbidden();
        $this->actingAsApi($crossCampusAdmin);
        $this->getJson("/api/v1/decisions/{$decision->id}")->assertForbidden();

        $this->assertSame($number, $decision->refresh()->decision_number);
        $this->assertSame($sequenceValue, DB::table('decision_number_sequences')->value('last_value'));
    }

    public function test_database_unique_constraint_rejects_duplicate_non_null_numbers_and_allows_nulls(): void
    {
        $admin = $this->makeUser('admin', 'admin-unique@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-unique@university.ac.id');
        $first = $this->makeDecision(
            $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
            $admin,
            ['decision_number' => 'LEGACY-UNIQUE-1'],
        );
        $this->makeDecision(
            $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
            $admin,
            ['decision_number' => null],
        );
        $this->makeDecision(
            $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
            $admin,
            ['decision_number' => null],
        );

        try {
            $this->makeDecision(
                $this->makeRecommendation($this->makeDecisionCase($admin, $satgas), $satgas, RecommendationStatusEnum::Accepted),
                $admin,
                ['decision_number' => 'LEGACY-UNIQUE-1'],
            );
            $this->fail('Expected the global decision number unique constraint to reject the duplicate.');
        } catch (QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505']);
        }

        $this->assertSame('LEGACY-UNIQUE-1', $first->refresh()->decision_number);
        $this->assertSame(2, Decision::query()->whereNull('decision_number')->count());
    }

    private function decisionPayload(array $overrides = []): array
    {
        return array_merge([
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'Ringkasan keputusan institusi.',
            'decision_content' => 'Isi keputusan lengkap yang terenkripsi saat tersimpan.',
        ], $overrides);
    }

    private function makeDecision(Recommendation $recommendation, User $admin, array $overrides = []): Decision
    {
        $status = DecisionStatus::query()->where('name', DecisionStatusEnum::Draft->value)->firstOrFail();
        $payload = $this->decisionPayload($overrides);

        $decision = Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => $status->code,
            'outcome_code' => $payload['outcome_code'],
            'decision_number' => $payload['decision_number'] ?? null,
            'decision_date' => $payload['decision_date'],
            'decision_summary' => $payload['decision_summary'],
            'decision_content' => $payload['decision_content'],
            'recorded_at' => now(),
        ]);

        $decision->statusHistories()->create([
            'from_status_code' => null,
            'to_status_code' => $status->code,
            'changed_by' => $admin->id,
            'changed_at' => now(),
        ]);

        return $decision;
    }

    private function markDecisionRecorded(Decision $decision, User $admin): void
    {
        $recorded = DecisionStatus::query()->where('name', DecisionStatusEnum::Recorded->value)->firstOrFail();
        $fromStatusCode = $decision->status_code;
        $decision->forceFill(['status_code' => $recorded->code])->save();
        $decision->statusHistories()->create([
            'from_status_code' => $fromStatusCode,
            'to_status_code' => $recorded->code,
            'changed_by' => $admin->id,
            'changed_at' => now(),
        ]);
        $decision->unsetRelation('status');
    }

    private function makeRecommendation(CaseRecord $case, User $satgas, RecommendationStatusEnum $statusName): Recommendation
    {
        $status = RecommendationStatus::query()->where('name', $statusName->value)->firstOrFail();
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
            'submitted_at' => in_array($statusName, [
                RecommendationStatusEnum::SubmittedForReview,
                RecommendationStatusEnum::SubmittedToLeader,
            ], true) ? now() : null,
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
        return $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Decision);
    }

    private function makeCaseWithStatus(User $admin, User $satgas, CaseStatusEnum $statusName): CaseRecord
    {
        $report = $this->makeReport();
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'decision_at' => $statusName === CaseStatusEnum::Decision ? now() : null,
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
        $reporter = $this->makeUser('reporter', 'decision-reporter-'.(Report::query()->count() + 1).'@university.ac.id');

        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji decision foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian decision.',
            'witness_info' => 'Informasi saksi untuk pengujian decision.',
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
