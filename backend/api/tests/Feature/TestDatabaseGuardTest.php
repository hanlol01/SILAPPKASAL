<?php

namespace Tests\Feature;

use App\Services\TestDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class TestDatabaseGuardTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConfig = [
            'environment' => config('app.env'),
            'default' => config('database.default'),
            'confirmation' => config('database.testing_confirmation'),
            'sqlite' => config('database.connections.sqlite'),
            'pgsql' => config('database.connections.pgsql'),
        ];
    }

    protected function tearDown(): void
    {
        config()->set('app.env', $this->originalConfig['environment']);
        config()->set('database.default', $this->originalConfig['default']);
        config()->set('database.testing_confirmation', $this->originalConfig['confirmation']);
        config()->set('database.connections.sqlite', $this->originalConfig['sqlite']);
        config()->set('database.connections.pgsql', $this->originalConfig['pgsql']);

        parent::tearDown();
    }

    public function test_default_phpunit_target_is_sqlite_memory(): void
    {
        $target = app(TestDatabaseGuard::class)->assertSafe();

        $this->assertSame('testing', $target['environment']);
        $this->assertSame('sqlite', $target['driver']);
        $this->assertSame(':memory:', $target['database']);
    }

    #[DataProvider('localPostgreSqlHosts')]
    public function test_local_postgresql_test_database_requires_and_accepts_exact_confirmation(string $host): void
    {
        $this->configurePostgreSql($host, 'silappkasal_test', 'silappkasal_test');

        $target = app(TestDatabaseGuard::class)->assertSafe();

        $this->assertSame('pgsql', $target['driver']);
        $this->assertSame($host, $target['host']);
        $this->assertSame('silappkasal_test', $target['database']);
    }

    /** @return array<string, array{string}> */
    public static function localPostgreSqlHosts(): array
    {
        return [
            'IPv4 loopback' => ['127.0.0.1'],
            'localhost' => ['localhost'],
        ];
    }

    #[DataProvider('unsafeTargets')]
    public function test_unsafe_effective_configuration_is_rejected(array $target, string $message): void
    {
        $this->applyTarget($target);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        app(TestDatabaseGuard::class)->assertSafe();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unsafeTargets(): array
    {
        return [
            'development database' => [[
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'silappkasal',
                'confirmation' => 'silappkasal_test',
            ], 'database=silappkasal'],
            'non-testing environment' => [[
                'environment' => 'local',
                'driver' => 'sqlite',
                'database' => ':memory:',
            ], 'APP_ENV=testing'],
            'non-empty database URL' => [[
                'driver' => 'sqlite',
                'database' => ':memory:',
                'url' => 'sqlite:///unsafe-test-file.sqlite',
            ], 'DB_URL is prohibited'],
            'SQLite file database' => [[
                'driver' => 'sqlite',
                'database' => dirname(__DIR__, 2).'/database/unsafe-test-file.sqlite',
            ], 'Unsafe testing database resolved'],
            'remote PostgreSQL host' => [[
                'driver' => 'pgsql',
                'host' => 'db.example.test',
                'database' => 'silappkasal_test',
                'confirmation' => 'silappkasal_test',
            ], 'Unsafe testing database resolved'],
            'missing PostgreSQL confirmation' => [[
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'silappkasal_test',
                'confirmation' => null,
            ], 'Unsafe testing database resolved'],
        ];
    }

    /** @param array<string, mixed> $target */
    private function applyTarget(array $target): void
    {
        config()->set('app.env', $target['environment'] ?? 'testing');
        config()->set('database.default', $target['driver']);
        config()->set('database.testing_confirmation', $target['confirmation'] ?? null);

        $connection = config('database.connections.'.$target['driver']);
        $connection['url'] = $target['url'] ?? null;
        $connection['database'] = $target['database'];
        if ($target['driver'] === 'pgsql') {
            $connection['host'] = $target['host'];
        }
        config()->set('database.connections.'.$target['driver'], $connection);
    }

    private function configurePostgreSql(string $host, string $database, ?string $confirmation): void
    {
        $this->applyTarget([
            'driver' => 'pgsql',
            'host' => $host,
            'database' => $database,
            'confirmation' => $confirmation,
        ]);
    }
}
