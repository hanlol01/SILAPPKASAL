<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{name: string, description: string}> */
    private array $permissions = [
        'reports.cancel.own' => [
            'name' => 'Cancel Own Complaint',
            'description' => 'Membatalkan pengaduan sendiri sebelum penanganan dimulai',
        ],
        'reports.withdraw.own' => [
            'name' => 'Withdraw Own Complaint',
            'description' => 'Mengajukan pencabutan formal pengaduan sendiri',
        ],
        'reports.withdraw.review.own_campus' => [
            'name' => 'Review Campus Complaint Withdrawals',
            'description' => 'Meninjau pencabutan pengaduan pada kampus sendiri',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ($this->permissions as $code => $attributes) {
                DB::table('permissions')->insertOrIgnore([
                    'code' => $code,
                    ...$attributes,
                    'module' => 'Pengaduan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('permissions')->where('code', $code)->update([
                    ...$attributes,
                    'module' => 'Pengaduan',
                    'updated_at' => now(),
                ]);
            }

            $this->assign('reporter', ['reports.cancel.own', 'reports.withdraw.own']);
            $this->assign('admin', ['reports.withdraw.review.own_campus']);

            DB::table('case_statuses')->insertOrIgnore([
                'code' => 'CSTS-16',
                'name' => 'withdrawn',
                'description' => 'Kasus dihentikan setelah pencabutan pengaduan disetujui.',
                'workflow_stage' => 0,
                'stage_name' => 'Dihentikan',
                'is_terminal' => true,
                'responsible_role' => 'system',
                'valid_transitions' => json_encode([]),
                'is_active' => true,
                'sort_order' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('notification_types')->insertOrIgnore([
                'code' => 'NOTIF-25',
                'name' => 'Pembatalan langsung pengaduan',
                'description' => 'Admin Kampus menerima informasi pengaduan yang dibatalkan Pelapor.',
                'channel' => 'in_app',
                'template_key' => 'report.direct_cancellation',
                'recipient_role' => 'Admin',
                'classification' => 'mvp_extended',
                'is_active' => true,
                'sort_order' => 25,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')
                ->whereIn('code', array_keys($this->permissions))
                ->pluck('id');

            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            DB::table('notification_types')->where('code', 'NOTIF-25')->delete();
            DB::table('case_statuses')->where('code', 'CSTS-16')->delete();
        });
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function assign(string $roleCode, array $permissionCodes): void
    {
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

        if ($roleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', $permissionCodes)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }
    }
};
