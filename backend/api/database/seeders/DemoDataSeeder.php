<?php

namespace Database\Seeders;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\EvidenceClassification;
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
use App\Models\EvidenceType;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Recovery;
use App\Models\RecoveryStatus;
use App\Models\RecoveryType;
use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'DemoPass123!';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDataSeeder is disabled in production.');
        }

        DB::transaction(function (): void {
            $university = University::query()->where('code', 'DEMO-UNIV')->first();
            $universityAttributes = $university ? ['university_id' => $university->id] : [];

            $superAdmin = $this->demoUser('super_admin', 'Demo Super Admin', 'demo.superadmin@silappkasal.test', $universityAttributes);
            $admin = $this->demoUser('admin', 'Demo Admin', 'demo.admin@silappkasal.test', $universityAttributes);
            $satgas = $this->demoUser('satgas_ppks', 'Demo Satgas', 'demo.satgas@silappkasal.test', $universityAttributes);
            $reporter = $this->demoUser('reporter', 'Demo Reporter', 'demo.reporter@silappkasal.test', [
                ...$universityAttributes,
                'nim' => '2026000001',
                'phone_number' => '081200000001',
            ]);

            $reportA = $this->report($reporter, 'DEMO-SLP-20260611-0001', ReportStatus::Submitted, [
                'report_type' => 'confidential',
                'status' => ReportStatus::Submitted->value,
                'submitted_at' => now()->subDays(7),
                'forwarded_at' => null,
            ]);

            $reportB = $this->report($reporter, 'DEMO-SLP-20260611-0002', ReportStatus::Forwarded, [
                'report_type' => 'confidential',
                'status' => ReportStatus::Forwarded->value,
                'submitted_at' => now()->subDays(6),
                'forwarded_at' => now()->subDays(5),
            ]);

            $reportC = $this->report($reporter, 'DEMO-SLP-20260611-0003', ReportStatus::Forwarded, [
                'report_type' => 'open',
                'status' => ReportStatus::Forwarded->value,
                'submitted_at' => now()->subDays(12),
                'forwarded_at' => now()->subDays(11),
            ]);

            $caseB = $this->caseRecord(
                $reportB,
                'DEMO-CASE-20260611-0001',
                CaseStatusEnum::Investigation,
                now()->subDays(5)
            );
            $caseC = $this->caseRecord(
                $reportC,
                'DEMO-CASE-20260611-0002',
                CaseStatusEnum::Recovery,
                now()->subDays(11)
            );

            $this->assignment($caseB, $satgas, $admin, now()->subDays(5));
            $this->assignment($caseC, $satgas, $admin, now()->subDays(11));

            $investigationB = $this->investigation(
                $caseB,
                $satgas,
                InvestigationStatusEnum::EvidenceCollection,
                now()->subDays(4)
            );
            $investigationC = $this->investigation(
                $caseC,
                $satgas,
                InvestigationStatusEnum::Completed,
                now()->subDays(10),
                now()->subDays(7)
            );

            $recommendation = $this->recommendation($caseC, $investigationC, $satgas);
            $decision = $this->decision($recommendation, $admin);
            $this->recovery($decision, $admin);

            $this->evidence(
                $investigationB,
                $satgas,
                'Demo Evidence - Interview Schedule Metadata',
                EvidenceStatus::Registered
            );
            $this->evidence(
                $investigationC,
                $satgas,
                'Demo Evidence - Document Review Metadata',
                EvidenceStatus::Verified
            );

            $reportA->refresh();
            $superAdmin->refresh();
        });
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function demoUser(string $roleCode, string $name, string $email, array $extra = []): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->updateOrCreate(
            ['email' => $email],
            array_merge([
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
            ], $extra)
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function report(User $reporter, string $registrationNumber, ReportStatus $status, array $overrides): Report
    {
        $category = ReportCategory::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        return Report::query()->updateOrCreate(
            ['registration_number' => $registrationNumber],
            array_merge([
                'reporter_id' => $reporter->id,
                'tracking_code' => null,
                'report_type' => 'confidential',
                'category_code' => $category->code,
                'chronology' => 'Demo fictional report narrative for internal validation only. No real identity, location, or sensitive incident details are represented here.',
                'incident_date' => now()->subDays(8)->toDateString(),
                'incident_time' => '09:30',
                'incident_location' => 'Demo campus public area',
                'location_type' => $this->masterCode('location_types'),
                'respondent_name' => 'Demo Respondent',
                'respondent_campus_status' => $this->masterCode('campus_statuses'),
                'respondent_relation' => $this->masterCode('relations'),
                'respondent_details' => 'Fictional respondent context for demo workflow validation only.',
                'witness_info' => 'Fictional witness availability noted for demo data only.',
                'reporter_phone_encrypted' => $reporter->phone_number,
                'status' => $status->value,
                'priority' => $this->masterCode('priority_levels'),
                'submitted_at' => now()->subDays(8),
                'reviewed_at' => null,
                'forwarded_at' => null,
            ], $overrides)
        );
    }

    private function caseRecord(Report $report, string $caseNumber, CaseStatusEnum $statusName, mixed $forwardedAt): CaseRecord
    {
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        return CaseRecord::query()->updateOrCreate(
            ['case_number' => $caseNumber],
            [
                'report_id' => $report->id,
                'registration_number' => $report->registration_number,
                'status_code' => $status->code,
                'risk_level_code' => $this->masterCode('risk_levels'),
                'priority_code' => $report->priority,
                'current_stage' => $status->workflow_stage,
                'forwarded_at' => $forwardedAt,
                'assessment_at' => $statusName === CaseStatusEnum::Recovery ? now()->subDays(10) : null,
                'investigation_started_at' => now()->subDays(4),
                'recommendation_at' => $statusName === CaseStatusEnum::Recovery ? now()->subDays(6) : null,
                'decision_at' => $statusName === CaseStatusEnum::Recovery ? now()->subDays(4) : null,
            ]
        );
    }

    private function assignment(CaseRecord $case, User $satgas, User $admin, mixed $assignedAt): CaseAssignment
    {
        return CaseAssignment::query()->updateOrCreate(
            [
                'case_id' => $case->id,
                'satgas_id' => $satgas->id,
            ],
            [
                'assigned_by' => $admin->id,
                'is_lead' => true,
                'is_active' => true,
                'assigned_at' => $assignedAt,
                'unassigned_at' => null,
            ]
        );
    }

    private function investigation(
        CaseRecord $case,
        User $satgas,
        InvestigationStatusEnum $statusName,
        mixed $startedAt,
        mixed $completedAt = null
    ): Investigation {
        $status = InvestigationStatus::query()->where('name', $statusName->value)->firstOrFail();

        return Investigation::query()->updateOrCreate(
            ['case_id' => $case->id],
            [
                'lead_investigator_id' => $satgas->id,
                'status_code' => $status->code,
                'plan_summary' => 'Demo investigation plan summary for internal workflow validation.',
                'findings' => $completedAt ? 'Demo non-sensitive findings summary for completed workflow validation.' : null,
                'conclusion' => $completedAt ? 'Demo conclusion placeholder for recommendation validation.' : null,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ]
        );
    }

    private function recommendation(CaseRecord $case, Investigation $investigation, User $satgas): Recommendation
    {
        $status = RecommendationStatus::query()
            ->where('name', RecommendationStatusEnum::SubmittedToLeader->value)
            ->firstOrFail();

        return Recommendation::query()->updateOrCreate(
            ['case_id' => $case->id],
            [
                'investigation_id' => $investigation->id,
                'author_id' => $satgas->id,
                'status_code' => $status->code,
                'conclusion' => 'Demo recommendation conclusion for internal validation only.',
                'recommended_actions' => 'Demo recommended action: continue institutional follow-up process.',
                'sanction_recommendation' => 'Demo sanction recommendation placeholder.',
                'recovery_recommendation' => 'Demo recovery recommendation placeholder.',
                'prevention_recommendation' => 'Demo prevention recommendation placeholder.',
                'submitted_at' => now()->subDays(6),
            ]
        );
    }

    private function decision(Recommendation $recommendation, User $admin): Decision
    {
        $status = DecisionStatus::query()->where('name', DecisionStatusEnum::Finalized->value)->firstOrFail();

        return Decision::query()->updateOrCreate(
            ['recommendation_id' => $recommendation->id],
            [
                'recorder_id' => $admin->id,
                'status_code' => $status->code,
                'outcome_code' => DecisionOutcome::Accepted->value,
                'decision_number' => 'DEMO-DEC-20260611-0001',
                'decision_date' => now()->subDays(4)->toDateString(),
                'decision_summary' => 'Demo institutional decision summary for validation only.',
                'decision_content' => 'Demo decision content with fictional non-sensitive wording.',
                'recorded_at' => now()->subDays(4),
                'finalized_at' => now()->subDays(3),
            ]
        );
    }

    private function recovery(Decision $decision, User $admin): Recovery
    {
        $type = RecoveryType::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $status = RecoveryStatus::query()->where('name', RecoveryStatusEnum::Ongoing->value)->firstOrFail();

        return Recovery::query()->updateOrCreate(
            [
                'decision_id' => $decision->id,
                'recovery_type_code' => $type->code,
            ],
            [
                'status_code' => $status->code,
                'created_by' => $admin->id,
                'recovery_plan' => 'Demo recovery plan for internal workflow validation.',
                'support_needs' => 'Demo support needs placeholder.',
                'notes' => 'Demo recovery note without sensitive content.',
                'started_at' => now()->subDays(2),
                'completed_at' => null,
                'discontinued_at' => null,
            ]
        );
    }

    private function evidence(Investigation $investigation, User $satgas, string $title, EvidenceStatus $status): Evidence
    {
        $type = EvidenceType::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        return Evidence::query()->updateOrCreate(
            [
                'investigation_id' => $investigation->id,
                'title' => $title,
            ],
            [
                'evidence_type_code' => $type->code,
                'submitted_by' => $satgas->id,
                'description' => 'Demo evidence metadata only. No file upload, preview, or storage object exists.',
                'source' => 'Demo internal QA source.',
                'collected_at' => now()->subDays(3),
                'classification' => EvidenceClassification::Confidential->value,
                'status' => $status->value,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum_sha256' => null,
            ]
        );
    }

    private function masterCode(string $table): string
    {
        $row = DB::table($table)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if (! $row) {
            throw new RuntimeException("Required master data table [{$table}] has no active rows.");
        }

        return $row->code;
    }
}
