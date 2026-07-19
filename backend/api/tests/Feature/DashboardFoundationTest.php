<?php

namespace Tests\Feature;

use App\Enums\EvidenceClassification;
use App\Enums\EvidenceStatus;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\University;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_admin_receives_global_metadata_only_dashboard_aggregates(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        $this->makeDashboardCase($admin, $satgas);
        $this->makeDashboardCase($admin, $otherSatgas);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'campus')
            ->assertJsonPath('data.totals.reports', 2)
            ->assertJsonPath('data.totals.cases', 2)
            ->assertJsonMissingPath('data.totals.monitoring');

        $this->getJson('/api/v1/dashboard/reports')
            ->assertOk()
            ->assertJsonPath('data.by_identity_mode.identified', 2)
            ->assertJsonMissingPath('data.tracking_code')
            ->assertJsonMissingPath('data.chronology')
            ->assertJsonMissingPath('data.reporter');
    }

    public function test_satgas_dashboard_is_scoped_to_active_assigned_cases(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        $this->makeDashboardCase($admin, $satgas);
        $this->makeDashboardCase($admin, $otherSatgas);

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/dashboard/cases')
            ->assertOk()
            ->assertJsonPath('data.scope', 'assigned_cases')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.assignments.assigned_cases', 1)
            ->assertJsonPath('data.assignments.active_assignments', 1);
    }

    public function test_reporter_is_forbidden_from_dashboard_analytics(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');

        $this->actingAsApi($reporter);
        $this->getJson('/api/v1/dashboard/summary')->assertForbidden();
    }

    public function test_dashboard_filters_reject_invalid_and_too_large_ranges(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/dashboard/summary?date_from=2026-06-10&date_to=2026-06-01')
            ->assertUnprocessable();

        $this->getJson('/api/v1/dashboard/summary?date_from=2025-01-01&date_to=2026-06-11')
            ->assertUnprocessable();

        $this->getJson('/api/v1/dashboard/summary?granularity=year')
            ->assertUnprocessable();
    }

    public function test_evidence_analytics_are_count_based_and_do_not_expose_evidence_details(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeDashboardCase($admin, $satgas);
        $investigation = $this->makeInvestigation($case, $satgas);

        Evidence::query()->create([
            'investigation_id' => $investigation->id,
            'evidence_type_code' => 'EVID-01',
            'submitted_by' => $satgas->id,
            'title' => 'Sensitive evidence title',
            'description' => 'Sensitive evidence description',
            'source' => 'Sensitive source',
            'classification' => EvidenceClassification::Confidential->value,
            'status' => EvidenceStatus::Registered->value,
            'original_filename' => 'secret.png',
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/dashboard/evidence')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.file_metadata_presence.with_metadata', 1)
            ->assertJsonMissingPath('data.total_file_size')
            ->assertJsonMissingPath('data.original_filename')
            ->assertJsonMissingPath('data.checksum_sha256')
            ->assertJsonMissingPath('data.custody_events')
            ->assertJsonMissingPath('data.description')
            ->assertJsonMissingPath('data.source');
    }

    public function test_workflow_conversion_counts_are_descriptive_only(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');

        $this->makeDashboardCase($admin, $satgas);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/dashboard/workflow')
            ->assertOk()
            ->assertJsonPath('data.metric_semantics', 'descriptive_counts_only_not_kpi_not_sla_not_success_rate_not_performance_scoring')
            ->assertJsonMissingPath('data.sla')
            ->assertJsonMissingPath('data.kpi')
            ->assertJsonMissingPath('data.performance_score')
            ->assertJsonMissingPath('data.audit_logs');
    }

    private function makeDashboardCase(User $admin, User $satgas): CaseRecord
    {
        $reporter = $this->makeUser('reporter', 'dashboard-reporter-'.(Report::query()->count() + 1).'@university.ac.id');
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi rahasia yang tidak boleh muncul di analytics dashboard.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Lokasi rahasia',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);

        $status = CaseStatus::query()->where('name', 'investigation')->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
        ]);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return $case;
    }

    private function makeInvestigation(CaseRecord $case, User $satgas): Investigation
    {
        $status = InvestigationStatus::query()->where('name', 'planning')->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Rencana rahasia.',
            'started_at' => now(),
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
            'university_id' => University::query()->where('code', 'DEMO-UNIV')->firstOrFail()->id,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
