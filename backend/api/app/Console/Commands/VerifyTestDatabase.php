<?php

namespace App\Console\Commands;

use App\Services\TestDatabaseGuard;
use Illuminate\Console\Command;

final class VerifyTestDatabase extends Command
{
    protected $signature = 'test-database:verify
        {--confirm-database= : Required exact database confirmation for PostgreSQL}';

    protected $description = 'Fail fast unless the resolved test database is explicitly disposable';

    public function handle(TestDatabaseGuard $guard): int
    {
        $target = $guard->assertSafe();

        $this->line('APP_ENV='.$target['environment']);
        $this->line('DB_CONNECTION='.$target['driver']);
        $this->line('DB_HOST='.($target['host'] ?? 'local'));
        $this->line('DB_DATABASE='.$target['database']);

        if ($target['driver'] === 'pgsql'
            && $this->option('confirm-database') !== $target['database']) {
            $this->error('PostgreSQL verification requires --confirm-database='.$target['database']);

            return self::FAILURE;
        }

        $this->info('Disposable test database target verified.');

        return self::SUCCESS;
    }
}
