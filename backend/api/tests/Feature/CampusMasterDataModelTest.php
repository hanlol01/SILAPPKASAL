<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\ReporterRegistration;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusMasterDataModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_model_relationships_are_available(): void
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $faculty = $university->faculties()->where('code', 'FT')->firstOrFail();
        $studyProgram = $faculty->studyPrograms()->where('code', 'TI')->firstOrFail();
        $role = Role::query()->where('code', 'reporter')->firstOrFail();

        $user = User::query()->create([
            'role_id' => $role->id,
            'university_id' => $university->id,
            'name' => 'Campus Reporter',
            'email' => 'campus-reporter@example.test',
            'nim' => '230001',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        $registration = ReporterRegistration::query()->create([
            'registration_number' => 'REG-20260624-0001',
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'name' => 'Registrant',
            'email' => 'registrant@example.test',
            'nim' => '230002',
            'phone_number' => '081234567890',
            'password_hash' => 'hashed-password',
            'status' => 'pending',
        ]);

        $this->assertTrue($university->faculties->contains($faculty));
        $this->assertTrue($university->studyPrograms->contains($studyProgram));
        $this->assertTrue($faculty->university->is($university));
        $this->assertTrue($studyProgram->university->is($university));
        $this->assertTrue($studyProgram->faculty->is($faculty));
        $this->assertTrue($user->university->is($university));
        $this->assertTrue($registration->university->is($university));
        $this->assertTrue($registration->faculty->is($faculty));
        $this->assertTrue($registration->studyProgram->is($studyProgram));
    }

    public function test_nim_uniqueness_is_scoped_by_university(): void
    {
        $role = Role::query()->where('code', 'reporter')->firstOrFail();
        $demoUniversity = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $demoCollege = University::query()->where('code', 'DEMO-ST')->firstOrFail();

        User::query()->create([
            'role_id' => $role->id,
            'university_id' => $demoUniversity->id,
            'name' => 'Reporter One',
            'email' => 'reporter-one@example.test',
            'nim' => '230777',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        User::query()->create([
            'role_id' => $role->id,
            'university_id' => $demoCollege->id,
            'name' => 'Reporter Two',
            'email' => 'reporter-two@example.test',
            'nim' => '230777',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        User::query()->create([
            'role_id' => $role->id,
            'university_id' => $demoUniversity->id,
            'name' => 'Reporter Duplicate',
            'email' => 'reporter-duplicate@example.test',
            'nim' => '230777',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    public function test_faculty_code_uniqueness_is_scoped_by_university(): void
    {
        $demoUniversity = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $demoCollege = University::query()->where('code', 'DEMO-ST')->firstOrFail();

        Faculty::query()->create([
            'university_id' => $demoCollege->id,
            'code' => 'FT',
            'name' => 'Fakultas Teknik Sekolah Tinggi Demo',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        Faculty::query()->create([
            'university_id' => $demoUniversity->id,
            'code' => 'FT',
            'name' => 'Duplicate Fakultas Teknik',
            'is_active' => true,
        ]);
    }
}
