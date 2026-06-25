<?php

namespace Database\Seeders\Demo;

use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Models\CaseRecord;
use App\Models\Recovery;
use App\Models\RecoveryMonitoring;
use App\Models\RecoveryStatusHistory;
use Illuminate\Database\Seeder;

class DemoRecoverySeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $statuses = [
        'CASE-DEMO-202606-0107' => RecoveryStatusEnum::Planned->value,
        'CASE-DEMO-202606-0108' => RecoveryStatusEnum::Ongoing->value,
        'CASE-DEMO-202606-0109' => RecoveryStatusEnum::Completed->value,
    ];

    public function run(): void
    {
        foreach ($this->statuses as $caseNumber => $statusName) {
            $case = CaseRecord::query()->where('case_number', $caseNumber)->firstOrFail();
            $decision = $case->recommendation()->firstOrFail()->decision()->firstOrFail();
            $admin = DemoSeed::user('superadmin@silappkasal.test');
            $status = DemoSeed::recoveryStatus($statusName);
            $recoveryType = DemoSeed::masterCode('recovery_types', crc32($caseNumber) % 4);

            $recovery = Recovery::query()->updateOrCreate(
                [
                    'decision_id' => $decision->id,
                    'recovery_type_code' => $recoveryType,
                ],
                [
                    'status_code' => $status->code,
                    'created_by' => $admin->id,
                    'recovery_plan' => 'Rencana pemulihan demo mencakup dukungan psikologis, akademik, dan monitoring berkala.',
                    'support_needs' => 'Kebutuhan dukungan demo dicatat untuk validasi tampilan dan workflow.',
                    'notes' => 'Catatan pemulihan demo tidak berisi data korban nyata.',
                    'started_at' => $statusName === RecoveryStatusEnum::Planned->value ? null : DemoSeed::date(2),
                    'completed_at' => $statusName === RecoveryStatusEnum::Completed->value ? DemoSeed::date(1) : null,
                    'discontinued_at' => $statusName === RecoveryStatusEnum::Discontinued->value ? DemoSeed::date(1) : null,
                ]
            );

            RecoveryStatusHistory::query()->updateOrCreate(
                [
                    'recovery_id' => $recovery->id,
                    'to_status_code' => $status->code,
                ],
                [
                    'from_status_code' => null,
                    'changed_by' => $admin->id,
                    'changed_at' => DemoSeed::date(2),
                ]
            );

            if ($statusName !== RecoveryStatusEnum::Planned->value) {
                $monitor = $case->activeAssignments()->where('is_lead', true)->firstOrFail()->satgas;
                RecoveryMonitoring::query()->updateOrCreate(
                    [
                        'recovery_id' => $recovery->id,
                        'monitoring_date' => DemoSeed::date(1)->toDateString(),
                    ],
                    [
                        'monitor_id' => $monitor->id,
                        'status' => 'recorded',
                        'condition_summary' => 'Ringkasan kondisi demo menunjukkan pendampingan berjalan sesuai rencana.',
                        'follow_up_plan' => 'Tindak lanjut demo berupa monitoring berkala dan koordinasi akademik.',
                        'notes' => 'Monitoring demo bersifat fiktif.',
                    ]
                );
            }
        }
    }
}
