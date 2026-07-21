<?php

namespace App\Services;

use RuntimeException;

final class TestDatabaseGuard
{
    /** @return array{environment: string, driver: string, host: ?string, database: string} */
    public function assertSafe(): array
    {
        $environment = (string) config('app.env');
        $connection = (string) config('database.default');
        $configuration = config("database.connections.{$connection}");

        if ($environment !== 'testing') {
            throw new RuntimeException('Test database operations require APP_ENV=testing.');
        }
        if (! is_array($configuration)) {
            throw new RuntimeException('The testing database connection is not configured.');
        }
        if (filled($configuration['url'] ?? null)) {
            throw new RuntimeException('DB_URL is prohibited for destructive test operations.');
        }

        $driver = (string) ($configuration['driver'] ?? '');
        $database = (string) ($configuration['database'] ?? '');
        $host = isset($configuration['host']) ? (string) $configuration['host'] : null;
        $safeSqlite = $driver === 'sqlite' && $database === ':memory:';
        $safePostgreSql = $driver === 'pgsql'
            && $database === 'silappkasal_test'
            && in_array($host, ['127.0.0.1', 'localhost'], true)
            && hash_equals($database, (string) config('database.testing_confirmation'));

        if (! $safeSqlite && ! $safePostgreSql) {
            throw new RuntimeException(
                "Unsafe testing database resolved: driver={$driver}, host=".($host ?? 'local').", database={$database}. "
                .'Only SQLite :memory: or explicitly confirmed local PostgreSQL silappkasal_test is allowed.'
            );
        }

        return [
            'environment' => $environment,
            'driver' => $driver,
            'host' => $host,
            'database' => $database,
        ];
    }
}
