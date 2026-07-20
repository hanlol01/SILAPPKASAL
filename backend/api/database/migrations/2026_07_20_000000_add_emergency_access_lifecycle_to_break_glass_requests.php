<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_DURATION_MINUTES = 480;

    public function up(): void
    {
        Schema::table('break_glass_requests', function (Blueprint $table): void {
            $table->unsignedInteger('requested_duration_minutes')
                ->default(self::LEGACY_DURATION_MINUTES)
                ->after('reason');
            $table->timestamp('grant_starts_at')->nullable()->after('approved_at');
            $table->timestamp('expires_at')->nullable()->after('grant_starts_at');
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users');
            $table->text('revocation_reason')->nullable()->after('revoked_by');
            $table->unsignedInteger('view_count')->default(0)->after('viewed_at');
            $table->timestamp('last_viewed_at')->nullable()->after('view_count');

            $table->index(['requestor_id', 'status'], 'break_glass_requestor_status_idx');
            $table->index(['report_id', 'status'], 'break_glass_report_status_idx');
            $table->index(['status', 'expires_at'], 'break_glass_status_expiry_idx');
            $table->index('expires_at', 'break_glass_expiry_idx');
        });

        $this->backfillLegacyGrants();
        $this->normalizeDuplicateActiveRows();
        $this->createActiveGrantUniqueIndex();
    }

    public function down(): void
    {
        $this->dropActiveGrantUniqueIndex();

        DB::table('break_glass_requests')
            ->whereIn('status', ['approved', 'viewed'])
            ->whereNull('viewed_at')
            ->where('view_count', '>', 0)
            ->whereNotNull('last_viewed_at')
            ->update(['viewed_at' => DB::raw('last_viewed_at')]);
        DB::table('break_glass_requests')
            ->where('status', 'revoked')
            ->update(['status' => 'expired']);

        Schema::table('break_glass_requests', function (Blueprint $table): void {
            $table->dropIndex('break_glass_requestor_status_idx');
            $table->dropIndex('break_glass_report_status_idx');
            $table->dropIndex('break_glass_status_expiry_idx');
            $table->dropIndex('break_glass_expiry_idx');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn([
                'requested_duration_minutes',
                'grant_starts_at',
                'expires_at',
                'revoked_at',
                'revocation_reason',
                'view_count',
                'last_viewed_at',
            ]);
        });
    }

    private function backfillLegacyGrants(): void
    {
        $now = CarbonImmutable::now();

        DB::table('break_glass_requests')
            ->orderBy('id')
            ->chunkById(200, function ($requests) use ($now): void {
                foreach ($requests as $request) {
                    $updates = ['requested_duration_minutes' => self::LEGACY_DURATION_MINUTES];

                    if (! in_array($request->status, ['approved', 'viewed'], true)) {
                        DB::table('break_glass_requests')->where('id', $request->id)->update($updates);
                        continue;
                    }

                    $start = $request->viewed_at
                        ?? $request->approved_at
                        ?? $request->requested_at
                        ?? $request->created_at;

                    if ($start === null) {
                        DB::table('break_glass_requests')->where('id', $request->id)->update([
                            ...$updates,
                            'status' => 'expired',
                        ]);
                        continue;
                    }

                    $grantStartsAt = CarbonImmutable::parse($start);
                    $expiresAt = $grantStartsAt->addMinutes(self::LEGACY_DURATION_MINUTES);

                    DB::table('break_glass_requests')->where('id', $request->id)->update([
                        ...$updates,
                        'grant_starts_at' => $grantStartsAt,
                        'expires_at' => $expiresAt,
                        'view_count' => $request->viewed_at === null ? 0 : 1,
                        'last_viewed_at' => $request->viewed_at,
                        'status' => $expiresAt->lte($now) ? 'expired' : $request->status,
                    ]);
                }
            });
    }

    private function normalizeDuplicateActiveRows(): void
    {
        $rows = DB::table('break_glass_requests')
            ->whereIn('status', ['pending', 'approved', 'viewed'])
            ->whereNull('revoked_at')
            ->orderBy('report_id')
            ->orderBy('requestor_id')
            ->orderByDesc('id')
            ->get(['id', 'report_id', 'requestor_id']);
        $seen = [];

        foreach ($rows as $row) {
            $key = $row->report_id.':'.$row->requestor_id;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                continue;
            }

            DB::table('break_glass_requests')->where('id', $row->id)->update([
                'status' => 'expired',
                'expires_at' => DB::raw('COALESCE(expires_at, CURRENT_TIMESTAMP)'),
            ]);
        }
    }

    private function createActiveGrantUniqueIndex(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX break_glass_active_requester_report_unique
            ON break_glass_requests (report_id, requestor_id)
            WHERE revoked_at IS NULL AND status IN ('pending', 'approved', 'viewed')
        SQL);
    }

    private function dropActiveGrantUniqueIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS break_glass_active_requester_report_unique');
    }
};
