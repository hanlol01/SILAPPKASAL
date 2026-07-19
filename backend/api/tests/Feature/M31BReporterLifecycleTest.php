<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ReporterRegistrationStatus;
use App\Models\AuditLog;
use App\Models\Faculty;
use App\Models\ReporterRegistration;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class M31BReporterLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_registration_requires_and_validates_campus_fields(): void
    {
        $this->postJson('/api/v1/reporter-registrations', [
            'name' => 'Mahasiswa Demo',
            'email' => 'student@example.test',
            'nim' => '230001',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number', 'university_id', 'study_program_id']);

        $demoUniversity = $this->university('DEMO-UNIV');
        $demoCollege = $this->university('DEMO-ST');
        $faculty = $this->faculty('DEMO-UNIV', 'FT');
        $studyProgram = $this->studyProgram('DEMO-ST', 'MI');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'university_id' => $demoUniversity->id,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
        ]))->assertUnprocessable()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonValidationErrors(['study_program_id'])
            ->assertJsonPath(
                'errors.study_program_id.0',
                'The selected study program does not belong to the selected university.',
            );

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'university_id' => $demoCollege->id,
            'faculty_id' => null,
            'study_program_id' => $studyProgram->id,
        ]))->assertCreated();
    }

    public function test_university_scoped_nim_and_global_email_duplicate_rules(): void
    {
        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload())
            ->assertCreated();

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'email' => 'second@example.test',
        ]))->assertUnprocessable()
            ->assertJsonPath('error_code', 'registration_duplicate_pending');

        $otherUniversity = $this->university('DEMO-ST');
        $otherStudyProgram = $this->studyProgram('DEMO-ST', 'MI');

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'email' => 'same-nim-other-campus@example.test',
            'university_id' => $otherUniversity->id,
            'faculty_id' => null,
            'study_program_id' => $otherStudyProgram->id,
        ]))->assertCreated();

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'email' => 'student@example.test',
            'nim' => '990001',
            'university_id' => $otherUniversity->id,
            'faculty_id' => null,
            'study_program_id' => $otherStudyProgram->id,
        ]))->assertUnprocessable();
    }

    public function test_approval_copies_campus_fields_to_user_and_pending_login_returns_registration_state(): void
    {
        $registration = $this->submitRegistration();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '230001',
            'password' => 'SecurePass123',
        ])->assertUnauthorized()
            ->assertJsonPath('error_code', 'invalid_credentials');

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student@example.test',
            'password' => 'SecurePass123',
        ])->assertOk()
            ->assertJsonPath('data.type', 'registration')
            ->assertJsonPath('data.registration.university_id', $registration->university_id)
            ->assertJsonPath('data.registration.status', ReporterRegistrationStatus::Pending->value);

        $admin = $this->makeUser('admin', 'admin@example.test', $registration->university_id);
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/approve")
            ->assertOk();

        $reporter = User::query()->where('email', 'student@example.test')->firstOrFail();

        $this->assertSame($registration->university_id, $reporter->university_id);
        $this->assertSame($registration->faculty_id, $reporter->faculty_id);
        $this->assertSame($registration->study_program_id, $reporter->study_program_id);
    }

    public function test_rejected_applicant_can_login_correct_nim_and_resubmit(): void
    {
        $registration = $this->submitRegistration();
        $admin = $this->makeUser('admin', 'admin@example.test', $registration->university_id);
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/reject", [
            'rejection_reason' => 'NIM tidak sesuai data kampus.',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student@example.test',
            'password' => 'SecurePass123',
        ])->assertOk()
            ->assertJsonPath('data.type', 'registration')
            ->assertJsonPath('data.registration.status', ReporterRegistrationStatus::Rejected->value)
            ->assertJsonPath('data.registration.rejection_reason', 'NIM tidak sesuai data kampus.');

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '230001',
            'password' => 'SecurePass123',
        ])->assertUnauthorized()
            ->assertJsonPath('error_code', 'invalid_credentials');

        $this->patchJson('/api/v1/reporter-registrations/correct', array_merge($this->registrationPayload([
            'email' => 'student@example.test',
            'password' => 'SecurePass123',
            'password_confirmation' => null,
            'nim' => 'DEMO-2026-001',
            'phone_number' => '+6281234567890',
            'new_password' => 'NewSecurePass123',
            'new_password_confirmation' => 'NewSecurePass123',
        ]), ['password_confirmation' => null]))->assertOk()
            ->assertJsonPath('data.status', ReporterRegistrationStatus::Pending->value)
            ->assertJsonPath('data.nim', 'DEMO-2026-001')
            ->assertJsonPath('data.phone_number', '+6281234567890')
            ->assertJsonPath('data.rejection_reason', null);

        $registration->refresh();

        $this->assertSame(ReporterRegistrationStatus::Pending, $registration->status);
        $this->assertNull($registration->reviewed_by);
        $this->assertNull($registration->reviewed_at);
        $this->assertNull($registration->rejection_reason);
        $this->assertTrue(Hash::check('NewSecurePass123', $registration->password_hash));
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterRegistrationCorrected->value,
        ]);

        $auditJson = AuditLog::query()
            ->where('action', AuditAction::ReporterRegistrationCorrected->value)
            ->firstOrFail()
            ->toJson();

        $this->assertStringNotContainsString('previous_nim', $auditJson);
        $this->assertStringNotContainsString('230001', $auditJson);
        $this->assertStringNotContainsString('DEMO-2026-001', $auditJson);
        $this->assertStringNotContainsString('NewSecurePass123', $auditJson);
    }

    public function test_admin_campus_scope_for_registrations_and_reporters(): void
    {
        $ownRegistration = $this->submitRegistration();
        $otherUniversity = $this->university('DEMO-ST');
        $otherStudyProgram = $this->studyProgram('DEMO-ST', 'MI');
        $otherRegistration = $this->submitRegistration([
            'email' => 'other-campus@example.test',
            'nim' => '230001',
            'university_id' => $otherUniversity->id,
            'faculty_id' => null,
            'study_program_id' => $otherStudyProgram->id,
        ]);

        $admin = $this->makeUser('admin', 'admin@example.test', $ownRegistration->university_id);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/reporter-registrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRegistration->id);

        $this->getJson("/api/v1/reporter-registrations/{$otherRegistration->id}")
            ->assertForbidden();

        $nullCampusAdmin = $this->makeUser('admin', 'null-campus-admin@example.test', null);
        Sanctum::actingAs($nullCampusAdmin, ['*']);

        $this->getJson('/api/v1/reporter-registrations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->patchJson("/api/v1/reporter-registrations/{$ownRegistration->id}/approve")
            ->assertForbidden();
    }

    public function test_manual_reporter_creation_and_password_reset_are_campus_scoped(): void
    {
        $university = $this->university('DEMO-UNIV');
        $admin = $this->makeUser('admin', 'admin@example.test', $university->id);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/v1/users/reporters', $this->manualReporterPayload())
            ->assertCreated()
            ->assertJsonPath('data.user.role.code', 'reporter')
            ->assertJsonPath('data.user.university_id', $university->id)
            ->assertJsonStructure(['data' => ['temporary_password']]);

        $reporter = User::query()->where('email', 'manual-reporter@example.test')->firstOrFail();

        $this->assertDatabaseMissing('reporter_registrations', ['email' => 'manual-reporter@example.test']);
        $this->assertSame($response->json('data.temporary_password'), 'ManualPass123');
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserReporterCreated->value,
            'actor_id' => $admin->id,
        ]);

        $resetResponse = $this->patchJson("/api/v1/users/{$reporter->id}/reset-password")
            ->assertOk()
            ->assertJsonStructure(['data' => ['temporary_password']]);

        $this->assertNotSame('ManualPass123', $resetResponse->json('data.temporary_password'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserPasswordReset->value,
            'actor_id' => $admin->id,
        ]);

        $otherUniversity = $this->university('DEMO-ST');

        $this->postJson('/api/v1/users/reporters', $this->manualReporterPayload([
            'email' => 'wrong-campus@example.test',
            'nim' => '990001',
            'university_id' => $otherUniversity->id,
            'faculty_id' => null,
            'study_program_id' => $this->studyProgram('DEMO-ST', 'MI')->id,
        ]))->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot manage users for this university');
    }

    public function test_correction_and_manual_reporter_reject_invalid_phone_numbers(): void
    {
        $this->patchJson('/api/v1/reporter-registrations/correct', array_merge($this->registrationPayload([
            'password_confirmation' => null,
            'phone_number' => '0812 3456',
        ]), ['password_confirmation' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);

        $university = $this->university('DEMO-UNIV');
        $admin = $this->makeUser('admin', 'phone-admin@example.test', $university->id);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/users/reporters', $this->manualReporterPayload([
            'email' => 'invalid-phone@example.test',
            'nim' => 'DEMO-MANUAL-001',
            'phone_number' => '0812-3456',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        $university = $this->university('DEMO-UNIV');
        $faculty = $this->faculty('DEMO-UNIV', 'FT');
        $studyProgram = $this->studyProgram('DEMO-UNIV', 'TI');

        return array_merge([
            'name' => 'Mahasiswa Demo',
            'email' => 'student@example.test',
            'nim' => '230001',
            'phone_number' => '081234567890',
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function manualReporterPayload(array $overrides = []): array
    {
        $payload = $this->registrationPayload([
            'name' => 'Manual Reporter',
            'email' => 'manual-reporter@example.test',
            'nim' => '880001',
            'password' => 'ManualPass123',
        ]);

        unset($payload['password_confirmation']);

        return array_merge($payload, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function submitRegistration(array $overrides = []): ReporterRegistration
    {
        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload($overrides))
            ->assertCreated();

        return ReporterRegistration::query()->where('email', $overrides['email'] ?? 'student@example.test')->firstOrFail();
    }

    private function makeUser(string $roleCode, string $email, ?int $universityId): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'university_id' => $universityId,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function university(string $code): University
    {
        return University::query()->where('code', $code)->firstOrFail();
    }

    private function faculty(string $universityCode, string $code): Faculty
    {
        return Faculty::query()
            ->where('university_id', $this->university($universityCode)->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function studyProgram(string $universityCode, string $code): StudyProgram
    {
        return StudyProgram::query()
            ->where('university_id', $this->university($universityCode)->id)
            ->where('code', $code)
            ->firstOrFail();
    }
}
