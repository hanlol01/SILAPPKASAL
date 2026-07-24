<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DecisionNumberMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config()->set('database.connections.decision_migration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'decision_migration_test');
        DB::purge('decision_migration_test');
        DB::reconnect('decision_migration_test');

        Schema::create('decisions', function (Blueprint $table): void {
            $table->id();
            $table->string('decision_number', 100)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('decision_migration_test');
        DB::purge('decision_migration_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_duplicate_preflight_fails_before_schema_or_data_mutation(): void
    {
        DB::table('decisions')->insert([
            ['decision_number' => 'LEGACY-DUPLICATE', 'created_at' => now(), 'updated_at' => now()],
            ['decision_number' => 'LEGACY-DUPLICATE', 'created_at' => now(), 'updated_at' => now()],
            ['decision_number' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $before = DB::table('decisions')->orderBy('id')->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
        $migration = $this->migration();

        try {
            $migration->up();
            $this->fail('Expected duplicate decision number preflight to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('1 duplicate non-null decision_number group', $exception->getMessage());
            $this->assertStringNotContainsString('LEGACY-DUPLICATE', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('decision_number_sequences'));
        $this->assertSame(
            $before,
            DB::table('decisions')->orderBy('id')->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        );
        DB::table('decisions')->insert([
            'decision_number' => 'LEGACY-DUPLICATE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseCount('decisions', 4, 'decision_migration_test');
    }

    public function test_additive_schema_enforces_global_uniqueness_allows_nulls_and_rolls_back_safely(): void
    {
        $migration = $this->migration();
        $migration->up();

        $this->assertTrue(Schema::hasTable('decision_number_sequences'));
        $this->assertTrue(Schema::hasColumn('decision_number_sequences', 'year'));
        $this->assertTrue(Schema::hasColumn('decision_number_sequences', 'last_value'));
        $yearColumn = collect(DB::select("PRAGMA table_info('decision_number_sequences')"))
            ->firstWhere('name', 'year');
        $this->assertSame(1, (int) $yearColumn->pk);

        DB::table('decisions')->insert([
            ['decision_number' => null, 'created_at' => now(), 'updated_at' => now()],
            ['decision_number' => null, 'created_at' => now(), 'updated_at' => now()],
            ['decision_number' => 'LEGACY-UNIQUE', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->assertSame(2, DB::table('decisions')->whereNull('decision_number')->count());

        try {
            DB::table('decisions')->insert([
                'decision_number' => 'LEGACY-UNIQUE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected duplicate non-null decision number to be rejected.');
        } catch (QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505']);
        }

        DB::table('decision_number_sequences')->insert([
            'year' => 2026,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, (int) DB::table('decision_number_sequences')
            ->where('year', 2026)
            ->value('last_value'));

        try {
            DB::table('decision_number_sequences')->insert([
                'year' => 2026,
                'last_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected duplicate sequence year to be rejected.');
        } catch (QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505']);
        }

        $migration->down();

        $this->assertFalse(Schema::hasTable('decision_number_sequences'));
        $this->assertTrue(Schema::hasColumn('decisions', 'decision_number'));
        DB::table('decisions')->insert([
            'decision_number' => 'LEGACY-UNIQUE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(2, DB::table('decisions')->where('decision_number', 'LEGACY-UNIQUE')->count());
    }

    public function test_schema_failure_rolls_back_the_additive_sequence_table(): void
    {
        Schema::table('decisions', function (Blueprint $table): void {
            $table->unique('created_at', 'decisions_decision_number_unique');
        });

        $failed = false;

        try {
            $this->migration()->up();
        } catch (\Throwable) {
            $failed = true;
        }

        $this->assertTrue($failed);
        $this->assertFalse(Schema::hasTable('decision_number_sequences'));

        Schema::table('decisions', function (Blueprint $table): void {
            $table->dropUnique('decisions_decision_number_unique');
        });
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_24_040000_add_formal_decision_number_sequence.php',
        );
    }
}
