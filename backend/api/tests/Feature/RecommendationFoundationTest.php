<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus as RecommendationStatusModel;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_recommendation_foundation_tables_and_transition_metadata_exist(): void
    {
        $this->assertTrue(Schema::hasTable('recommendations'));
        $this->assertTrue(Schema::hasTable('recommendation_status_histories'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'case_id'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'investigation_id'));
        $this->assertTrue(Schema::hasColumn('recommendation_statuses', 'valid_transitions'));
        $this->assertFalse(Schema::hasTable('decisions'));
        $this->assertFalse(Schema::hasTable('evidences'));

        $drafting = RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail();

        $this->assertContains(RecommendationStatusEnum::SubmittedToLeader->value, $drafting->valid_transitions);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.recommend']);
    }

    public function test_assigned_satgas_can_create_recommendation_from_completed_investigation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/recommendations", [
            'investigation_id' => $investigation->id,
            'conclusion' => 'Kesimpulan rekomendasi yang hanya boleh dibaca Satgas assigned.',
            'recommended_actions' => 'Tindakan yang direkomendasikan Satgas.',
            'sanction_recommendation' => 'Rekomendasi sanksi internal.',
            'recovery_recommendation' => 'Rekomendasi pemulihan korban.',
            'prevention_recommendation' => 'Rekomendasi pencegahan kampus.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RecommendationStatusEnum::Drafting->value)
            ->assertJsonPath('data.investigation_id', $investigation->id)
            ->assertJsonPath('data.conclusion', 'Kesimpulan rekomendasi yang hanya boleh dibaca Satgas assigned.')
            ->assertJsonMissingPath('data.investigation.conclusion');

        $this->assertDatabaseHas('recommendations', [
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => 'RECS-01',
        ]);
        $this->assertDatabaseCount('recommendation_status_histories', 1);
    }

    public function test_recommendation_creation_requires_case_status_and_completed_investigation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);
        $draftInvestigation = $this->makeInvestigation($case, $satgas, InvestigationStatusEnum::Planning);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/recommendations", $this->payload($draftInvestigation))
            ->assertUnprocessable();

        $recommendationCase = $this->makeRecommendationCase($admin, $satgas);
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$recommendationCase->id}/recommendations", $this->payload($draftInvestigation))
            ->assertUnprocessable();

        $this->assertDatabaseCount('recommendations', 0);
    }

    public function test_admin_and_unassigned_satgas_cannot_create_or_read_sensitive_recommendation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/recommendations", $this->payload($investigation))
            ->assertForbidden();

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertForbidden();

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::Drafting->value)
            ->assertJsonMissingPath('data.conclusion')
            ->assertJsonMissingPath('data.recommended_actions')
            ->assertJsonMissingPath('data.investigation.conclusion');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.conclusion', 'Kesimpulan rekomendasi rahasia.')
            ->assertJsonMissingPath('data.investigation.conclusion');
    }

    public function test_assigned_satgas_can_update_draft_recommendation_only(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tindakan rekomendasi yang sudah diperbarui.',
        ])
            ->assertOk()
            ->assertJsonPath('data.recommended_actions', 'Tindakan rekomendasi yang sudah diperbarui.');

        $recommendation->forceFill([
            'status_code' => RecommendationStatusModel::query()->where('name', RecommendationStatusEnum::SubmittedToLeader->value)->firstOrFail()->code,
            'submitted_at' => now(),
        ])->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tidak boleh berubah setelah submit.',
        ])
            ->assertUnprocessable();
    }

    public function test_status_transitions_use_master_data_history_and_do_not_update_case_status(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::InternalReview->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::InternalReview->value);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::SubmittedToLeader->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::SubmittedToLeader->value);

        $recommendation->refresh();
        $this->assertNotNull($recommendation->submitted_at);
        $this->assertSame(CaseStatusEnum::Recommendation->value, $case->refresh()->status->name);
        $this->assertDatabaseCount('recommendation_status_histories', 3);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::Revised->value,
        ])
            ->assertUnprocessable();
    }

    public function test_decision_statuses_are_not_reachable_even_if_master_data_is_changed(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail()
            ->forceFill(['valid_transitions' => [RecommendationStatusEnum::Accepted->value]])
            ->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::Accepted->value,
        ])
            ->assertUnprocessable();
    }

    private function payload(Investigation $investigation): array
    {
        return [
            'investigation_id' => $investigation->id,
            'conclusion' => 'Kesimpulan rekomendasi rahasia.',
            'recommended_actions' => 'Tindakan rekomendasi untuk penanganan kasus.',
            'sanction_recommendation' => 'Rekomendasi sanksi.',
            'recovery_recommendation' => 'Rekomendasi pemulihan.',
            'prevention_recommendation' => 'Rekomendasi pencegahan.',
        ];
    }

    private function makeRecommendation(CaseRecord $case, Investigation $investigation, User $satgas): Recommendation
    {
        $status = RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail();

        $recommendation = Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $status->code,
            'conclusion' => 'Kesimpulan rekomendasi rahasia.',
            'recommended_actions' => 'Tindakan rekomendasi untuk penanganan kasus.',
            'sanction_recommendation' => 'Rekomendasi sanksi.',
            'recovery_recommendation' => 'Rekomendasi pemulihan.',
            'prevention_recommendation' => 'Rekomendasi pencegahan.',
        ]);

        $recommendation->statusHistories()->create([
            'from_status_code' => null,
            'to_status_code' => $status->code,
            'changed_by' => $satgas->id,
            'changed_at' => now(),
        ]);

        return $recommendation;
    }

    private function makeCompletedInvestigation(CaseRecord $case, User $satgas): Investigation
    {
        return $this->makeInvestigation($case, $satgas, InvestigationStatusEnum::Completed);
    }

    private function makeInvestigation(CaseRecord $case, User $satgas, InvestigationStatusEnum $statusName): Investigation
    {
        $status = InvestigationStatus::query()
            ->where('name', $statusName->value)
            ->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Plan investigasi rahasia.',
            'findings' => 'Temuan investigasi rahasia.',
            'conclusion' => 'Kesimpulan investigasi rahasia.',
            'started_at' => now(),
            'completed_at' => $statusName === InvestigationStatusEnum::Completed ? now() : null,
        ]);
    }

    private function makeRecommendationCase(User $admin, User $satgas): CaseRecord
    {
        return $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Recommendation);
    }

    private function makeInvestigationCase(User $admin, User $satgas): CaseRecord
    {
        return $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Investigation);
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

    private function makeReport(): Report
    {
        return Report::query()->create([
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji recommendation foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian recommendation.',
            'witness_info' => 'Informasi saksi untuk pengujian recommendation.',
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
