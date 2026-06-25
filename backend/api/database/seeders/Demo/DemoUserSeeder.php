<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'superadmin@silappkasal.test'],
            [
                'name' => 'Nadia Prameswari',
                'role_id' => DemoSeed::role('super_admin')->id,
                'password' => Hash::make(DemoSeed::PASSWORD),
                'phone_number' => '081200000000',
                'is_active' => true,
            ]
        );

        foreach (DemoSeed::universityCodes() as $campusIndex => $universityCode) {
            $university = DemoSeed::university($universityCode);
            $studyProgram = DemoSeed::primaryStudyProgram($university);
            $faculty = DemoSeed::facultyForStudyProgram($studyProgram);
            $slug = DemoSeed::slug($universityCode);
            $baseNim = 2026000 + (($campusIndex + 1) * 100);

            User::query()->updateOrCreate(
                ['email' => DemoSeed::campusEmail('admin', $universityCode)],
                [
                    'name' => "Admin Kampus {$university->abbreviation}",
                    'role_id' => DemoSeed::role('admin')->id,
                    'password' => Hash::make(DemoSeed::PASSWORD),
                    'nip' => sprintf('ADM-%s-001', strtoupper($slug)),
                    'phone_number' => '0812'.str_pad((string) ($campusIndex + 1), 8, '0', STR_PAD_LEFT),
                    'university_id' => $university->id,
                    'faculty_id' => null,
                    'study_program_id' => null,
                    'is_active' => true,
                ]
            );

            for ($i = 1; $i <= 2; $i++) {
                User::query()->updateOrCreate(
                    ['email' => DemoSeed::campusEmail('satgas', $universityCode, $i)],
                    [
                        'name' => "Satgas {$university->abbreviation} {$i}",
                        'role_id' => DemoSeed::role('satgas_ppks')->id,
                        'password' => Hash::make(DemoSeed::PASSWORD),
                        'nip' => sprintf('STG-%s-%03d', strtoupper($slug), $i),
                        'phone_number' => '0821'.str_pad((string) (($campusIndex + 1) * 10 + $i), 8, '0', STR_PAD_LEFT),
                        'university_id' => $university->id,
                        'faculty_id' => null,
                        'study_program_id' => null,
                        'is_active' => true,
                    ]
                );
            }

            for ($i = 1; $i <= 2; $i++) {
                User::query()->updateOrCreate(
                    ['email' => DemoSeed::campusEmail('reporter', $universityCode, $i)],
                    [
                        'name' => "Reporter {$university->abbreviation} {$i}",
                        'role_id' => DemoSeed::role('reporter')->id,
                        'password' => Hash::make(DemoSeed::PASSWORD),
                        'nim' => (string) ($baseNim + $i),
                        'phone_number' => '0831'.str_pad((string) (($campusIndex + 1) * 10 + $i), 8, '0', STR_PAD_LEFT),
                        'university_id' => $university->id,
                        'faculty_id' => $faculty?->id,
                        'study_program_id' => $studyProgram->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
