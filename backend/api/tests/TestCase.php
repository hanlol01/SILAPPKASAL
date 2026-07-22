<?php

namespace Tests;

use App\Models\User;
use App\Services\TestDatabaseGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Notifications\DatabaseNotification;

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

    /**
     * The framework notification migration stores `data` as text. Filtering
     * the decoded payload in memory keeps assertions portable across SQLite
     * and PostgreSQL without pretending that column is JSONB.
     *
     * @return Collection<int, DatabaseNotification>
     */
    protected function notificationsByType(User $user, string $type): Collection
    {
        return $user->notifications()->get()
            ->filter(fn ($notification): bool => ($notification->data['notification_type_code'] ?? null) === $type)
            ->values();
    }
}
