<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\EvidenceStatus;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Evidence;
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
use App\Services\CaseClosureService;
use App\Services\CaseFinalSummaryService;
use App\Services\DecisionService;
use App\Services\EvidenceService;
use App\Services\InvestigationService;
use App\Services\RecommendationService;
use App\Services\RecoveryService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaseWithdrawnTerminalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $satgas;

    private CaseRecord $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
        $this->admin = $this->makeUser('admin', 'withdrawn-admin@example.test');
        $this->satgas = $this->makeUser('satgas_ppks', 'withdrawn-satgas@example.test');
        $this->case = $this->makeWithdrawnCase();
    }

    public function test_withdrawn_is_excluded_from_operational_queries_but_remains_visible_as_history(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.totals.cases', 1)
            ->assertJsonPath('data.active_workflow.cases_open', 0);
        $this->getJson('/api/v1/dashboard/cases')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.assignments.assigned_cases', 0)
            ->assertJsonPath('data.assignments.unassigned_cases', 0)
            ->assertJsonPath('data.assignments.active_assignments', 0);
        $this->getJson('/api/v1/cases?quick_filter=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/cases?status=withdrawn')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->case->id);

        Sanctum::actingAs($this->satgas, ['*']);
        $this->getJson('/api/v1/my-work/summary')
            ->assertOk()
            ->assertJsonPath('data.assigned_active_cases', 0);
        $this->getJson('/api/v1/my-work/cases')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/my-work/cases?status=withdrawn')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_id', $this->case->id);
        $detail = $this->getJson("/api/v1/cases/{$this->case->id}")
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Withdrawn->value)
            ->assertJsonPath('data.assignments.0.satgas_id', $this->satgas->id);

        foreach ($detail->json('data.workflow_context.actions') as $action) {
            $this->assertFalse($action['allowed']);
        }

        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $this->case->id,
            'satgas_id' => $this->satgas->id,
            'is_active' => true,
        ]);
    }

    public function test_assignment_assessment_and_status_mutations_return_terminal_conflict_without_changes(): void
    {
        $otherSatgas = $this->makeUser('satgas_ppks', 'withdrawn-other@example.test');

        Sanctum::actingAs($this->admin, ['*']);
        $this->patchJson("/api/v1/cases/{$this->case->id}/assign", [
            'satgas_ids' => [$otherSatgas->id],
            'lead_satgas_id' => $otherSatgas->id,
        ])
            ->assertConflict()
            ->assertJsonPath('error_code', 'case_operationally_terminal');

        Sanctum::actingAs($this->satgas, ['*']);
        $this->patchJson("/api/v1/cases/{$this->case->id}/assessment", [
            'risk_level_code' => 'RISK-02',
            'priority_level_code' => 'PRIO-02',
        ])->assertConflict();
        $this->patchJson("/api/v1/cases/{$this->case->id}/status", [
            'status' => CaseStatusEnum::Escalated->value,
        ])->assertConflict();

        $this->assertSame(CaseStatusEnum::Withdrawn->value, $this->case->refresh()->status->name);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $this->case->id,
            'satgas_id' => $this->satgas->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('case_assignments', [
            'case_id' => $this->case->id,
            'satgas_id' => $otherSatgas->id,
            'is_active' => true,
        ]);
    }

    public function test_all_existing_child_workflow_mutations_fail_closed_for_withdrawn_case(): void
    {
        $investigation = Investigation::query()->create([
            'case_id' => $this->case->id,
            'lead_investigator_id' => $this->satgas->id,
            'status_code' => InvestigationStatus::query()
                ->where('name', InvestigationStatusEnum::Planning->value)
                ->value('code'),
            'plan_summary' => 'Rencana investigasi',
            'started_at' => now(),
        ]);
        $evidence = Evidence::query()->create([
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-01',
            'submitted_by' => $this->satgas->id,
            'title' => 'Bukti historis',
            'classification' => 'confidential',
            'status' => EvidenceStatus::Registered->value,
        ]);
        $recommendation = Recommendation::query()->create([
            'case_id' => $this->case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $this->satgas->id,
            'status_code' => RecommendationStatus::query()
                ->where('name', RecommendationStatusEnum::Drafting->value)
                ->value('code'),
            'conclusion' => 'Kesimpulan historis',
            'recommended_actions' => 'Tindakan historis',
        ]);
        $decision = Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $this->admin->id,
            'status_code' => DecisionStatus::query()
                ->where('name', DecisionStatusEnum::Draft->value)
                ->value('code'),
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'Ringkasan putusan',
            'decision_content' => 'Isi putusan',
            'recorded_at' => now(),
        ]);
        $recovery = Recovery::query()->create([
            'decision_id' => $decision->id,
            'recovery_type_code' => 'RCV-01',
            'status_code' => RecoveryStatus::query()
                ->where('name', RecoveryStatusEnum::Ongoing->value)
                ->value('code'),
            'created_by' => $this->admin->id,
            'recovery_plan' => 'Rencana pemulihan',
            'started_at' => now(),
        ]);

        $this->assertTerminalConflict(fn () => app(InvestigationService::class)->addActivity(
            $investigation,
            $this->satgas,
            [],
        ));
        $this->assertTerminalConflict(fn () => app(EvidenceService::class)->update(
            $evidence,
            $this->satgas,
            ['title' => 'Tidak boleh berubah'],
        ));
        $this->assertTerminalConflict(fn () => app(RecommendationService::class)->update(
            $recommendation,
            $this->satgas,
            ['conclusion' => 'Tidak boleh berubah'],
        ));
        $this->assertTerminalConflict(fn () => app(DecisionService::class)->update(
            $decision,
            $this->admin,
            ['decision_summary' => 'Tidak boleh berubah'],
        ));
        $this->assertTerminalConflict(fn () => app(RecoveryService::class)->update(
            $recovery,
            $this->admin,
            ['recovery_plan' => 'Tidak boleh berubah'],
        ));
        $this->assertTerminalConflict(fn () => app(RecoveryService::class)->createMonitoring(
            $recovery,
            $this->satgas,
            [],
        ));
        $this->assertTerminalConflict(fn () => app(CaseFinalSummaryService::class)->create(
            $this->case,
            $this->admin,
            [],
        ));
        $this->assertTerminalConflict(fn () => app(CaseClosureService::class)->close(
            $this->case,
            $this->satgas,
        ));

        $this->assertSame('Bukti historis', $evidence->fresh()->title);
        $this->assertSame('Kesimpulan historis', $recommendation->fresh()->conclusion);
        $this->assertDatabaseCount('recovery_monitorings', 0);
        $this->assertDatabaseCount('case_final_summaries', 0);
    }

    private function makeWithdrawnCase(): CaseRecord
    {
        $reporter = $this->makeUser('reporter', 'withdrawn-reporter@example.test');
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-20260724-9001',
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi historis untuk pengujian Case withdrawn.',
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Lokasi pengujian',
            'status' => ReportStatus::Withdrawn->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
            'withdrawn_at' => now(),
        ]);
        $status = CaseStatus::query()
            ->where('name', CaseStatusEnum::Withdrawn->value)
            ->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-20260724-9001',
            'status_code' => $status->code,
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'withdrawn_at' => now(),
        ]);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $this->satgas->id,
            'assigned_by' => $this->admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return $case;
    }

    private function makeUser(string $roleCode, string $email): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'university_id' => University::query()->where('code', 'DEMO-UNIV')->value('id'),
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function assertTerminalConflict(callable $mutation): void
    {
        try {
            $mutation();
            $this->fail('Expected terminal Case mutation to be rejected.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame(
                'case_operationally_terminal',
                json_decode((string) $exception->getResponse()->getContent(), true)['error_code'],
            );
        }
    }
}
