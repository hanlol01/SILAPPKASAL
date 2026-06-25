<?php

namespace Database\Seeders\Foundation;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string}>
     */
    private array $roles = [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Pengelola seluruh sistem.',
        ],
        'admin' => [
            'name' => 'Admin',
            'description' => 'Memverifikasi laporan masuk, routing, user management, dan statistik.',
        ],
        'satgas_ppks' => [
            'name' => 'Satgas PPKS',
            'description' => 'Menangani kasus yang ditugaskan.',
        ],
        'reporter' => [
            'name' => 'Pelapor',
            'description' => 'Membuat laporan dan memantau status kasus sendiri.',
        ],
    ];

    /**
     * @var array<string, array{name: string, description: string, module: string}>
     */
    private array $permissions = [
        'system.configure' => ['name' => 'Configure System', 'description' => 'Mengubah konfigurasi sistem', 'module' => 'Sistem'],
        'system.audit_log.view' => ['name' => 'View Audit Log', 'description' => 'Melihat audit log', 'module' => 'Sistem'],
        'system.break_glass_access' => ['name' => 'Break Glass Access', 'description' => 'Akses darurat ke data sensitif kasus', 'module' => 'Sistem'],
        'privacy.request_break_glass' => ['name' => 'Request Break-Glass', 'description' => 'Mengajukan permintaan akses break-glass', 'module' => 'Privasi'],
        'privacy.approve_break_glass' => ['name' => 'Approve Break-Glass', 'description' => 'Menyetujui atau menolak permintaan break-glass', 'module' => 'Privasi'],
        'privacy.reveal_anonymous_identity' => ['name' => 'Reveal Anonymous Identity', 'description' => 'Membuka identitas pelapor anonim melalui break-glass', 'module' => 'Privasi'],
        'users.create' => ['name' => 'Create Users', 'description' => 'Membuat akun pengguna', 'module' => 'User'],
        'users.read' => ['name' => 'Read Users', 'description' => 'Melihat daftar pengguna', 'module' => 'User'],
        'users.update' => ['name' => 'Update Users', 'description' => 'Mengedit akun pengguna', 'module' => 'User'],
        'users.deactivate' => ['name' => 'Deactivate Users', 'description' => 'Menonaktifkan akun pengguna', 'module' => 'User'],
        'users.assign_role' => ['name' => 'Assign User Role', 'description' => 'Menetapkan role ke pengguna', 'module' => 'User'],
        'reports.create' => ['name' => 'Create Reports', 'description' => 'Membuat laporan baru', 'module' => 'Laporan'],
        'reports.read.own' => ['name' => 'Read Own Reports', 'description' => 'Melihat laporan sendiri', 'module' => 'Laporan'],
        'reports.read.all' => ['name' => 'Read All Reports', 'description' => 'Melihat semua laporan masuk', 'module' => 'Laporan'],
        'reports.verify' => ['name' => 'Verify Reports', 'description' => 'Memverifikasi laporan', 'module' => 'Laporan'],
        'reports.reject' => ['name' => 'Reject Reports', 'description' => 'Menolak laporan', 'module' => 'Laporan'],
        'reports.forward' => ['name' => 'Forward Reports', 'description' => 'Meneruskan laporan ke Satgas', 'module' => 'Laporan'],
        'reports.request_info' => ['name' => 'Request Report Info', 'description' => 'Meminta info tambahan dari pelapor', 'module' => 'Laporan'],
        'cases.read.metadata' => ['name' => 'Read Case Metadata', 'description' => 'Melihat metadata kasus tanpa data sensitif', 'module' => 'Kasus'],
        'cases.read.assigned' => ['name' => 'Read Assigned Cases', 'description' => 'Melihat data lengkap kasus yang ditugaskan', 'module' => 'Kasus'],
        'cases.read.all' => ['name' => 'Read All Cases', 'description' => 'Melihat metadata semua kasus', 'module' => 'Kasus'],
        'cases.assess_risk' => ['name' => 'Assess Case Risk', 'description' => 'Mengisi asesmen risiko', 'module' => 'Kasus'],
        'cases.investigate' => ['name' => 'Investigate Cases', 'description' => 'Mencatat aktivitas investigasi', 'module' => 'Kasus'],
        'cases.recommend' => ['name' => 'Recommend Case Action', 'description' => 'Menyusun rekomendasi', 'module' => 'Kasus'],
        'cases.record_decision' => ['name' => 'Record Case Decision', 'description' => 'Mencatat keputusan institusi', 'module' => 'Kasus'],
        'cases.monitor' => ['name' => 'Monitor Cases', 'description' => 'Mencatat monitoring pasca kasus', 'module' => 'Kasus'],
        'cases.close' => ['name' => 'Close Cases', 'description' => 'Menutup kasus', 'module' => 'Kasus'],
        'cases.assign_satgas' => ['name' => 'Assign Satgas', 'description' => 'Menugaskan Satgas ke kasus', 'module' => 'Kasus'],
        'cases.escalate' => ['name' => 'Escalate Cases', 'description' => 'Mengeskalasi kasus', 'module' => 'Kasus'],
        'messages.send' => ['name' => 'Send Messages', 'description' => 'Mengirim pesan internal', 'module' => 'Komunikasi'],
        'messages.read.case' => ['name' => 'Read Case Messages', 'description' => 'Membaca pesan dalam konteks kasus', 'module' => 'Komunikasi'],
        'dashboard.admin' => ['name' => 'Admin Dashboard', 'description' => 'Melihat dashboard admin', 'module' => 'Dashboard'],
        'dashboard.satgas' => ['name' => 'Satgas Dashboard', 'description' => 'Melihat dashboard satgas', 'module' => 'Dashboard'],
        'statistics.view' => ['name' => 'View Statistics', 'description' => 'Melihat statistik', 'module' => 'Dashboard'],
        'statistics.export' => ['name' => 'Export Statistics', 'description' => 'Export statistik', 'module' => 'Dashboard'],
        'evidence.upload' => ['name' => 'Upload Evidence', 'description' => 'Mengupload bukti', 'module' => 'Bukti'],
        'evidence.view.case' => ['name' => 'View Case Evidence', 'description' => 'Melihat bukti dalam konteks kasus', 'module' => 'Bukti'],
        'evidence.download' => ['name' => 'Download Evidence', 'description' => 'Mengunduh bukti', 'module' => 'Bukti'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $rolePermissions = [
        'super_admin' => [
            'system.configure',
            'system.audit_log.view',
            'system.break_glass_access',
            'users.create',
            'users.read',
            'users.update',
            'users.deactivate',
            'users.assign_role',
            'reports.read.all',
            'reports.verify',
            'reports.reject',
            'reports.forward',
            'reports.request_info',
            'cases.read.metadata',
            'cases.read.all',
            'cases.assign_satgas',
            'cases.record_decision',
            'cases.monitor',
            'dashboard.admin',
            'statistics.view',
            'statistics.export',
            'privacy.request_break_glass',
            'privacy.approve_break_glass',
            'privacy.reveal_anonymous_identity',
        ],
        'admin' => [
            'system.audit_log.view',
            'users.create',
            'users.read',
            'users.update',
            'users.deactivate',
            'reports.read.all',
            'reports.verify',
            'reports.reject',
            'reports.forward',
            'reports.request_info',
            'cases.read.metadata',
            'cases.assign_satgas',
            'cases.record_decision',
            'cases.monitor',
            'dashboard.admin',
            'statistics.view',
            'statistics.export',
            'privacy.request_break_glass',
        ],
        'satgas_ppks' => [
            'cases.read.assigned',
            'cases.assess_risk',
            'cases.investigate',
            'cases.recommend',
            'cases.monitor',
            'cases.close',
            'cases.escalate',
            'messages.send',
            'messages.read.case',
            'dashboard.satgas',
            'statistics.view',
            'evidence.upload',
            'evidence.view.case',
            'evidence.download',
        ],
        'reporter' => [
            'reports.create',
            'reports.read.own',
            'messages.send',
            'messages.read.case',
            'evidence.upload',
            'evidence.view.case',
        ],
    ];

    public function run(): void
    {
        $roles = collect($this->roles)->mapWithKeys(function (array $attributes, string $code): array {
            return [
                $code => Role::query()->updateOrCreate(
                    ['code' => $code],
                    [...$attributes, 'is_active' => true]
                ),
            ];
        });

        $permissions = collect($this->permissions)->mapWithKeys(function (array $attributes, string $code): array {
            return [
                $code => Permission::query()->updateOrCreate(
                    ['code' => $code],
                    $attributes
                ),
            ];
        });

        foreach ($this->rolePermissions as $roleCode => $permissionCodes) {
            $roles[$roleCode]->permissions()->sync(
                $permissions->only($permissionCodes)->pluck('id')->all()
            );
        }
    }
}
