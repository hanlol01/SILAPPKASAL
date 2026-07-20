<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OPERATIONAL_PERMISSIONS = [
        'system.break_glass_access',
        'privacy.request_break_glass',
        'privacy.approve_break_glass',
        'privacy.reveal_anonymous_identity',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->setRolePermissions('super_admin', []);
            $this->setRolePermissions('admin', ['privacy.approve_break_glass']);
            $this->setRolePermissions('satgas_ppks', [
                'privacy.request_break_glass',
                'privacy.reveal_anonymous_identity',
            ]);
            $this->setRolePermissions('reporter', []);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->setRolePermissions('super_admin', self::OPERATIONAL_PERMISSIONS);
            $this->setRolePermissions('admin', ['privacy.request_break_glass']);
            $this->setRolePermissions('satgas_ppks', []);
            $this->setRolePermissions('reporter', []);
        });
    }

    /** @param list<string> $desiredCodes */
    private function setRolePermissions(string $roleCode, array $desiredCodes): void
    {
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

        if ($roleId === null) {
            return;
        }

        $managedIds = DB::table('permissions')->whereIn('code', self::OPERATIONAL_PERMISSIONS)->pluck('id');
        $desiredIds = DB::table('permissions')->whereIn('code', $desiredCodes)->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $managedIds)
            ->delete();

        foreach ($desiredIds as $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }
    }
};
