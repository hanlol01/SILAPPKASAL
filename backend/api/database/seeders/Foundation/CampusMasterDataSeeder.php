<?php

namespace Database\Seeders\Foundation;

use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use Illuminate\Database\Seeder;

class CampusMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $staiSebelasApril = $this->university('STAI-SA', 'STAI Sebelas April', 'STAI-SA', 'sekolah_tinggi', false, 1);
        $this->studyProgram($staiSebelasApril, null, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($staiSebelasApril, null, 'ES', 'Ekonomi Syariah', 'S1', 2);
        $this->studyProgram($staiSebelasApril, null, 'HKI', 'Hukum Keluarga Islam', 'S1', 3);

        $staiAlMusaddadiyah = $this->university('STAI-AMG', 'STAI Al Musaddadiyah Garut', 'STAI-AMG', 'sekolah_tinggi', false, 2);
        $this->studyProgram($staiAlMusaddadiyah, null, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($staiAlMusaddadiyah, null, 'PGMI', 'Pendidikan Guru Madrasah Ibtidaiyah', 'S1', 2);
        $this->studyProgram($staiAlMusaddadiyah, null, 'MPI', 'Manajemen Pendidikan Islam', 'S1', 3);

        $uikhir = $this->university('UIKHIR', 'Universitas Islam KH. Ilyas Ruhiyat', 'UIKHIR', 'universitas', true, 3);
        $tarbiyahUikhir = $this->faculty($uikhir, 'FTK', 'Fakultas Tarbiyah dan Keguruan', 1);
        $syariahUikhir = $this->faculty($uikhir, 'FSH', 'Fakultas Syariah dan Hukum', 2);
        $ekonomiUikhir = $this->faculty($uikhir, 'FEBI', 'Fakultas Ekonomi dan Bisnis Islam', 3);
        $this->studyProgram($uikhir, $tarbiyahUikhir, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($uikhir, $tarbiyahUikhir, 'PGMI', 'Pendidikan Guru Madrasah Ibtidaiyah', 'S1', 2);
        $this->studyProgram($uikhir, $syariahUikhir, 'HKI', 'Hukum Keluarga Islam', 'S1', 3);
        $this->studyProgram($uikhir, $ekonomiUikhir, 'ES', 'Ekonomi Syariah', 'S1', 4);

        $inuTasikmalaya = $this->university('INU-TSM', 'Institut Nahdlatul Ulama Tasikmalaya', 'INU-TSM', 'institut', true, 4);
        $tarbiyahInu = $this->faculty($inuTasikmalaya, 'FTK', 'Fakultas Tarbiyah dan Keguruan', 1);
        $teknikInu = $this->faculty($inuTasikmalaya, 'FT', 'Fakultas Teknik', 2);
        $ekonomiInu = $this->faculty($inuTasikmalaya, 'FEB', 'Fakultas Ekonomi dan Bisnis', 3);
        $this->studyProgram($inuTasikmalaya, $tarbiyahInu, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($inuTasikmalaya, $teknikInu, 'IF', 'Informatika', 'S1', 2);
        $this->studyProgram($inuTasikmalaya, $teknikInu, 'SI', 'Sistem Informasi', 'S1', 3);
        $this->studyProgram($inuTasikmalaya, $ekonomiInu, 'MNJ', 'Manajemen', 'S1', 4);

        $uidCiamis = $this->university('UID-CMS', 'Universitas Islam Darussalam Ciamis', 'UID-CMS', 'universitas', true, 5);
        $agamaUid = $this->faculty($uidCiamis, 'FAI', 'Fakultas Agama Islam', 1);
        $sospolUid = $this->faculty($uidCiamis, 'FISIP', 'Fakultas Ilmu Sosial dan Ilmu Politik', 2);
        $ekonomiUid = $this->faculty($uidCiamis, 'FE', 'Fakultas Ekonomi', 3);
        $this->studyProgram($uidCiamis, $agamaUid, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($uidCiamis, $agamaUid, 'PBS', 'Perbankan Syariah', 'S1', 2);
        $this->studyProgram($uidCiamis, $sospolUid, 'IAP', 'Ilmu Administrasi Publik', 'S1', 3);
        $this->studyProgram($uidCiamis, $ekonomiUid, 'MNJ', 'Manajemen', 'S1', 4);

        $stitNuAlFarabi = $this->university('STITNU-AF', 'Sekolah Tinggi Ilmu Tarbiyah Nahdlatul Ulama Al-Farabi', 'STITNU-AF', 'sekolah_tinggi', false, 6);
        $this->studyProgram($stitNuAlFarabi, null, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($stitNuAlFarabi, null, 'PIAUD', 'Pendidikan Islam Anak Usia Dini', 'S1', 2);
        $this->studyProgram($stitNuAlFarabi, null, 'MPI', 'Manajemen Pendidikan Islam', 'S1', 3);

        $imaBanjar = $this->university('IMA-BJR', 'Institut Miftahul Al Azhar Banjar', 'IMA-BJR', 'institut', true, 7);
        $tarbiyahIma = $this->faculty($imaBanjar, 'FTK', 'Fakultas Tarbiyah dan Keguruan', 1);
        $syariahIma = $this->faculty($imaBanjar, 'FS', 'Fakultas Syariah', 2);
        $dakwahIma = $this->faculty($imaBanjar, 'FD', 'Fakultas Dakwah', 3);
        $this->studyProgram($imaBanjar, $tarbiyahIma, 'PAI', 'Pendidikan Agama Islam', 'S1', 1);
        $this->studyProgram($imaBanjar, $tarbiyahIma, 'PGMI', 'Pendidikan Guru Madrasah Ibtidaiyah', 'S1', 2);
        $this->studyProgram($imaBanjar, $syariahIma, 'HES', 'Hukum Ekonomi Syariah', 'S1', 3);
        $this->studyProgram($imaBanjar, $dakwahIma, 'KPI', 'Komunikasi dan Penyiaran Islam', 'S1', 4);

        $demoUniversity = $this->university('DEMO-UNIV', 'Universitas Demo SILAPPKASAL', 'UNIV-DEMO', 'universitas', true, 900);
        $technicalFaculty = $this->faculty($demoUniversity, 'FT', 'Fakultas Teknik', 1);
        $scienceFaculty = $this->faculty($demoUniversity, 'FMIPA', 'Fakultas MIPA', 2);
        $lawFaculty = $this->faculty($demoUniversity, 'FH', 'Fakultas Hukum', 3);
        $this->studyProgram($demoUniversity, $technicalFaculty, 'TI', 'Teknik Informatika', 'S1', 1);
        $this->studyProgram($demoUniversity, $technicalFaculty, 'SI', 'Sistem Informasi', 'S1', 2);
        $this->studyProgram($demoUniversity, $scienceFaculty, 'MAT', 'Matematika', 'S1', 3);
        $this->studyProgram($demoUniversity, $scienceFaculty, 'FIS', 'Fisika', 'S1', 4);
        $this->studyProgram($demoUniversity, $lawFaculty, 'HKM', 'Ilmu Hukum', 'S1', 5);

        $demoCollege = $this->university('DEMO-ST', 'Sekolah Tinggi Demo SILAPPKASAL', 'ST-DEMO', 'sekolah_tinggi', false, 901);
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
