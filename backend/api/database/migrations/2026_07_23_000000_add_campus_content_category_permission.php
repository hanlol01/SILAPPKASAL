<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'content.category.manage.own_campus';

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')->insertOrIgnore([
                'code' => self::PERMISSION,
                'name' => 'Manage Own Campus Content Categories',
                'description' => 'Mengelola registry kategori konten kampus sendiri',
                'module' => 'Konten',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('permissions')->where('code', self::PERMISSION)->update([
                'name' => 'Manage Own Campus Content Categories',
                'description' => 'Mengelola registry kategori konten kampus sendiri',
                'module' => 'Konten',
                'updated_at' => now(),
            ]);

            $roleId = DB::table('roles')->where('code', 'admin')->value('id');
            $permissionId = DB::table('permissions')->where('code', self::PERMISSION)->value('id');

            if ($roleId !== null && $permissionId !== null) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $permissionId = DB::table('permissions')->where('code', self::PERMISSION)->value('id');

            if ($permissionId !== null) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        });
    }
};
