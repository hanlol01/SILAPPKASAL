<?php

namespace Tests;

use App\Services\TestDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Assert the effective database after bootstrap and before Laravel runs test traits.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $this->app->make(TestDatabaseGuard::class)->assertSafe();
    }
}
