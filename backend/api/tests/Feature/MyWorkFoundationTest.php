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
use App\Models\RecommendationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\University;
use App\Services\NotificationService;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyWorkFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_satgas_sees_only_active_assigned_work_summary_and_cases(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        $assignedCase = $this->makeCase($admin, $satgas, CaseStatusEnum::Investigation);
        $this->makeCase($admin, $otherSatgas, CaseStatusEnum::Investigation);
        $inactiveCase = $this->makeCase($admin, $satgas, CaseStatusEnum::Investigation, false);

        app(NotificationService::class)->caseAssigned($assignedCase, [$satgas->id]);

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/my-work/summary')
            ->assertOk()
            ->assertJsonPath('data.scope', 'assigned_cases')
            ->assertJsonPath('data.assigned_active_cases', 1)
            ->assertJsonPath('data.unread_notifications', 1);

        $this->getJson('/api/v1/my-work/cases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_id', $assignedCase->id)
            ->assertJsonMissingPath('data.0.risk_level_code')
            ->assertJsonMissingPath('data.0.chronology')
            ->assertJsonMissingPath('data.0.tracking_code')
            ->assertJsonMissingPath('data.0.reporter');

        $this->assertNotSame($inactiveCase->id, $assignedCase->id);
    }

    public function test_admin_sees_campus_work_and_super_admin_has_no_operational_work(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        $this->makeCase($admin, $satgas, CaseStatusEnum::Investigation);
        $this->makeCase($admin, $otherSatgas, CaseStatusEnum::Investigation);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/my-work/cases')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.risk_level_code')
            ->assertJsonMissingPath('data.0.chronology');

        $this->actingAsApi($superAdmin);
        $this->getJson('/api/v1/my-work/cases')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reporter_is_forbidden_from_my_work(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');

        $this->actingAsApi($reporter);
        $this->getJson('/api/v1/my-work/summary')->assertForbidden();
        $this->getJson('/api/v1/my-work/cases')->assertForbidden();
        $this->getJson('/api/v1/my-work/investigations')->assertForbidden();
        $this->getJson('/api/v1/my-work/recommendations')->assertForbidden();
    }

    public function test_pending_investigations_are_scoped_and_do_not_expose_narratives(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        $case = $this->makeCase($admin, $satgas, CaseStatusEnum::Investigation);
        $otherCase = $this->makeCase($admin, $otherSatgas, CaseStatusEnum::Investigation);
        $this->makeInvestigation($case, $satgas, InvestigationStatusEnum::Planning);
        $this->makeInvestigation($otherCase, $otherSatgas, InvestigationStatusEnum::Planning);

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/my-work/investigations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_id', $case->id)
            ->assertJsonMissingPath('data.0.plan_summary')
            ->assertJsonMissingPath('data.0.findings')
            ->assertJsonMissingPath('data.0.conclusion')
            ->assertJsonMissingPath('data.0.evidence');
    }

    public function test_pending_recommendations_include_missing_or_not_submitted_recommendations_only(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $otherAdmin = $this->makeUser('admin', 'other-admin@university.ac.id', 'DEMO-ST');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');

        $missingRecommendationCase = $this->makeCase($admin, $satgas, CaseStatusEnum::Recommendation);
        $draftRecommendationCase = $this->makeCase($admin, $satgas, CaseStatusEnum::Recommendation);
        $submittedRecommendationCase = $this->makeCase($admin, $satgas, CaseStatusEnum::Recommendation);

        $this->makeRecommendation($draftRecommendationCase, $satgas, RecommendationStatusEnum::Drafting);
        $this->makeRecommendation($submittedRecommendationCase, $satgas, RecommendationStatusEnum::SubmittedForReview);

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/my-work/recommendations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['case_id' => $missingRecommendationCase->id])
            ->assertJsonFragment(['case_id' => $draftRecommendationCase->id])
            ->assertJsonMissingPath('data.0.conclusion')
            ->assertJsonMissingPath('data.0.recommended_actions')
            ->assertJsonMissingPath('data.0.decision_content');

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/my-work/recommendations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_id', $submittedRecommendationCase->id)
            ->assertJsonMissingPath('data.0.conclusion')
            ->assertJsonMissingPath('data.0.recommended_actions');

        $this->actingAsApi($otherAdmin);
        $this->getJson('/api/v1/my-work/recommendations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAsApi($superAdmin);
        $this->getJson('/api/v1/my-work/recommendations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_my_work_filters_do_not_accept_priority_filter(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeCase($admin, $satgas, CaseStatusEnum::Investigation);

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/my-work/cases?priority=PRIO-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_id', $case->id);
    }

    private function makeRecommendation(CaseRecord $case, User $satgas, RecommendationStatusEnum $statusName): Recommendation
    {
        $investigation = $this->makeInvestigation($case, $satgas, InvestigationStatusEnum::Completed);
        $status = RecommendationStatus::query()->where('name', $statusName->value)->firstOrFail();

        return Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $status->code,
            'conclusion' => 'Sensitive recommendation conclusion must not be exposed.',
            'recommended_actions' => 'Sensitive recommendation actions must not be exposed.',
            'submitted_at' => $statusName === RecommendationStatusEnum::SubmittedForReview ? now() : null,
        ]);
    }

    private function makeInvestigation(CaseRecord $case, User $satgas, InvestigationStatusEnum $statusName): Investigation
    {
        $status = InvestigationStatus::query()->where('name', $statusName->value)->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Sensitive plan summary must not be exposed.',
            'findings' => 'Sensitive findings must not be exposed.',
            'conclusion' => 'Sensitive conclusion must not be exposed.',
            'started_at' => now(),
            'completed_at' => $statusName === InvestigationStatusEnum::Completed ? now() : null,
        ]);
    }

    private function makeCase(User $admin, User $satgas, CaseStatusEnum $statusName, bool $activeAssignment = true): CaseRecord
    {
        $reporter = $this->makeUser('reporter', 'my-work-reporter-'.(Report::query()->count() + 1).'@university.ac.id');
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Sensitive chronology must not be exposed by my work queues.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Sensitive location',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Sensitive respondent',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);

        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'risk_level_code' => 'RISK-01',
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
            'is_active' => $activeAssignment,
            'assigned_at' => now(),
            'unassigned_at' => $activeAssignment ? null : now(),
        ]);

        return $case;
    }

    private function makeUser(
        string $roleCode,
        string $email,
        string $universityCode = 'DEMO-UNIV',
    ): User
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
