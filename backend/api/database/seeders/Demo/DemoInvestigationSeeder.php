<?php

namespace Database\Seeders\Demo;

use App\Enums\InvestigationActivityType;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Models\CaseRecord;
use App\Models\Investigation;
use App\Models\InvestigationActivity;
use Illuminate\Database\Seeder;

class DemoInvestigationSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $statuses = [
        'CASE-DEMO-202606-0102' => InvestigationStatusEnum::VictimInterview->value,
        'CASE-DEMO-202606-0103' => InvestigationStatusEnum::Completed->value,
        'CASE-DEMO-202606-0104' => InvestigationStatusEnum::Completed->value,
        'CASE-DEMO-202606-0105' => InvestigationStatusEnum::Completed->value,
        'CASE-DEMO-202606-0106' => InvestigationStatusEnum::Completed->value,
        'CASE-DEMO-202606-0107' => InvestigationStatusEnum::Completed->value,
        'CASE-DEMO-202606-0108' => InvestigationStatusEnum::Completed->value,
        'CASE-DEMO-202606-0109' => InvestigationStatusEnum::Completed->value,
    ];

    public function run(): void
    {
        foreach ($this->statuses as $caseNumber => $statusName) {
            $case = CaseRecord::query()->where('case_number', $caseNumber)->firstOrFail();
            $lead = $case->activeAssignments()->where('is_lead', true)->firstOrFail()->satgas;
            $status = DemoSeed::investigationStatus($statusName);
            $completed = $statusName === InvestigationStatusEnum::Completed->value;

            $investigation = Investigation::query()->updateOrCreate(
                ['case_id' => $case->id],
                [
                    'lead_investigator_id' => $lead->id,
                    'status_code' => $status->code,
                    'plan_summary' => 'Rencana investigasi demo mencakup penelaahan kronologi, review dokumen pendukung, dan wawancara pihak terkait dengan pendekatan aman serta berperspektif korban.',
                    'findings' => $completed ? 'Temuan demo menunjukkan adanya konsistensi informasi awal dan kebutuhan tindak lanjut kelembagaan. Data ini fiktif untuk simulasi workflow.' : null,
                    'conclusion' => $completed ? 'Investigasi demo selesai dan dapat dilanjutkan ke penyusunan rekomendasi.' : null,
                    'started_at' => $case->investigation_started_at ?? DemoSeed::date(9),
                    'completed_at' => $completed ? DemoSeed::date(6) : null,
                ]
            );

            $this->activity($investigation, $lead->id, InvestigationActivityType::CaseReview->value, 8, 'Review awal pengaduan dan metadata kasus.');
            $this->activity($investigation, $lead->id, InvestigationActivityType::DocumentReview->value, 7, 'Penelaahan dokumen dan tangkapan layar yang dicatat sebagai metadata.');

            if ($completed) {
                $this->activity($investigation, $lead->id, InvestigationActivityType::VictimInterview->value, 6, 'Wawancara demo dilakukan dengan catatan ringkas yang tidak memuat identitas nyata.');
                $this->activity($investigation, $lead->id, InvestigationActivityType::TimelineReview->value, 5, 'Penyusunan timeline demo untuk validasi tampilan workflow.');
            }
        }
    }

    private function activity(Investigation $investigation, int $investigatorId, string $type, int $daysAgo, string $description): void
    {
        InvestigationActivity::query()->updateOrCreate(
            [
                'investigation_id' => $investigation->id,
                'activity_type' => $type,
                'activity_date' => DemoSeed::date($daysAgo)->toDateString(),
            ],
            [
                'investigator_id' => $investigatorId,
                'description' => $description,
                'findings' => 'Catatan temuan demo bersifat fiktif dan aman untuk pelatihan.',
                'notes' => 'Dibuat otomatis oleh Demo Dataset V2.',
            ]
        );
    }
}
