<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;
use Tests\TestCase as ApplicationTestCase;

final class TestDatabaseLifecycleGuardTest extends PhpUnitTestCase
{
    public function test_guard_rejects_unsafe_database_before_laravel_reaches_database_traits(): void
    {
        UnsafeDatabaseLifecycleProbe::resetProbe();
        $probe = new UnsafeDatabaseLifecycleProbe('test_placeholder');

        try {
            $probe->runLaravelSetupLifecycle();
            $this->fail('The unsafe database configuration reached Laravel test traits.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('database=silappkasal', $exception->getMessage());
        } finally {
            $probe->disposeApplication();
        }

        $this->assertTrue(UnsafeDatabaseLifecycleProbe::$applicationBootstrapped);
        $this->assertFalse(UnsafeDatabaseLifecycleProbe::$traitSetupReached);
        $this->assertFalse(UnsafeDatabaseLifecycleProbe::$databaseRefreshAttempted);
        $this->assertFalse(UnsafeDatabaseLifecycleProbe::$testBodyReached);
    }
}

final class UnsafeDatabaseLifecycleProbe extends ApplicationTestCase
{
    use RefreshDatabase;

    public static bool $applicationBootstrapped = false;

    public static bool $traitSetupReached = false;

    public static bool $databaseRefreshAttempted = false;

    public static bool $testBodyReached = false;

    public static function resetProbe(): void
    {
        self::$applicationBootstrapped = false;
        self::$traitSetupReached = false;
        self::$databaseRefreshAttempted = false;
        self::$testBodyReached = false;
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        self::$applicationBootstrapped = true;
        $config = $app->make('config');
        $config->set('app.env', 'testing');
        $config->set('database.default', 'pgsql');
        $config->set('database.connections.pgsql.url', null);
        $config->set('database.connections.pgsql.host', '127.0.0.1');
        $config->set('database.connections.pgsql.database', 'silappkasal');
        $config->set('database.testing_confirmation', 'silappkasal_test');

        return $app;
    }

    protected function setUpTraits()
    {
        self::$traitSetupReached = true;

        return parent::setUpTraits();
    }

    public function refreshDatabase(): void
    {
        self::$databaseRefreshAttempted = true;
    }

    public function runLaravelSetupLifecycle(): void
    {
        $this->setUpTheTestEnvironment();
    }

    public function disposeApplication(): void
    {
        if ($this->app !== null) {
            $this->tearDownTheTestEnvironment();
        }
    }

    public function test_placeholder(): void
    {
        self::$testBodyReached = true;
    }
}
