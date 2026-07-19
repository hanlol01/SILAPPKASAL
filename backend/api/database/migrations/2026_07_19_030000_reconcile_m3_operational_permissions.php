<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')->insertOrIgnore([
                'code' => 'cases.read.sensitive_oversight',
                'name' => 'Read Sensitive Case Oversight',
                'description' => 'Membaca data sensitif lintas kampus dalam mode pengawasan',
                'module' => 'Kasus',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('permissions')->where('code', 'cases.read.sensitive_oversight')->update([
                'name' => 'Read Sensitive Case Oversight',
                'description' => 'Membaca data sensitif lintas kampus dalam mode pengawasan',
                'module' => 'Kasus',
                'updated_at' => now(),
            ]);
            $permissionId = (int) DB::table('permissions')->where('code', 'cases.read.sensitive_oversight')->value('id');

            $this->attach('super_admin', ['cases.read.sensitive_oversight'], [$permissionId]);
            $this->attach('admin', ['cases.review_recommendation']);
            $this->detach('super_admin', [
                'reports.verify',
                'reports.reject',
                'reports.forward',
                'reports.request_info',
                'cases.assign_satgas',
                'cases.review_recommendation',
                'cases.monitor',
                'cases.record_decision',
                'cases.assess_risk',
                'cases.investigate',
                'cases.recommend',
                'cases.close',
                'cases.escalate',
                'evidence.upload',
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->detach('admin', ['cases.review_recommendation']);
            $this->attach('super_admin', [
                'reports.verify',
                'reports.reject',
                'reports.forward',
                'reports.request_info',
                'cases.assign_satgas',
                'cases.review_recommendation',
                'cases.monitor',
            ]);
            $this->detach('super_admin', ['cases.read.sensitive_oversight']);
            DB::table('permissions')->where('code', 'cases.read.sensitive_oversight')->delete();
        });
    }

    /** @param list<string> $codes @param list<int> $knownPermissionIds */
    private function attach(string $roleCode, array $codes, array $knownPermissionIds = []): void
    {
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

        if ($roleId === null) {
            return;
        }

        $permissionIds = [...$knownPermissionIds, ...DB::table('permissions')->whereIn('code', $codes)->pluck('id')->all()];

        foreach (array_unique($permissionIds) as $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }
    }

    /** @param list<string> $codes */
    private function detach(string $roleCode, array $codes): void
    {
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

        if ($roleId === null) {
            return;
        }
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
