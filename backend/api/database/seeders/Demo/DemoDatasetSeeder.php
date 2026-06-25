<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDatasetSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                DemoUserSeeder::class,
                DemoRegistrationSeeder::class,
                DemoReportSeeder::class,
                DemoCaseSeeder::class,
                DemoInvestigationSeeder::class,
                DemoRecommendationSeeder::class,
                DemoDecisionSeeder::class,
                DemoRecoverySeeder::class,
                DemoEvidenceSeeder::class,
                DemoNotificationSeeder::class,
                DemoAuditSeeder::class,
            ]);
        });
    }
}
