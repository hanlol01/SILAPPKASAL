<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaseMinutesMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config()->set('database.connections.case_minutes_migration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'case_minutes_migration_test');
        DB::purge('case_minutes_migration_test');
        DB::reconnect('case_minutes_migration_test');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('cases', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('case_minutes_migration_test');
        DB::purge('case_minutes_migration_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_additive_schema_enforces_version_and_foreign_key_invariants_and_rolls_back_safely(): void
    {
        $migration = $this->migration();
        $migration->up();

        $this->assertTrue(Schema::hasTable('case_minutes'));
        foreach ([
            'public_id', 'case_id', 'version', 'status', 'occurred_at', 'internal_summary',
            'anonymized_summary', 'outcome', 'follow_up', 'supersedes_id', 'created_by',
            'updated_by', 'finalized_by', 'finalized_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('case_minutes', $column));
        }

        $indexes = collect(DB::select("PRAGMA index_list('case_minutes')"))
            ->pluck('name')
            ->all();
        $this->assertContains('case_minutes_case_version_unique', $indexes);
        $this->assertContains('case_minutes_case_status_index', $indexes);

        $now = now();
        DB::table('users')->insert([
            ['id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('cases')->insert([
            ['id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('case_minutes')->insert([
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'case_id' => 1,
            'version' => 1,
            'status' => 'finalized',
            'occurred_at' => $now,
            'internal_summary' => 'ciphertext-placeholder',
            'anonymized_summary' => 'ciphertext-placeholder',
            'outcome' => 'ciphertext-placeholder',
            'follow_up' => 'ciphertext-placeholder',
            'created_by' => 1,
            'updated_by' => 2,
            'finalized_by' => 3,
            'finalized_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('case_minutes')->insert([
            'public_id' => '00000000-0000-4000-8000-000000000002',
            'case_id' => 1,
            'version' => 2,
            'status' => 'draft',
            'occurred_at' => $now,
            'created_by' => 1,
            'supersedes_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('case_minutes')->insert([
            'public_id' => '00000000-0000-4000-8000-000000000003',
            'case_id' => 2,
            'version' => 1,
            'status' => 'draft',
            'occurred_at' => $now,
            'created_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            DB::table('case_minutes')->insert([
                'public_id' => '00000000-0000-4000-8000-000000000004',
                'case_id' => 1,
                'version' => 1,
                'status' => 'draft',
                'occurred_at' => $now,
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->fail('The unique Case/version constraint must reject duplicates.');
        } catch (QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505']);
        }

        try {
            DB::table('users')->where('id', 1)->delete();
            $this->fail('Deleting the creator must not delete or orphan Case Minute history.');
        } catch (QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23503']);
        }

        DB::table('users')->where('id', 2)->delete();
        DB::table('users')->where('id', 3)->delete();
        $first = DB::table('case_minutes')->where('id', 1)->first();
        $this->assertNull($first->updated_by);
        $this->assertNull($first->finalized_by);

        $migration->down();

        $this->assertFalse(Schema::hasTable('case_minutes'));
        $this->assertTrue(Schema::hasTable('cases'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_24_050000_create_case_minutes_table.php');
    }
}
