<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Console\Command;

final class PurgeExpiredLoginFailureAudits extends Command
{
    protected $signature = 'audit:purge-expired-login-failures
        {--execute : Delete eligible expired rows}
        {--batch=500 : Maximum records deleted per batch}';

    protected $description = 'Purge only expired anonymous login-failure audit records';

    public function handle(): int
    {
        $query = AuditLog::query()
            ->where('action', AuditAction::AuthLoginFailed->value)
            ->whereNull('actor_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        $count = (clone $query)->count();

        if (! $this->option('execute')) {
            $this->info("Dry run: {$count} expired anonymous login-failure records are eligible.");

            return self::SUCCESS;
        }

        $batchSize = max(1, min(1000, (int) $this->option('batch')));
        $deleted = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit($batchSize)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += AuditLog::query()->whereKey($ids)->delete();
        } while ($ids->count() === $batchSize);

        $this->info("Deleted {$deleted} expired anonymous login-failure records.");

        return self::SUCCESS;
    }
}
