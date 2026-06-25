<?php

namespace Database\Seeders\Demo;

use App\Enums\ReporterRegistrationStatus;
use App\Models\ReporterRegistration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoSeed::universityCodes() as $campusIndex => $universityCode) {
            $university = DemoSeed::university($universityCode);
            $studyProgram = DemoSeed::primaryStudyProgram($university);
            $faculty = DemoSeed::facultyForStudyProgram($studyProgram);
            $admin = DemoSeed::user(DemoSeed::campusEmail('admin', $universityCode));
            $baseNim = 2026000 + (($campusIndex + 1) * 100);

            $approvedUser = DemoSeed::user(DemoSeed::campusEmail('reporter', $universityCode));
            ReporterRegistration::query()->updateOrCreate(
                ['registration_number' => sprintf('REG-DEMO-%02d-APPROVED', $campusIndex + 1)],
                [
                    'university_id' => $university->id,
                    'faculty_id' => $faculty?->id,
                    'study_program_id' => $studyProgram->id,
                    'name' => $approvedUser->name,
                    'email' => $approvedUser->email,
                    'nim' => $approvedUser->nim,
                    'phone_number' => $approvedUser->phone_number,
                    'password_hash' => null,
                    'status' => ReporterRegistrationStatus::Approved->value,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => DemoSeed::date(25 - $campusIndex),
                    'rejection_reason' => null,
                    'approved_user_id' => $approvedUser->id,
                ]
            );

            ReporterRegistration::query()->updateOrCreate(
                ['registration_number' => sprintf('REG-DEMO-%02d-PENDING', $campusIndex + 1)],
                [
                    'university_id' => $university->id,
                    'faculty_id' => $faculty?->id,
                    'study_program_id' => $studyProgram->id,
                    'name' => "Calon Reporter {$university->abbreviation}",
                    'email' => DemoSeed::campusEmail('pending', $universityCode),
                    'nim' => (string) ($baseNim + 91),
                    'phone_number' => '0841'.str_pad((string) (($campusIndex + 1) * 10 + 1), 8, '0', STR_PAD_LEFT),
                    'password_hash' => Hash::make(DemoSeed::PASSWORD),
                    'status' => ReporterRegistrationStatus::Pending->value,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                    'approved_user_id' => null,
                ]
            );

            ReporterRegistration::query()->updateOrCreate(
                ['registration_number' => sprintf('REG-DEMO-%02d-REJECTED', $campusIndex + 1)],
                [
                    'university_id' => $university->id,
                    'faculty_id' => $faculty?->id,
                    'study_program_id' => $studyProgram->id,
                    'name' => "Pendaftar Revisi {$university->abbreviation}",
                    'email' => DemoSeed::campusEmail('rejected', $universityCode),
                    'nim' => (string) ($baseNim + 92),
                    'phone_number' => '0842'.str_pad((string) (($campusIndex + 1) * 10 + 2), 8, '0', STR_PAD_LEFT),
                    'password_hash' => Hash::make(DemoSeed::PASSWORD),
                    'status' => ReporterRegistrationStatus::Rejected->value,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => DemoSeed::date(5),
                    'rejection_reason' => 'Data NIM perlu diperbaiki agar sesuai dengan dokumen akademik kampus.',
                    'approved_user_id' => null,
                ]
            );
        }
    }
}
