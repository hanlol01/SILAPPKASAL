<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{name: string, description: string}> */
    private array $permissions = [
        'content.create.campus' => ['name' => 'Create Campus Content', 'description' => 'Membuat draf konten untuk kampus sendiri'],
        'content.update.own_campus' => ['name' => 'Update Own Campus Content', 'description' => 'Memperbarui draf konten kampus sendiri'],
        'content.submit.own_campus' => ['name' => 'Submit Own Campus Content', 'description' => 'Mengajukan konten kampus sendiri untuk peninjauan'],
        'content.read.management.own_campus' => ['name' => 'Read Own Campus Content Management', 'description' => 'Melihat data pengelolaan konten kampus sendiri'],
        'content.attachment.manage.own_campus' => ['name' => 'Manage Own Campus Content Attachments', 'description' => 'Mengelola lampiran draf konten kampus sendiri'],
        'content.review' => ['name' => 'Review Content', 'description' => 'Meninjau konten kampus'],
        'content.publish.global' => ['name' => 'Publish Global Content', 'description' => 'Membuat dan menerbitkan konten global'],
        'content.archive' => ['name' => 'Archive Content', 'description' => 'Mengarsipkan konten'],
        'content.feature.manage' => ['name' => 'Manage Featured Content', 'description' => 'Mengelola konten unggulan'],
        'content.category.govern' => ['name' => 'Govern Content Categories', 'description' => 'Mengelola kategori konten'],
        'content.read.management.all' => ['name' => 'Read All Content Management', 'description' => 'Melihat seluruh data pengelolaan konten'],
        'content.read.published' => ['name' => 'Read Published Content', 'description' => 'Membaca konten terbit sesuai cakupan'],
    ];

    /** @var array<string, list<string>> */
    private array $assignments = [
        'super_admin' => [
            'content.review', 'content.publish.global', 'content.archive', 'content.feature.manage',
            'content.category.govern', 'content.read.management.all', 'content.read.published',
        ],
        'admin' => [
            'content.create.campus', 'content.update.own_campus', 'content.submit.own_campus',
            'content.read.management.own_campus', 'content.attachment.manage.own_campus', 'content.read.published',
        ],
        'satgas_ppks' => ['content.read.published'],
        'reporter' => ['content.read.published'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ($this->permissions as $code => $attributes) {
                DB::table('permissions')->insertOrIgnore([
                    'code' => $code,
                    ...$attributes,
                    'module' => 'Konten',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('permissions')->where('code', $code)->update([
                    ...$attributes,
                    'module' => 'Konten',
                    'updated_at' => now(),
                ]);
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('code', array_keys($this->permissions))
                ->pluck('id', 'code');

            foreach ($this->assignments as $roleCode => $codes) {
                $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

                if ($roleId === null) {
                    continue;
                }

                DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->whereIn('permission_id', $permissionIds->values())
                    ->delete();

                foreach ($codes as $code) {
                    $permissionId = $permissionIds[$code] ?? null;

                    if ($permissionId !== null) {
                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_keys($this->permissions))
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
