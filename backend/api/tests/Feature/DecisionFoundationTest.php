<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DecisionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_decision_foundation_tables_and_master_data_exist(): void
    {
        $this->assertTrue(Schema::hasTable('decision_statuses'));
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertTrue(Schema::hasTable('decision_status_histories'));
        $this->assertTrue(Schema::hasColumn('decision_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasColumn('decisions', 'recommendation_id'));
        $this->assertFalse(Schema::hasColumn('decisions', 'case_id'));
        $this->assertTrue(Schema::hasTable('recovery_monitorings'));
        $this->assertDatabaseCount('recovery_monitorings', 0);
        $this->assertFalse(Schema::hasTable('evidences'));

        $draft = DecisionStatus::query()->where('name', DecisionStatusEnum::Draft->value)->firstOrFail();

        $this->assertContains(DecisionStatusEnum::Recorded->value, $draft->valid_transitions);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.record_decision']);
    }

    public function test_admin_can_create_decision_for_submitted_recommendation_and_case_in_decision_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::SubmittedToLeader);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/decisions", $this->decisionPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', DecisionStatusEnum::Draft->value)
            ->assertJsonPath('data.outcome_code', DecisionOutcome::Accepted->value)
            ->assertJsonPath('data.decision_number', 'SK-2026-001')
            ->assertJsonPath('data.decision_summary', 'Ringkasan keputusan institusi.')
            ->assertJsonMissingPath('data.recommendation.conclusion');

        $this->assertDatabaseHas('decisions', [
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => 'DECS-01',
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_number' => 'SK-2026-001',
        ]);
        $this->assertDatabaseCount('decision_status_histories', 1);
        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
        $this->assertNull($case->closed_at);
    }

    public function test_decision_creation_requires_submitted_recommendation_and_case_decision_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $decisionCase = $this->makeDecisionCase($admin, $satgas);
        $draftRecommendation = $this->makeRecommendation($decisionCase, $satgas, RecommendationStatusEnum::Drafting);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$draftRecommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();

        $recommendationCase = $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Recommendation);
        $submittedRecommendation = $this->makeRecommendation($recommendationCase, $satgas, RecommendationStatusEnum::SubmittedToLeader);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$submittedRecommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('decisions', 0);
    }

    public function test_satgas_can_read_assigned_decision_detail_but_cannot_mutate(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::SubmittedToLeader);
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

    public function test_admin_can_update_draft_decision_and_decision_number_is_nullable_and_non_unique(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $caseA = $this->makeDecisionCase($admin, $satgas);
        $caseB = $this->makeDecisionCase($admin, $satgas);
        $recommendationA = $this->makeRecommendation($caseA, $satgas, RecommendationStatusEnum::SubmittedToLeader);
        $recommendationB = $this->makeRecommendation($caseB, $satgas, RecommendationStatusEnum::SubmittedToLeader);
        $decisionA = $this->makeDecision($recommendationA, $admin, ['decision_number' => null]);
        $decisionB = $this->makeDecision($recommendationB, $admin, ['decision_number' => null]);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decisionA->id}", [
            'decision_number' => 'SK-SAMA',
            'outcome_code' => DecisionOutcome::Deferred->value,
            'decision_summary' => 'Ringkasan keputusan diperbarui.',
        ])
            ->assertOk()
            ->assertJsonPath('data.decision_number', 'SK-SAMA')
            ->assertJsonPath('data.outcome_code', DecisionOutcome::Deferred->value);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/decisions/{$decisionB->id}", [
            'decision_number' => 'SK-SAMA',
        ])
            ->assertOk()
            ->assertJsonPath('data.decision_number', 'SK-SAMA');
    }

    public function test_status_transitions_use_master_data_history_and_never_update_case_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::SubmittedToLeader);
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
            ->assertJsonPath('data.status', DecisionStatusEnum::Finalized->value);

        $decision->refresh();
        $this->assertNotNull($decision->finalized_at);
        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
        $this->assertNull($case->closed_at);
        $this->assertDatabaseCount('decision_status_histories', 3);

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

    public function test_reporter_cannot_access_decisions_and_duplicate_decision_is_rejected(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->makeDecisionCase($admin, $satgas);
        $recommendation = $this->makeRecommendation($case, $satgas, RecommendationStatusEnum::SubmittedToLeader);
        $decision = $this->makeDecision($recommendation, $admin);

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertForbidden();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/decisions", $this->decisionPayload())
            ->assertUnprocessable();
    }

    private function decisionPayload(array $overrides = []): array
    {
        return array_merge([
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_number' => 'SK-2026-001',
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
            'submitted_at' => $statusName === RecommendationStatusEnum::SubmittedToLeader ? now() : null,
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
        return Report::query()->create([
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
