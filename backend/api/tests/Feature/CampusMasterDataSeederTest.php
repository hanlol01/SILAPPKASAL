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

        $uikhir = University::query()->where('code', 'UIKHIR')->firstOrFail();
        $staiSebelasApril = University::query()->where('code', 'STAI-SA')->firstOrFail();

        $this->assertDatabaseHas('universities', ['code' => 'STAI-SA', 'name' => 'STAI Sebelas April']);
        $this->assertDatabaseHas('universities', ['code' => 'STAI-AMG', 'name' => 'STAI Al Musaddadiyah Garut']);
        $this->assertDatabaseHas('universities', ['code' => 'UIKHIR', 'name' => 'Universitas Islam KH. Ilyas Ruhiyat']);
        $this->assertDatabaseHas('universities', ['code' => 'INU-TSM', 'name' => 'Institut Nahdlatul Ulama Tasikmalaya']);
        $this->assertDatabaseHas('universities', ['code' => 'UID-CMS', 'name' => 'Universitas Islam Darussalam Ciamis']);
        $this->assertDatabaseHas('universities', ['code' => 'STITNU-AF', 'name' => 'Sekolah Tinggi Ilmu Tarbiyah Nahdlatul Ulama Al-Farabi']);
        $this->assertDatabaseHas('universities', ['code' => 'IMA-BJR', 'name' => 'Institut Miftahul Al Azhar Banjar']);

        $this->assertSame('universitas', $uikhir->type);
        $this->assertTrue($uikhir->has_faculties);
        $this->assertSame('sekolah_tinggi', $staiSebelasApril->type);
        $this->assertFalse($staiSebelasApril->has_faculties);
        $this->assertSame(3, $uikhir->faculties()->count());
        $this->assertSame(0, $staiSebelasApril->faculties()->count());
        $this->assertSame(4, $uikhir->studyPrograms()->count());
        $this->assertSame(3, $staiSebelasApril->studyPrograms()->whereNull('faculty_id')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CampusMasterDataSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);

        $this->assertSame(9, University::query()->count());
        $this->assertSame(15, Faculty::query()->count());
        $this->assertSame(32, StudyProgram::query()->count());
    }
}
