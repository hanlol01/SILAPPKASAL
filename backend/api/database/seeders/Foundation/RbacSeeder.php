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
        'system.audit_log.oversight' => ['name' => 'View Operational Oversight', 'description' => 'Melihat antrean perhatian operasional', 'module' => 'Sistem'],
        'system.audit_log.export' => ['name' => 'Export Audit Log', 'description' => 'Mengekspor riwayat audit yang aman', 'module' => 'Sistem'],
        'system.break_glass_access' => ['name' => 'Legacy Break Glass Access', 'description' => 'Kode izin operasional lama yang tidak ditugaskan pada alur R2', 'module' => 'Sistem'],
        'privacy.request_break_glass' => ['name' => 'Request Emergency Access', 'description' => 'Satgas yang ditugaskan mengajukan akses darurat untuk kasus anonim', 'module' => 'Privasi'],
        'privacy.approve_break_glass' => ['name' => 'Review Emergency Access', 'description' => 'Admin Kampus meninjau, menyetujui, menolak, atau mencabut akses darurat', 'module' => 'Privasi'],
        'privacy.reveal_anonymous_identity' => ['name' => 'Reveal Anonymous Identity', 'description' => 'Satgas pemohon membuka identitas melalui grant aktif miliknya', 'module' => 'Privasi'],
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
        'cases.read.sensitive_oversight' => ['name' => 'Read Sensitive Case Oversight', 'description' => 'Membaca data sensitif lintas kampus dalam mode pengawasan', 'module' => 'Kasus'],
        'cases.assess_risk' => ['name' => 'Assess Case Risk', 'description' => 'Mengisi asesmen risiko', 'module' => 'Kasus'],
        'cases.investigate' => ['name' => 'Investigate Cases', 'description' => 'Mencatat aktivitas investigasi', 'module' => 'Kasus'],
        'cases.recommend' => ['name' => 'Recommend Case Action', 'description' => 'Menyusun rekomendasi', 'module' => 'Kasus'],
        'cases.review_recommendation' => ['name' => 'Review Case Recommendation', 'description' => 'Meninjau rekomendasi sebagai Admin Kampus', 'module' => 'Kasus'],
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
        'reporter_evidence.read.own' => ['name' => 'Read Own Reporter Evidence', 'description' => 'Melihat lampiran bukti pada laporan sendiri', 'module' => 'Bukti Pelapor'],
        'reporter_evidence.upload.own' => ['name' => 'Upload Own Reporter Evidence', 'description' => 'Mengunggah lampiran bukti pada laporan sendiri', 'module' => 'Bukti Pelapor'],
        'reporter_evidence.download.own' => ['name' => 'Download Own Reporter Evidence', 'description' => 'Mengunduh lampiran bukti pada laporan sendiri', 'module' => 'Bukti Pelapor'],
        'reporter_evidence.read.assigned' => ['name' => 'Read Assigned Reporter Evidence', 'description' => 'Melihat lampiran bukti pelapor pada kasus yang ditugaskan', 'module' => 'Bukti Pelapor'],
        'reporter_evidence.download.assigned' => ['name' => 'Download Assigned Reporter Evidence', 'description' => 'Mengunduh lampiran bukti pelapor pada kasus yang ditugaskan', 'module' => 'Bukti Pelapor'],
        'content.create.campus' => ['name' => 'Create Campus Content', 'description' => 'Membuat draf konten untuk kampus sendiri', 'module' => 'Konten'],
        'content.update.own_campus' => ['name' => 'Update Own Campus Content', 'description' => 'Memperbarui draf konten kampus sendiri', 'module' => 'Konten'],
        'content.submit.own_campus' => ['name' => 'Submit Own Campus Content', 'description' => 'Mengajukan konten kampus sendiri untuk peninjauan', 'module' => 'Konten'],
        'content.read.management.own_campus' => ['name' => 'Read Own Campus Content Management', 'description' => 'Melihat data pengelolaan konten kampus sendiri', 'module' => 'Konten'],
        'content.attachment.manage.own_campus' => ['name' => 'Manage Own Campus Content Attachments', 'description' => 'Mengelola lampiran draf konten kampus sendiri', 'module' => 'Konten'],
        'content.review' => ['name' => 'Review Content', 'description' => 'Meninjau konten kampus', 'module' => 'Konten'],
        'content.publish.global' => ['name' => 'Publish Global Content', 'description' => 'Membuat dan menerbitkan konten global', 'module' => 'Konten'],
        'content.archive' => ['name' => 'Archive Content', 'description' => 'Mengarsipkan konten yang telah diterbitkan', 'module' => 'Konten'],
        'content.feature.manage' => ['name' => 'Manage Featured Content', 'description' => 'Mengelola penempatan konten unggulan', 'module' => 'Konten'],
        'content.category.govern' => ['name' => 'Govern Content Categories', 'description' => 'Mengelola tata kelola kategori konten', 'module' => 'Konten'],
        'content.read.management.all' => ['name' => 'Read All Content Management', 'description' => 'Melihat seluruh data pengelolaan konten', 'module' => 'Konten'],
        'content.read.published' => ['name' => 'Read Published Content', 'description' => 'Membaca konten yang telah diterbitkan sesuai cakupan', 'module' => 'Konten'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $rolePermissions = [
        'super_admin' => [
            'system.configure',
            'system.audit_log.view',
            'system.audit_log.oversight',
            'system.audit_log.export',
            'users.create',
            'users.read',
            'users.update',
            'users.deactivate',
            'users.assign_role',
            'reports.read.all',
            'cases.read.metadata',
            'cases.read.all',
            'cases.read.sensitive_oversight',
            'dashboard.admin',
            'statistics.view',
            'statistics.export',
            'content.review',
            'content.publish.global',
            'content.archive',
            'content.feature.manage',
            'content.category.govern',
            'content.read.management.all',
            'content.read.published',
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
            'cases.review_recommendation',
            'cases.record_decision',
            'cases.monitor',
            'dashboard.admin',
            'statistics.view',
            'statistics.export',
            'privacy.approve_break_glass',
            'content.create.campus',
            'content.update.own_campus',
            'content.submit.own_campus',
            'content.read.management.own_campus',
            'content.attachment.manage.own_campus',
            'content.read.published',
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
            'reporter_evidence.read.assigned',
            'reporter_evidence.download.assigned',
            'privacy.request_break_glass',
            'privacy.reveal_anonymous_identity',
            'content.read.published',
        ],
        'reporter' => [
            'reports.create',
            'reports.read.own',
            'messages.send',
            'messages.read.case',
            'evidence.view.case',
            'reporter_evidence.read.own',
            'reporter_evidence.upload.own',
            'reporter_evidence.download.own',
            'content.read.published',
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

        $managedPermissionIds = $permissions->pluck('id');

        foreach ($this->rolePermissions as $roleCode => $permissionCodes) {
            $desiredPermissionIds = $permissions->only($permissionCodes)->pluck('id');

            $roles[$roleCode]->permissions()->detach(
                $managedPermissionIds->diff($desiredPermissionIds)->all()
            );
            $roles[$roleCode]->permissions()->syncWithoutDetaching($desiredPermissionIds->all());
        }
    }
}
