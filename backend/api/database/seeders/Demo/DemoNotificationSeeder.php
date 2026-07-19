<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoNotificationSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->whereHas('role')->get()->each(function (User $user): void {
            $roleCode = $user->role?->code;
            $items = match ($roleCode) {
                'super_admin' => [],
                'admin' => [
                    ['NOTIF-14', 'recommendation_submitted_for_review', 'Ada rekomendasi demo menunggu peninjauan Admin Kampus.'],
                    ['NOTIF-01', 'report_new', 'Laporan demo baru masuk untuk kampus Anda.'],
                    ['NOTIF-13', 'case_status_changed', 'Status kasus demo berubah.'],
                ],
                'satgas_ppks' => [
                    ['NOTIF-12', 'case_assigned', 'Kasus demo ditugaskan kepada Anda.'],
                    ['NOTIF-18', 'decision_created', 'Keputusan demo tersedia untuk kasus yang Anda tangani.'],
                    ['NOTIF-15', 'decision_finalized', 'Keputusan demo telah difinalisasi.'],
                ],
                'reporter' => [
                    ['NOTIF-02', 'report_confirmed', 'Laporan demo Anda telah diterima.'],
                    ['NOTIF-03', 'case_status_changed', 'Status laporan demo Anda diperbarui.'],
                ],
                default => [],
            };

            foreach ($items as $index => [$typeCode, $event, $message]) {
                DB::table('notifications')->updateOrInsert(
                    ['id' => DemoSeed::deterministicUuid("notification:{$user->email}:{$event}")],
                    [
                        'type' => 'App\\Notifications\\WorkflowDatabaseNotification',
                        'notifiable_type' => User::class,
                        'notifiable_id' => $user->id,
                        'data' => json_encode([
                            'notification_type_code' => $typeCode,
                            'event' => $event,
                            'title' => 'Demo Notification',
                            'message' => $message,
                            'case_number' => $index === 0 ? 'CASE-DEMO-202606-0107' : 'CASE-DEMO-202606-0109',
                        ], JSON_THROW_ON_ERROR),
                        'read_at' => $index === 0 ? null : DemoSeed::date(1),
                        'created_at' => DemoSeed::date($index + 1),
                        'updated_at' => DemoSeed::date($index + 1),
                    ]
                );
            }
        });
    }
}
