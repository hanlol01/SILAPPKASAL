<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiErrorCode;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestigationWorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_assessment_gate_requires_both_risk_and_priority_and_context_refreshes(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Assessment);
        $investigationStatus = CaseStatus::query()->where('name', CaseStatusEnum::Investigation->value)->firstOrFail();
        CaseStatus::query()->where('name', CaseStatusEnum::Assessment->value)->firstOrFail()
            ->forceFill(['valid_transitions' => [CaseStatusEnum::Investigation->value]])
            ->save();

        $this->actingAsApi($lead);
        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.workflow_context.facts.assessment_complete', false)
            ->assertJsonPath('data.workflow_context.actions.update_case_status.allowed', false)
            ->assertJsonPath('data.workflow_context.actions.update_case_status.reason_code', ApiErrorCode::CaseAssessmentRequired);

        $this->withHeader('Accept-Language', 'id')
            ->patchJson("/api/v1/cases/{$case->id}/status", ['status' => $investigationStatus->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseAssessmentRequired)
            ->assertJsonPath('message', 'Catat tingkat risiko dan prioritas sebelum melanjutkan kasus ke tahap investigasi.');

        $riskCode = DB::table('risk_levels')->where('is_active', true)->value('code');
        $priorityCode = DB::table('priority_levels')->where('is_active', true)->value('code');
        $case->forceFill(['risk_level_code' => $riskCode])->save();
        $this->withHeader('Accept-Language', 'en')
            ->patchJson("/api/v1/cases/{$case->id}/status", ['status' => $investigationStatus->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseAssessmentRequired)
            ->assertJsonPath('message', 'Record the risk level and priority before advancing the case to investigation.');

        $this->patchJson("/api/v1/cases/{$case->id}/assessment", [
            'risk_level_code' => $riskCode,
            'priority_level_code' => $priorityCode,
        ])
            ->assertOk()
            ->assertJsonPath('data.workflow_context.facts.assessment_complete', true)
            ->assertJsonPath('data.workflow_context.actions.update_case_status.allowed', true);

        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => $investigationStatus->code])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Investigation->value);
    }

    public function test_only_active_assigned_lead_can_create_and_server_derives_lead(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $member = $this->makeUser('satgas_ppks', 'member@example.test');
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $member->id,
            'assigned_by' => $case->assignments()->value('assigned_by'),
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        $inactiveLead = $this->makeUser('satgas_ppks', 'inactive-lead@example.test');
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $inactiveLead->id,
            'assigned_by' => $case->assignments()->value('assigned_by'),
            'is_lead' => true,
            'is_active' => false,
            'assigned_at' => now(),
            'unassigned_at' => now(),
        ]);

        $payload = ['plan_summary' => str_repeat('Rencana investigasi aman. ', 3)];
        $this->actingAsApi($member);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", $payload)->assertForbidden();

        $this->actingAsApi($inactiveLead);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", $payload)->assertForbidden();

        $this->actingAsApi($lead);
        $this->postJson("/api/v1/cases/{$case->id}/investigations", [
            ...$payload,
            'lead_investigator_id' => $member->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('lead_investigator_id');

        $this->postJson("/api/v1/cases/{$case->id}/investigations", $payload)
            ->assertCreated()
            ->assertJsonPath('data.lead_investigator.id', $lead->id);
    }

    public function test_activity_uses_locked_server_stage_and_enforces_type_compatibility(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Planning);
        $this->actingAsApi($lead);

        $base = [
            'activity_date' => now()->toDateString(),
            'description' => 'Aktivitas pengujian tahap investigasi.',
        ];
        $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            ...$base,
            'activity_type' => 'victim_interview',
        ])->assertUnprocessable()->assertJsonPath('error_code', ApiErrorCode::InvestigationActivityStageIncompatible);

        $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            ...$base,
            'activity_type' => 'case_review',
            'investigation_stage_code' => 'INVS-99',
        ])->assertUnprocessable()->assertJsonValidationErrors('investigation_stage_code');

        foreach (['case_review', 'document_review'] as $type) {
            $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
                ...$base,
                'activity_type' => $type,
            ])->assertCreated()->assertJsonPath('data.investigation_stage', InvestigationStatusEnum::Planning->value);
        }

        $this->assertDatabaseCount('investigation_activities', 2);
        $this->assertDatabaseHas('investigation_activities', [
            'investigation_id' => $investigation->id,
            'investigation_stage_code' => $investigation->status_code,
        ]);
    }

    public function test_every_entered_stage_requires_activity_but_valid_skips_do_not(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Planning);
        $drafting = InvestigationStatus::query()->where('name', InvestigationStatusEnum::ReportDrafting->value)->firstOrFail();
        $completed = InvestigationStatus::query()->where('name', InvestigationStatusEnum::Completed->value)->firstOrFail();
        $this->actingAsApi($lead);

        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", ['status' => $drafting->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::InvestigationStageActivityRequired);

        $this->addActivity($investigation, 'case_review')->assertCreated();
        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", ['status' => $drafting->code])
            ->assertOk()
            ->assertJsonPath('data.status', InvestigationStatusEnum::ReportDrafting->value);

        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", ['status' => $completed->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::InvestigationStageActivityRequired);

        $investigation->refresh();
        $this->addActivity($investigation, 'report_drafting')->assertCreated();
        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", ['status' => $completed->code])
            ->assertOk()
            ->assertJsonPath('data.status', InvestigationStatusEnum::Completed->value);

        $this->assertDatabaseMissing('investigation_activities', ['investigation_stage_code' => 'INVS-02']);
    }

    public function test_all_activity_types_follow_the_explicit_stage_compatibility_matrix(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Planning);
        $this->actingAsApi($lead);
        $matrix = [
            'case_review' => ['planning'],
            'document_review' => ['planning', 'evidence_collection', 'evidence_analysis'],
            'timeline_review' => ['planning', 'evidence_collection', 'evidence_analysis'],
            'victim_interview' => ['victim_interview'],
            'witness_interview' => ['witness_interview'],
            'respondent_interview' => ['respondent_interview'],
            'evidence_analysis' => ['evidence_analysis'],
            'report_drafting' => ['report_drafting'],
        ];

        foreach ($matrix as $type => $permittedStages) {
            foreach ($permittedStages as $stage) {
                $status = InvestigationStatus::query()->where('name', $stage)->firstOrFail();
                $investigation->forceFill(['status_code' => $status->code])->save();
                $this->addActivity($investigation, $type)->assertCreated();
            }

            $blockedStage = collect(InvestigationStatusEnum::values())
                ->reject(fn (string $stage): bool => $stage === InvestigationStatusEnum::Completed->value || in_array($stage, $permittedStages, true))
                ->first();
            $this->assertIsString($blockedStage);
            $blockedStatus = InvestigationStatus::query()->where('name', $blockedStage)->firstOrFail();
            $investigation->forceFill(['status_code' => $blockedStatus->code])->save();
            $this->addActivity($investigation, $type)
                ->assertUnprocessable()
                ->assertJsonPath('error_code', ApiErrorCode::InvestigationActivityStageIncompatible);
        }
    }

    public function test_legacy_null_stage_activity_remains_visible_but_does_not_satisfy_gate(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Planning);
        $investigation->activities()->create([
            'investigator_id' => $lead->id,
            'activity_type' => 'case_review',
            'activity_date' => now()->toDateString(),
            'description' => 'Aktivitas legacy tanpa atribusi tahap.',
        ]);
        $drafting = InvestigationStatus::query()->where('name', InvestigationStatusEnum::ReportDrafting->value)->firstOrFail();

        $this->actingAsApi($lead);
        $this->getJson("/api/v1/investigations/{$investigation->id}")
            ->assertOk()
            ->assertJsonPath('data.activities.0.investigation_stage_code', null)
            ->assertJsonPath('data.activities.0.description', 'Aktivitas legacy tanpa atribusi tahap.');
        $this->patchJson("/api/v1/investigations/{$investigation->id}/status", ['status' => $drafting->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::InvestigationStageActivityRequired);
    }

    public function test_case_cannot_leave_investigation_until_investigation_is_completed(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $recommendation = CaseStatus::query()->where('name', CaseStatusEnum::Recommendation->value)->firstOrFail();
        CaseStatus::query()->where('name', CaseStatusEnum::Investigation->value)->firstOrFail()
            ->forceFill(['valid_transitions' => [CaseStatusEnum::Recommendation->value]])
            ->save();
        $this->actingAsApi($lead);

        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => $recommendation->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseInvestigationCompletionRequired);

        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Completed);
        $this->patchJson("/api/v1/cases/{$case->id}/status", ['status' => $recommendation->code])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Recommendation->value);
        $this->assertNotNull($investigation->completed_at);

        [, $secondLead, $secondCase] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $mediation = CaseStatus::query()->where('name', CaseStatusEnum::Mediation->value)->firstOrFail();
        CaseStatus::query()->where('name', CaseStatusEnum::Investigation->value)->firstOrFail()
            ->forceFill(['valid_transitions' => [CaseStatusEnum::Mediation->value, CaseStatusEnum::Recommendation->value]])
            ->save();
        $this->actingAsApi($secondLead);
        $this->patchJson("/api/v1/cases/{$secondCase->id}/status", ['status' => $mediation->code])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ApiErrorCode::CaseInvestigationCompletionRequired);
        $this->makeInvestigation($secondCase, $secondLead, InvestigationStatusEnum::Completed);
        $this->patchJson("/api/v1/cases/{$secondCase->id}/status", ['status' => $mediation->code])
            ->assertOk()
            ->assertJsonPath('data.status', CaseStatusEnum::Mediation->value);
    }

    public function test_internal_evidence_creation_requires_open_investigation_stage_and_active_assignment(): void
    {
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Planning);
        $payload = [
            'evidence_type_code' => DB::table('evidence_types')->where('is_active', true)->value('code'),
            'title' => 'Bukti internal tahap investigasi',
            'classification' => 'confidential',
        ];
        $this->actingAsApi($lead);
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", $payload)->assertCreated();

        $recommendationStatus = CaseStatus::query()->where('name', CaseStatusEnum::Recommendation->value)->firstOrFail();
        $case->forceFill(['status_code' => $recommendationStatus->code])->save();
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", $payload)->assertUnprocessable();
        $investigationCaseStatus = CaseStatus::query()->where('name', CaseStatusEnum::Investigation->value)->firstOrFail();
        $case->forceFill(['status_code' => $investigationCaseStatus->code])->save();

        $completed = InvestigationStatus::query()->where('name', InvestigationStatusEnum::Completed->value)->firstOrFail();
        $investigation->forceFill(['status_code' => $completed->code, 'completed_at' => now()])->save();
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", $payload)->assertUnprocessable();

        $investigation->forceFill(['status_code' => InvestigationStatus::query()->where('name', InvestigationStatusEnum::Planning->value)->value('code'), 'completed_at' => null])->save();
        $case->activeAssignments()->update(['is_active' => false]);
        $this->postJson("/api/v1/investigations/{$investigation->id}/evidences", $payload)->assertForbidden();
    }

    public function test_activity_schema_is_nullable_indexed_and_detail_is_renderable(): void
    {
        $this->assertTrue(Schema::hasColumn('investigation_activities', 'investigation_stage_code'));
        [, $lead, $case] = $this->caseWithLead(CaseStatusEnum::Investigation);
        $investigation = $this->makeInvestigation($case, $lead, InvestigationStatusEnum::Planning);
        $this->actingAsApi($lead);
        $this->addActivity($investigation, 'case_review')->assertCreated();

        $this->getJson("/api/v1/investigations/{$investigation->id}")
            ->assertOk()
            ->assertJsonPath('data.current_stage_activity_count', 1)
            ->assertJsonPath('data.activities.0.activity_type', 'case_review')
            ->assertJsonPath('data.activities.0.investigation_stage', InvestigationStatusEnum::Planning->value)
            ->assertJsonPath('data.activities.0.investigator.id', $lead->id)
            ->assertJsonStructure(['data' => ['activities' => [['activity_date', 'description', 'findings', 'notes', 'created_at']]]]);
        $this->getJson("/api/v1/investigations/{$investigation->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_stage_activity_count', 1)
            ->assertJsonPath('data.can_transition', true)
            ->assertJsonPath('data.reason_code', null);
    }

    private function addActivity(Investigation $investigation, string $type)
    {
        return $this->postJson("/api/v1/investigations/{$investigation->id}/activities", [
            'activity_type' => $type,
            'activity_date' => now()->toDateString(),
            'description' => "Aktivitas {$type} untuk tahap berjalan.",
            'findings' => 'Temuan uji.',
            'notes' => 'Catatan uji.',
        ]);
    }

    /** @return array{User, User, CaseRecord} */
    private function caseWithLead(CaseStatusEnum $status): array
    {
        $admin = $this->makeUser('admin', 'admin-'.uniqid().'@example.test');
        $lead = $this->makeUser('satgas_ppks', 'lead-'.uniqid().'@example.test');
        $report = Report::query()->create([
            'registration_number' => 'SLP-'.uniqid(),
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => str_repeat('Kronologi pengujian. ', 4),
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Kampus pengujian',
            'status' => ReportStatus::Forwarded->value,
            'submitted_at' => now(),
            'forwarded_at' => now(),
            'forwarded_by' => $admin->id,
        ]);
        $caseStatus = CaseStatus::query()->where('name', $status->value)->firstOrFail();
        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.uniqid(),
            'status_code' => $caseStatus->code,
            'current_stage' => $caseStatus->workflow_stage,
            'forwarded_at' => now(),
        ]);
        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $lead->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return [$admin, $lead, $case->load(['status', 'report'])];
    }

    private function makeInvestigation(CaseRecord $case, User $lead, InvestigationStatusEnum $status): Investigation
    {
        $master = InvestigationStatus::query()->where('name', $status->value)->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $lead->id,
            'status_code' => $master->code,
            'plan_summary' => str_repeat('Rencana investigasi. ', 4),
            'started_at' => now(),
            'completed_at' => $status === InvestigationStatusEnum::Completed ? now() : null,
        ]);
    }

    private function makeUser(string $roleCode, string $email): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'name' => $roleCode.' User',
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
