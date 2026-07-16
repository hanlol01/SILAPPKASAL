<?php

namespace Tests\Feature;

use Database\Seeders\MasterDataSeeder;
use App\Models\CaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_tables_and_required_columns_exist(): void
    {
        foreach ($this->masterTables() as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} table is missing");
            $this->assertTrue(Schema::hasColumn($table, 'code'), "{$table}.code is missing");
            $this->assertTrue(Schema::hasColumn($table, 'is_active'), "{$table}.is_active is missing");
            $this->assertTrue(Schema::hasColumn($table, 'sort_order'), "{$table}.sort_order is missing");
        }

        $this->assertTrue(Schema::hasColumn('case_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasColumn('investigation_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasColumn('recommendation_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasColumn('decision_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasColumn('recovery_statuses', 'valid_transitions'));
    }

    public function test_master_data_seeder_is_idempotent_and_seeds_known_codes(): void
    {
        $this->seed(MasterDataSeeder::class);
        $firstRunCounts = $this->tableCounts();

        $this->seed(MasterDataSeeder::class);

        $this->assertSame($firstRunCounts, $this->tableCounts());
        $this->assertDatabaseHas('report_categories', ['code' => 'RCAT-01']);
        $this->assertDatabaseHas('report_types', ['code' => 'RTYP-01']);
        $this->assertDatabaseHas('evidence_types', ['code' => 'EVID-01']);
        $this->assertDatabaseHas('case_statuses', ['code' => 'CSTS-01']);
        $this->assertDatabaseHas('risk_levels', ['code' => 'RISK-01']);
        $this->assertDatabaseHas('priority_levels', ['code' => 'PRIO-01']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-01']);
        $this->assertSame([], CaseStatus::query()->where('name', 'recommendation')->firstOrFail()->valid_transitions);
        $this->assertSame([], CaseStatus::query()->where('name', 'decision')->firstOrFail()->valid_transitions);
    }

    public function test_master_data_seeder_does_not_create_business_rows(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertTrue(Schema::hasTable('reports'));
        $this->assertDatabaseCount('reports', 0);
        $this->assertTrue(Schema::hasTable('cases'));
        $this->assertDatabaseCount('cases', 0);
        $this->assertTrue(Schema::hasTable('investigations'));
        $this->assertDatabaseCount('investigations', 0);
        $this->assertTrue(Schema::hasTable('recommendations'));
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertDatabaseCount('decisions', 0);
        $this->assertTrue(Schema::hasTable('recoveries'));
        $this->assertDatabaseCount('recoveries', 0);
        $this->assertTrue(Schema::hasTable('recovery_monitorings'));
        $this->assertDatabaseCount('recovery_monitorings', 0);
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertDatabaseCount('evidences', 0);
    }

    /**
     * @return list<string>
     */
    private function masterTables(): array
    {
        return [
            'report_categories',
            'report_types',
            'evidence_types',
            'case_statuses',
            'investigation_statuses',
            'recommendation_statuses',
            'decision_statuses',
            'recovery_statuses',
            'notification_types',
            'risk_levels',
            'priority_levels',
            'campus_statuses',
            'relations',
            'location_types',
            'escalation_types',
            'recovery_types',
            'sanction_types',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return collect($this->masterTables())
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
