<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use Database\Seeders\CampusMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusMasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_universities_faculties_and_study_programs(): void
    {
        $this->seed(CampusMasterDataSeeder::class);

        $demoUniversity = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $demoCollege = University::query()->where('code', 'DEMO-ST')->firstOrFail();

        $this->assertDatabaseHas('universities', ['code' => 'STAI-SA', 'name' => 'STAI Sebelas April']);
        $this->assertDatabaseHas('universities', ['code' => 'STAI-AMG', 'name' => 'STAI Al Musaddadiyah Garut']);
        $this->assertDatabaseHas('universities', ['code' => 'UIKHIR', 'name' => 'Universitas Islam KH. Ilyas Ruhiyat']);
        $this->assertDatabaseHas('universities', ['code' => 'INU-TSM', 'name' => 'Institut Nahdlatul Ulama Tasikmalaya']);
        $this->assertDatabaseHas('universities', ['code' => 'UID-CMS', 'name' => 'Universitas Islam Darussalam Ciamis']);
        $this->assertDatabaseHas('universities', ['code' => 'STITNU-AF', 'name' => 'Sekolah Tinggi Ilmu Tarbiyah Nahdlatul Ulama Al-Farabi']);
        $this->assertDatabaseHas('universities', ['code' => 'IMA-BJR', 'name' => 'Institut Miftahul Al Azhar Banjar']);

        $this->assertSame('universitas', $demoUniversity->type);
        $this->assertTrue($demoUniversity->has_faculties);
        $this->assertSame('sekolah_tinggi', $demoCollege->type);
        $this->assertFalse($demoCollege->has_faculties);
        $this->assertSame(3, $demoUniversity->faculties()->count());
        $this->assertSame(0, $demoCollege->faculties()->count());
        $this->assertSame(5, $demoUniversity->studyPrograms()->count());
        $this->assertSame(2, $demoCollege->studyPrograms()->whereNull('faculty_id')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CampusMasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);

        $this->assertSame(9, University::query()->count());
        $this->assertSame(3, Faculty::query()->count());
        $this->assertSame(7, StudyProgram::query()->count());
    }
}
