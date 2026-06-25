<?php

namespace Database\Seeders\Demo;

use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\DecisionStatusHistory;
use Illuminate\Database\Seeder;

class DemoDecisionSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $outcomes = [
        'CASE-DEMO-202606-0105' => DecisionOutcome::Deferred->value,
        'CASE-DEMO-202606-0106' => DecisionOutcome::Accepted->value,
        'CASE-DEMO-202606-0107' => DecisionOutcome::PartiallyAccepted->value,
        'CASE-DEMO-202606-0108' => DecisionOutcome::Rejected->value,
        'CASE-DEMO-202606-0109' => DecisionOutcome::Accepted->value,
    ];

    public function run(): void
    {
        foreach ($this->outcomes as $caseNumber => $outcome) {
            $case = CaseRecord::query()->where('case_number', $caseNumber)->firstOrFail();
            $recommendation = $case->recommendation()->firstOrFail();
            $report = $case->report()->firstOrFail();
            $recorder = DemoSeed::user('superadmin@silappkasal.test');
            $status = DemoSeed::decisionStatus(DecisionStatusEnum::Finalized->value);

            $decision = Decision::query()->updateOrCreate(
                ['recommendation_id' => $recommendation->id],
                [
                    'recorder_id' => $recorder->id,
                    'status_code' => $status->code,
                    'outcome_code' => $outcome,
                    'decision_number' => 'DEC-'.$case->case_number,
                    'decision_date' => DemoSeed::date(3)->toDateString(),
                    'decision_summary' => 'Ringkasan keputusan demo untuk validasi workflow keputusan.',
                    'decision_content' => 'Konten keputusan demo bersifat fiktif, tidak memuat identitas nyata, dan digunakan untuk UAT.',
                    'recorded_at' => DemoSeed::date(3),
                    'finalized_at' => DemoSeed::date(2),
                ]
            );

            DecisionStatusHistory::query()->updateOrCreate(
                [
                    'decision_id' => $decision->id,
                    'to_status_code' => $status->code,
                ],
                [
                    'from_status_code' => null,
                    'changed_by' => $recorder->id,
                    'changed_at' => DemoSeed::date(2),
                ]
            );

            $case->forceFill([
                'decision_at' => DemoSeed::date(3),
            ])->save();

            $report->forceFill(['reviewed_at' => $report->reviewed_at ?? DemoSeed::date(3)])->save();
        }
    }
}
