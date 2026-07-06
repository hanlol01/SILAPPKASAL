<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaseAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_assigned_satgas_can_record_assessment_while_case_is_in_assessment(): void
    {
        [$case, $satgas] = $this->assessmentCase();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/assessment", [
            'risk_level_code' => 'RISK-02',
            'priority_level_code' => 'PRIO-01',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.risk_level_code', 'RISK-02')
            ->assertJsonPath('data.priority', 'PRIO-01')
            ->assertJsonPath('data.status', CaseStatusEnum::Assessment->value)
            ->assertJsonMissingPath('data.report');

        $case->refresh();
        $this->assertSame('RISK-02', $case->risk_level_code);
        $this->assertSame('PRIO-01', $case->priority_code);
        $this->assertSame('CSTS-06', $case->status_code);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'case.assessment_recorded',
            'actor_id' => $satgas->id,
            'subject_id' => $case->id,
        ]);
    }

    public function test_assessment_response_contains_no_reporter_sensitive_content(): void
    {
        [$case, $satgas] = $this->assessmentCase();

        $this->actingAsApi($satgas);
        $response = $this->patchJson("/api/v1/cases/{$case->id}/assessment", [
            'risk_level_code' => 'RISK-03',
            'priority_level_code' => 'PRIO-02',
        ]);

        $response->assertOk();

        $raw = (string) $response->getContent();
        $this->assertStringNotContainsString('Kronologi asesmen sangat rahasia untuk pengujian.', $raw);
        $this->assertStringNotContainsString('Terlapor Asesmen Rahasia', $raw);
        $this->assertStringNotContainsString('Saksi asesmen rahasia', $raw);
    }

    public function test_non_assigned_satgas_cannot_record_assessment(): void
    {
        [$case] = $this->assessmentCase();
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@university.ac.id');

        $this->actingAsApi($otherSatgas);
        $this->patchJson("/api/v1/cases/{$case->id}/assessment", $this->validPayload())
            ->assertForbidden();

        $this->assertNull($case->refresh()->risk_level_code);
    }

    public function test_admin_super_admin_and_reporter_cannot_record_assessment(): void
    {
        [$case] = $this->assessmentCase();

        foreach ([
            $this->makeUser('super_admin', 'super-admin@university.ac.id'),
            $this->makeUser('admin', 'second-admin@university.ac.id'),
            $this->makeUser('reporter', 'reporter@university.ac.id'),
        ] as $user) {
            $this->actingAsApi($user);
            $this->patchJson("/api/v1/cases/{$case->id}/assessment", $this->validPayload())
                ->assertForbidden();
        }

        $this->assertNull($case->refresh()->risk_level_code);
    }

    public function test_assessment_is_rejected_when_case_is_not_in_assessment_status(): void
    {
        [$case, $satgas] = $this->assessmentCase(moveToAssessment: false);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/assessment", $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertNull($case->refresh()->risk_level_code);
    }

    public function test_closed_case_rejects_assessment(): void
    {
        [$case, $satgas] = $this->assessmentCase();
        $closed = CaseStatus::query()->where('name', CaseStatusEnum::Closed->value)->firstOrFail();
        $case->forceFill([
            'status_code' => $closed->code,
            'current_stage' => $closed->workflow_stage,
            'closed_at' => now(),
        ])->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/assessment", $this->validPayload())
            ->assertForbidden();
    }

    public function test_invalid_master_data_codes_are_rejected(): void
    {
        [$case, $satgas] = $this->assessmentCase();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/assessment", [
            'risk_level_code' => 'RISK-99',
            'priority_level_code' => 'PRIO-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['risk_level_code']);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/cases/{$case->id}/assessment", [
            'risk_level_code' => 'RISK-01',
            'priority_level_code' => 'PRIO-99',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority_level_code']);

        $this->assertNull($case->refresh()->risk_level_code);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'risk_level_code' => 'RISK-01',
            'priority_level_code' => 'PRIO-03',
        ];
    }

    /**
     * @return array{0: CaseRecord, 1: User}
     */
    private function assessmentCase(bool $moveToAssessment = true): array
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $report = $this->makeReport();

        $this->actingAsApi($admin);
        $response = $this->postJson("/api/v1/reports/{$report->id}/forward-to-case", [
            'satgas_ids' => [$satgas->id],
            'lead_satgas_id' => $satgas->id,
        ]);
        $response->assertOk();
        $this->flushHeaders();

        $case = CaseRecord::query()->findOrFail($response->json('data.case.id'));

        if ($moveToAssessment) {
            $assessment = CaseStatus::query()->where('name', CaseStatusEnum::Assessment->value)->firstOrFail();
            $case->forceFill([
                'status_code' => $assessment->code,
                'current_stage' => $assessment->workflow_stage,
                'assessment_at' => now(),
            ])->save();
        }

        return [$case, $satgas];
    }

    private function makeReport(): Report
    {
        return Report::query()->create([
            'registration_number' => 'SLP-'.now()->format('Y-md').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi asesmen sangat rahasia untuk pengujian.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Terlapor Asesmen Rahasia',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor asesmen rahasia.',
            'witness_info' => 'Saksi asesmen rahasia',
            'status' => ReportStatus::Submitted->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
        ]);
    }

    private function makeUser(string $roleCode, string $email): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
