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
            $table->string('registration_number_snapshot', 64)->nullable()->after('requester_id');
            $table->text('requester_display_name_snapshot')->nullable()->after('registration_number_snapshot');
            $table->timestamp('draft_document_viewed_at')->nullable()->after('submitted_at');
        });

        DB::transaction(function (): void {
            $this->upsertNotificationType(
                'NOTIF-26',
                'Permohonan pencabutan menunggu verifikasi',
                'Pengaduan mengajukan pencabutan dan menunggu verifikasi Admin Kampus.',
                'report.formal_withdrawal.submitted',
                26,
            );
            $this->upsertNotificationType(
                'NOTIF-27',
                'Permohonan pencabutan dibatalkan Pelapor',
                'Permohonan pencabutan yang menunggu verifikasi dibatalkan oleh Pelapor.',
                'report.formal_withdrawal.cancelled',
                27,
            );
        });
    }

    public function down(): void
    {
        DB::table('notification_types')
            ->whereIn('code', ['NOTIF-26', 'NOTIF-27'])
            ->delete();

        Schema::table('report_withdrawals', function (Blueprint $table): void {
            $table->dropColumn([
                'registration_number_snapshot',
                'requester_display_name_snapshot',
                'draft_document_viewed_at',
            ]);
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
            'recipient_role' => 'Admin',
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
            'recipient_role' => 'Admin',
            'classification' => 'mvp_extended',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'updated_at' => now(),
        ]);
    }
};
