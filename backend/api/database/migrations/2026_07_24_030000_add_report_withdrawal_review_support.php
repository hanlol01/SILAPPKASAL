<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_withdrawals', function (Blueprint $table): void {
            $table->index(
                ['status', 'submitted_at', 'id'],
                'report_withdrawals_review_queue_idx',
            );
            $table->unique('supersedes_id', 'report_withdrawals_supersedes_unique');
        });

        $this->upsertNotificationType(
            'NOTIF-28',
            'Permohonan pencabutan disetujui',
            'Hasil persetujuan permohonan pencabutan untuk Pelapor.',
            'report.formal_withdrawal.approved',
            28,
        );
        $this->upsertNotificationType(
            'NOTIF-29',
            'Permohonan pencabutan ditolak',
            'Hasil penolakan permohonan pencabutan untuk Pelapor tanpa alasan sensitif.',
            'report.formal_withdrawal.rejected',
            29,
        );
    }

    public function down(): void
    {
        DB::table('notification_types')->whereIn('code', ['NOTIF-28', 'NOTIF-29'])->delete();

        Schema::table('report_withdrawals', function (Blueprint $table): void {
            $table->dropIndex('report_withdrawals_review_queue_idx');
            $table->dropUnique('report_withdrawals_supersedes_unique');
        });
    }

    private function upsertNotificationType(
        string $code,
        string $name,
        string $description,
        string $templateKey,
        int $sortOrder,
    ): void {
        DB::table('notification_types')->insertOrIgnore([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'channel' => 'in_app',
            'template_key' => $templateKey,
            'recipient_role' => 'Reporter',
            'classification' => 'mvp_extended',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('notification_types')->where('code', $code)->update([
            'name' => $name,
            'description' => $description,
            'channel' => 'in_app',
            'template_key' => $templateKey,
            'recipient_role' => 'Reporter',
            'classification' => 'mvp_extended',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'updated_at' => now(),
        ]);
    }
};
