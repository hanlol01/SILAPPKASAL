<?php

namespace Database\Seeders\Demo;

use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseRecord;
use App\Models\Recommendation;
use App\Models\RecommendationStatusHistory;
use Illuminate\Database\Seeder;

class DemoRecommendationSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $statuses = [
        'CASE-DEMO-202606-0103' => RecommendationStatusEnum::Drafting->value,
        'CASE-DEMO-202606-0104' => RecommendationStatusEnum::SubmittedForReview->value,
        'CASE-DEMO-202606-0105' => RecommendationStatusEnum::SubmittedForReview->value,
        'CASE-DEMO-202606-0106' => RecommendationStatusEnum::Accepted->value,
        'CASE-DEMO-202606-0107' => RecommendationStatusEnum::PartiallyAccepted->value,
        'CASE-DEMO-202606-0108' => RecommendationStatusEnum::Rejected->value,
        'CASE-DEMO-202606-0109' => RecommendationStatusEnum::Accepted->value,
    ];

    public function run(): void
    {
        foreach ($this->statuses as $caseNumber => $statusName) {
            $case = CaseRecord::query()->where('case_number', $caseNumber)->firstOrFail();
            $investigation = $case->investigation()->firstOrFail();
            $author = $case->activeAssignments()->where('is_lead', true)->firstOrFail()->satgas;
            $status = DemoSeed::recommendationStatus($statusName);

            $recommendation = Recommendation::query()->updateOrCreate(
                ['case_id' => $case->id],
                [
                    'investigation_id' => $investigation->id,
                    'author_id' => $author->id,
                    'status_code' => $status->code,
                    'conclusion' => 'Kesimpulan rekomendasi demo berdasarkan investigasi fiktif dan metadata kasus.',
                    'recommended_actions' => 'Rekomendasi demo: pembatasan interaksi, pendampingan pelapor, dan tindak lanjut kelembagaan sesuai prosedur.',
                    'sanction_recommendation' => 'Sanksi demo disesuaikan dengan tingkat risiko dan bukti yang tersedia.',
                    'recovery_recommendation' => 'Pendampingan psikologis dan akademik direkomendasikan untuk skenario demo.',
                    'prevention_recommendation' => 'Sosialisasi ulang kanal pelaporan dan kode etik di unit terkait.',
                    'submitted_at' => $statusName === RecommendationStatusEnum::Drafting->value ? null : DemoSeed::date(5),
                ]
            );

            RecommendationStatusHistory::query()->updateOrCreate(
                [
                    'recommendation_id' => $recommendation->id,
                    'to_status_code' => $status->code,
                ],
                [
                    'from_status_code' => null,
                    'changed_by' => $author->id,
                    'changed_at' => DemoSeed::date(5),
                ]
            );
        }
    }
}
