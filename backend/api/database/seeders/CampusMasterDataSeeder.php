<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use Illuminate\Database\Seeder;

class CampusMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->university(
            'STAI-SA',
            'STAI Sebelas April',
            'STAI-SA',
            'sekolah_tinggi',
            false,
            1
        );

        $this->university(
            'STAI-AMG',
            'STAI Al Musaddadiyah Garut',
            'STAI-AMG',
            'sekolah_tinggi',
            false,
            2
        );

        $this->university(
            'UIKHIR',
            'Universitas Islam KH. Ilyas Ruhiyat',
            'UIKHIR',
            'universitas',
            true,
            3
        );

        $this->university(
            'INU-TSM',
            'Institut Nahdlatul Ulama Tasikmalaya',
            'INU-TSM',
            'institut',
            true,
            4
        );

        $this->university(
            'UID-CMS',
            'Universitas Islam Darussalam Ciamis',
            'UID-CMS',
            'universitas',
            true,
            5
        );

        $this->university(
            'STITNU-AF',
            'Sekolah Tinggi Ilmu Tarbiyah Nahdlatul Ulama Al-Farabi',
            'STITNU-AF',
            'sekolah_tinggi',
            false,
            6
        );

        $this->university(
            'IMA-BJR',
            'Institut Miftahul Al Azhar Banjar',
            'IMA-BJR',
            'institut',
            true,
            7
        );

        $demoUniversity = University::query()->updateOrCreate(
            ['code' => 'DEMO-UNIV'],
            [
                'name' => 'Universitas Demo SILAPPKASAL',
                'abbreviation' => 'UNIV-DEMO',
                'address' => 'Jalan Demo Kampus Nomor 1',
                'website' => 'https://demo.ac.id',
                'email' => 'info@demo.ac.id',
                'hotline' => '0800-0000-0001',
                'type' => 'universitas',
                'has_faculties' => true,
                'is_active' => true,
                'sort_order' => 900,
            ]
        );

        $technicalFaculty = $this->faculty($demoUniversity, 'FT', 'Fakultas Teknik', 1);
        $scienceFaculty = $this->faculty($demoUniversity, 'FMIPA', 'Fakultas MIPA', 2);
        $lawFaculty = $this->faculty($demoUniversity, 'FH', 'Fakultas Hukum', 3);

        $this->studyProgram($demoUniversity, $technicalFaculty, 'TI', 'Teknik Informatika', 'S1', 1);
        $this->studyProgram($demoUniversity, $technicalFaculty, 'SI', 'Sistem Informasi', 'S1', 2);
        $this->studyProgram($demoUniversity, $scienceFaculty, 'MAT', 'Matematika', 'S1', 3);
        $this->studyProgram($demoUniversity, $scienceFaculty, 'FIS', 'Fisika', 'S1', 4);
        $this->studyProgram($demoUniversity, $lawFaculty, 'HKM', 'Ilmu Hukum', 'S1', 5);

        $demoCollege = University::query()->updateOrCreate(
            ['code' => 'DEMO-ST'],
            [
                'name' => 'Sekolah Tinggi Demo SILAPPKASAL',
                'abbreviation' => 'ST-DEMO',
                'address' => 'Jalan Demo Sekolah Tinggi Nomor 2',
                'website' => 'https://st-demo.ac.id',
                'email' => 'info@st-demo.ac.id',
                'hotline' => '0800-0000-0002',
                'type' => 'sekolah_tinggi',
                'has_faculties' => false,
                'is_active' => true,
                'sort_order' => 901,
            ]
        );

        $this->studyProgram($demoCollege, null, 'MI', 'Manajemen Informatika', 'D3', 1);
        $this->studyProgram($demoCollege, null, 'TK', 'Teknik Komputer', 'D3', 2);
    }

    private function university(
        string $code,
        string $name,
        string $abbreviation,
        string $type,
        bool $hasFaculties,
        int $sortOrder,
    ): University {
        return University::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'abbreviation' => $abbreviation,
                'address' => null,
                'website' => null,
                'email' => null,
                'hotline' => null,
                'type' => $type,
                'has_faculties' => $hasFaculties,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function faculty(University $university, string $code, string $name, int $sortOrder): Faculty
    {
        return Faculty::query()->updateOrCreate(
            [
                'university_id' => $university->id,
                'code' => $code,
            ],
            [
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function studyProgram(
        University $university,
        ?Faculty $faculty,
        string $code,
        string $name,
        string $degreeLevel,
        int $sortOrder,
    ): StudyProgram {
        return StudyProgram::query()->updateOrCreate(
            [
                'university_id' => $university->id,
                'code' => $code,
            ],
            [
                'faculty_id' => $faculty?->id,
                'name' => $name,
                'degree_level' => $degreeLevel,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }
}
