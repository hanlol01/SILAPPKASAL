<?php

namespace Database\Seeders\Demo;

use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\Report;
use Illuminate\Database\Seeder;

class DemoCaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoReportSeeder::CASE_REPORTS as [$registrationNumber, $caseNumber, $caseStatus, $universityCode, $daysAgo]) {
            $report = Report::query()->where('registration_number', $registrationNumber)->firstOrFail();
            $status = DemoSeed::caseStatus($caseStatus);

            $case = CaseRecord::query()->updateOrCreate(
                ['case_number' => $caseNumber],
                [
                    'report_id' => $report->id,
                    'registration_number' => $report->registration_number,
                    'status_code' => $status->code,
                    'risk_level_code' => DemoSeed::masterCode('risk_levels', $daysAgo % 3),
                    'priority_code' => $report->priority,
                    'current_stage' => $status->workflow_stage,
                    'forwarded_at' => $report->forwarded_at ?? DemoSeed::date($daysAgo),
                    'assessment_at' => $this->timestampFor($caseStatus, ['assessment', 'investigation', 'recommendation', 'decision', 'decided', 'recovery', 'monitoring', 'closed'], $daysAgo - 1),
                    'investigation_started_at' => $this->timestampFor($caseStatus, ['investigation', 'recommendation', 'decision', 'decided', 'recovery', 'monitoring', 'closed'], $daysAgo - 2),
                    'recommendation_at' => $this->timestampFor($caseStatus, ['recommendation', 'decision', 'decided', 'recovery', 'monitoring', 'closed'], $daysAgo - 4),
                    'decision_at' => $this->timestampFor($caseStatus, ['decision', 'decided', 'recovery', 'monitoring', 'closed'], $daysAgo - 6),
                    'closed_at' => $caseStatus === 'closed' ? DemoSeed::date(1) : null,
                    'escalated_at' => null,
                    'escalation_type' => null,
                ]
            );

            $admin = DemoSeed::user(DemoSeed::campusEmail('admin', $universityCode));
            for ($i = 1; $i <= 2; $i++) {
                $satgas = DemoSeed::user(DemoSeed::campusEmail('satgas', $universityCode, $i));
                CaseAssignment::query()->updateOrCreate(
                    [
                        'case_id' => $case->id,
                        'satgas_id' => $satgas->id,
                    ],
                    [
                        'assigned_by' => $admin->id,
                        'is_lead' => false,
                        'is_active' => true,
                        'assigned_at' => $case->forwarded_at,
                        'unassigned_at' => null,
                    ]
                );
            }
        }
    }

    /**
     * @param  list<string>  $statuses
     */
    private function timestampFor(string $currentStatus, array $statuses, int $daysAgo): mixed
    {
        return in_array($currentStatus, $statuses, true)
            ? DemoSeed::date(max(1, $daysAgo))
            : null;
    }
}
