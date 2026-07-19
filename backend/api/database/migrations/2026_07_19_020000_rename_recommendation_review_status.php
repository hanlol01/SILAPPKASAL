<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rename('submitted_to_leader', 'submitted_for_review', 'Rekomendasi diajukan untuk peninjauan Admin Kampus.');
        $this->updateDecidedDescription('Keputusan institusi telah diterbitkan.');
        $this->renameNotification(
            'Rekomendasi diajukan untuk peninjauan',
            'Admin Kampus',
            'recommendation.submitted_for_review',
        );
    }

    public function down(): void
    {
        // Application dual-read support makes this rollback safe for one release.
        $this->rename('submitted_for_review', 'submitted_to_leader', 'Rekomendasi diajukan ke pimpinan PT.');
        $this->updateDecidedDescription('Keputusan pimpinan telah diterbitkan.');
        $this->renameNotification(
            'Rekomendasi dikirim ke pimpinan',
            'Super Admin',
            'recommendation.submitted_to_leader',
        );
    }

    private function rename(string $from, string $to, string $description): void
    {
        DB::transaction(function () use ($from, $to, $description): void {
            DB::table('recommendation_statuses')
                ->where('code', 'RECS-03')
                ->update([
                    'name' => $to,
                    'description' => $description,
                    'updated_at' => now(),
                ]);

            DB::table('recommendation_statuses')
                ->orderBy('code')
                ->get(['code', 'valid_transitions'])
                ->each(function (object $row) use ($from, $to): void {
                    $transitions = json_decode((string) $row->valid_transitions, true);

                    if (! is_array($transitions) || ! in_array($from, $transitions, true)) {
                        return;
                    }

                    DB::table('recommendation_statuses')
                        ->where('code', $row->code)
                        ->update([
                            'valid_transitions' => json_encode(array_values(array_map(
                                static fn (mixed $value): mixed => $value === $from ? $to : $value,
                                $transitions,
                            )), JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                });
        });
    }

    private function renameNotification(string $name, string $recipient, string $event): void
    {
        DB::table('notification_types')
            ->where('code', 'NOTIF-14')
            ->update([
                'name' => $name,
                'description' => $name,
                'recipient_role' => $recipient,
                'template_key' => $event,
                'updated_at' => now(),
            ]);
    }

    private function updateDecidedDescription(string $description): void
    {
        DB::table('case_statuses')
            ->where('code', 'CSTS-11')
            ->update([
                'description' => $description,
                'updated_at' => now(),
            ]);
    }
};
