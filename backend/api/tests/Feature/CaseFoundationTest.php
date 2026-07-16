<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_case_foundation_tables_exist_without_out_of_scope_tables(): void
    {
        $this->assertTrue(Schema::hasTable('cases'));
        $this->assertTrue(Schema::hasColumn('cases', 'case_number'));
        $this->assertTrue(Schema::hasColumn('cases', 'status_code'));
        $this->assertTrue(Schema::hasTable('case_assignments'));
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertDatabaseCount('evidences', 0);
        $this->assertFalse(Schema::hasTable('risk_assessments'));
        $this->assertTrue(Schema::hasTable('investigations'));
        $this->assertDatabaseCount('investigations', 0);
        $this->assertTrue(Schema::hasTable('recommendations'));
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertDatabaseCount('decisions', 0);
    }

    public function test_admin_can_forward_report_to_case_transactionally(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $response = $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => [$satgas->id],
            'lead_satgas_id' => $satgas->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.status', ReportStatus::Forwarded->value)
            ->assertJsonPath('data.case.status', CaseStatusEnum::Forwarded->value)
            ->assertJsonPath('data.case.assignments.0.satgas_id', $satgas->id)
            ->assertJsonPath('data.case.assignments.0.is_lead', true);

        $report->refresh();
        $case = CaseRecord::query()->where('report_id', $report->id)->firstOrFail();

        $this->assertSame(ReportStatus::Forwarded->value, $report->status);
        $this->assertNotSame($report->registration_number, $case->case_number);
        $this->assertSame('CSTS-05', $case->status_code);
        $this->assertDatabaseCount('case_assignments', 1);
    }

    public function test_invalid_satgas_assignment_does_not_forward_report(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => [$reporter->id],
            'lead_satgas_id' => $reporter->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('cases', 0);
        $this->assertSame(ReportStatus::Submitted->value, $report->refresh()->status);
    }

    public function test_duplicate_and_ineligible_forwarding_are_rejected(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $report = $this->makeReport();

        $payload = ['satgas_ids' => [$satgas->id], 'lead_satgas_id' => $satgas->id];

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", $payload)
            ->assertOk();

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", $payload)
            ->assertUnprocessable();

        $rejectedReport = $this->makeReport(['status' => ReportStatus::Rejected->value]);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/reports/{$rejectedReport->id}/forward-to-case", $payload)
            ->assertUnprocessable();
    }

    public function test_admin_reads_metadata_only_and_satgas_reads_assigned_case_detail(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/cases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.report.chronology');

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.report.chronology');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.report.chronology', $case->report->chronology);

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertForbidden();

        $this->actingAsApi($reporter);
        $this->getJson('/api/v1/cases')
            ->assertForbidden();
    }

    public function test_reassignment_preserves_assignment_history(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgasA = $this->makeUser('satgas_ppks', 'satgas-a@university.ac.id');
        $satgasB = $this->makeUser('satgas_ppks', 'satgas-b@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgasA], $satgasA);

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$satgasB->id],
            'lead_satgas_id' => $satgasB->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignments.0.satgas_id', $satgasB->id);

        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasA->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'satgas_id' => $satgasB->id,
            'is_active' => true,
            'is_lead' => true,
        ]);
        $this->assertSame(2, CaseAssignment::query()->where('case_id', $case->id)->count());
    }

    public function test_report_detail_includes_only_safe_active_case_assignment_context(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);

        $this->actingAsApi($admin);
        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonMissingPath('data.0.case');

        $this->getJson("/api/v1/reports/{$case->report_id}")
            ->assertOk()
            ->assertJsonPath('data.case.id', $case->id)
            ->assertJsonPath('data.case.case_number', $case->case_number)
            ->assertJsonPath('data.case.active_assignments.0.satgas_id', $satgas->id)
            ->assertJsonPath('data.case.active_assignments.0.satgas_name', $satgas->name)
            ->assertJsonPath('data.case.active_assignments.0.is_lead', true)
            ->assertJsonPath('data.case.active_assignments.0.is_active', true)
            ->assertJsonMissingPath('data.case.report')
            ->assertJsonMissingPath('data.case.active_assignments.0.id')
            ->assertJsonMissingPath('data.case.active_assignments.0.assigned_at')
            ->assertJsonMissingPath('data.case.active_assignments.0.satgas.email')
            ->assertJsonMissingPath('data.case.active_assignments.0.assigned_by');
    }

    public function test_status_transition_uses_master_data_and_report_status_remains_forwarded(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Assessment->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Assessment->value);

        $case->refresh();
        $this->assertSame('CSTS-06', $case->status_code);
        $this->assertNotNull($case->assessment_at);
        $this->assertSame(ReportStatus::Forwarded->value, $case->report->refresh()->status);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Closed->value,
        ])
            ->assertUnprocessable();
    }

    public function test_generic_case_status_api_cannot_bypass_controlled_workflow_transitions(): void
    {
        $admin = $this->makeUser('admin', 'admin-lifecycle@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-lifecycle@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-lifecycle@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);
        $recommendation = CaseStatus::query()->where('name', CaseStatusEnum::Recommendation->value)->firstOrFail();
        $decision = CaseStatus::query()->where('name', CaseStatusEnum::Decision->value)->firstOrFail();

        $recommendation->forceFill(['valid_transitions' => [CaseStatusEnum::Decision->value]])->save();
        $decision->forceFill(['valid_transitions' => [CaseStatusEnum::Decided->value]])->save();

        $case->forceFill([
            'status_code' => $recommendation->code,
            'current_stage' => $recommendation->workflow_stage,
        ])->save();
        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Decision->value,
        ])->assertForbidden();
        $this->assertSame(CaseStatusEnum::Recommendation->value, $case->refresh()->status->name);

        foreach ([$admin, $superAdmin] as $unauthorizedActor) {
            $this->actingAsApi($unauthorizedActor);
            $this->patchJson("/api/v1/cases/{$case->id}/status", [
                'status' => CaseStatusEnum::Decision->value,
            ])->assertForbidden();
        }

        $case->forceFill([
            'status_code' => $decision->code,
            'current_stage' => $decision->workflow_stage,
        ])->save();
        foreach ([$satgas, $admin, $superAdmin] as $unauthorizedActor) {
            $this->actingAsApi($unauthorizedActor);
            $this->patchJson("/api/v1/cases/{$case->id}/status", [
                'status' => CaseStatusEnum::Decided->value,
            ])->assertForbidden();
        }
        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
    }

    public function test_closed_case_rejects_transition_and_assignment(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->forwardedCase($admin, [$satgas], $satgas);
        $monitoring = CaseStatus::query()->where('name', CaseStatusEnum::Monitoring->value)->firstOrFail();

        $case->forceFill([
            'status_code' => $monitoring->code,
            'current_stage' => $monitoring->workflow_stage,
        ])->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Closed->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Closed->value);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/status", [
            'status' => CaseStatusEnum::Assessment->value,
        ])
            ->assertForbidden();

        $this->actingAsApi($admin);
        $this->patchJson("/api/v1/cases/{$case->id}/assign", [
            'satgas_ids' => [$otherSatgas->id],
            'lead_satgas_id' => $otherSatgas->id,
        ])
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeReport(array $overrides = []): Report
    {
        return Report::query()->create(array_merge([
            'registration_number' => 'SLP-'.now()->format('Y-md').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji case foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian akses Satgas.',
            'witness_info' => 'Informasi saksi untuk pengujian akses Satgas.',
            'status' => ReportStatus::Submitted->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
        ], $overrides));
    }

    /**
     * @param list<User> $satgasUsers
     */
    private function forwardedCase(User $admin, array $satgasUsers, User $lead): CaseRecord
    {
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $response = $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => collect($satgasUsers)->pluck('id')->all(),
            'lead_satgas_id' => $lead->id,
        ]);

        $response->assertOk();
        $this->flushHeaders();

        return CaseRecord::query()->with('report')->findOrFail($response->json('data.case.id'));
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
