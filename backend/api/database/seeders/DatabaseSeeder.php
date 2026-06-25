<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoDatasetSeeder;
use Database\Seeders\Foundation\CampusMasterDataSeeder;
use Database\Seeders\Foundation\MasterDataSeeder;
use Database\Seeders\Foundation\RbacSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            MasterDataSeeder::class,
            CampusMasterDataSeeder::class,
            DemoDatasetSeeder::class,
        ]);
    }
}
