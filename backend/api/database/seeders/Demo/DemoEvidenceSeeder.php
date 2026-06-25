<?php

namespace Database\Seeders\Demo;

use App\Enums\EvidenceClassification;
use App\Enums\EvidenceCustodyEventType;
use App\Enums\EvidenceStatus;
use App\Models\Evidence;
use App\Models\EvidenceCustodyEvent;
use App\Models\EvidenceStatusHistory;
use App\Models\Investigation;
use Illuminate\Database\Seeder;

class DemoEvidenceSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            EvidenceStatus::Registered->value,
            EvidenceStatus::UnderReview->value,
            EvidenceStatus::Verified->value,
            EvidenceStatus::Rejected->value,
            EvidenceStatus::Archived->value,
        ];

        Investigation::query()->with('case.activeAssignments')->orderBy('id')->get()->each(function (Investigation $investigation, int $index) use ($statuses): void {
            $actor = $investigation->case->activeAssignments->firstWhere('is_lead', true)?->satgas
                ?? $investigation->case->activeAssignments->first()?->satgas;

            if (! $actor) {
                return;
            }

            for ($i = 1; $i <= 2; $i++) {
                $status = $statuses[($index + $i) % count($statuses)];
                $evidence = Evidence::query()->updateOrCreate(
                    [
                        'investigation_id' => $investigation->id,
                        'title' => sprintf('Metadata Bukti Demo %s-%d', $investigation->case->case_number, $i),
                    ],
                    [
                        'evidence_type_code' => DemoSeed::masterCode('evidence_types', ($index + $i) % 4),
                        'submitted_by' => $actor->id,
                        'description' => 'Metadata bukti demo tanpa file fisik. Digunakan untuk validasi daftar, status, dan custody.',
                        'source' => 'Catatan demo internal',
                        'collected_at' => DemoSeed::date(max(1, 7 - $i)),
                        'classification' => $i === 1 ? EvidenceClassification::Confidential->value : EvidenceClassification::Internal->value,
                        'status' => $status,
                        'original_filename' => "demo-evidence-{$investigation->id}-{$i}.pdf",
                        'mime_type' => 'application/pdf',
                        'file_size' => 128000 + ($index * 1000) + $i,
                        'checksum_sha256' => hash('sha256', "demo-evidence-{$investigation->id}-{$i}"),
                    ]
                );

                EvidenceStatusHistory::query()->updateOrCreate(
                    [
                        'evidence_id' => $evidence->id,
                        'to_status' => $status,
                    ],
                    [
                        'from_status' => null,
                        'changed_by' => $actor->id,
                        'changed_at' => DemoSeed::date(5),
                    ]
                );

                foreach ([EvidenceCustodyEventType::Registered->value, EvidenceCustodyEventType::Reviewed->value] as $eventType) {
                    EvidenceCustodyEvent::query()->updateOrCreate(
                        [
                            'evidence_id' => $evidence->id,
                            'event_type' => $eventType,
                        ],
                        [
                            'actor_id' => $actor->id,
                            'event_at' => DemoSeed::date($eventType === EvidenceCustodyEventType::Registered->value ? 6 : 4),
                            'details' => 'Custody event demo untuk validasi chain-of-custody metadata.',
                        ]
                    );
                }
            }
        });
    }
}
