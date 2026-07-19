<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_widened_phone_column_preserves_a_thirty_character_value(): void
    {
        $phoneNumber = str_repeat('1', 30);

        $user = User::query()->create([
            'role_id' => Role::query()->where('code', 'reporter')->value('id'),
            'name' => 'Migration Phone User',
            'email' => 'migration-phone@example.test',
            'phone_number' => $phoneNumber,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        $this->assertSame($phoneNumber, $user->refresh()->phone_number);
    }

    public function test_rollback_refuses_to_shrink_when_longer_phone_values_exist(): void
    {
        User::query()->create([
            'role_id' => Role::query()->where('code', 'reporter')->value('id'),
            'name' => 'Rollback Guard User',
            'email' => 'rollback-phone@example.test',
            'phone_number' => str_repeat('1', 16),
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_07_19_000000_widen_users_phone_number.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot shrink users.phone_number to 15 characters while longer values exist.');

        $migration->down();
    }

    public function test_migration_can_safely_rollback_and_reapply_when_values_fit(): void
    {
        $migration = require database_path('migrations/2026_07_19_000000_widen_users_phone_number.php');
        $migration->down();

        $phoneNumber = '081234567890123';
        $user = User::query()->create([
            'role_id' => Role::query()->where('code', 'reporter')->value('id'),
            'name' => 'Migration Round Trip User',
            'email' => 'migration-round-trip@example.test',
            'phone_number' => $phoneNumber,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        $migration->up();

        $this->assertSame($phoneNumber, $user->refresh()->phone_number);
    }
}
