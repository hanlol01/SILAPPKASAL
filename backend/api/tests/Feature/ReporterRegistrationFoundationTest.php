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

class ReporterRegistrationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_public_registration_creates_pending_registration_without_user_or_sensitive_response(): void
    {
        $response = $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'email' => '  STUDENT@example.test  ',
            'nim' => '  230001  ',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ReporterRegistrationStatus::Pending->value)
            ->assertJsonMissingPath('data.password_hash');

        $registration = ReporterRegistration::query()->firstOrFail();

        $this->assertSame('student@example.test', $registration->email);
        $this->assertSame('230001', $registration->nim);
        $this->assertNotNull($registration->registration_number);
        $this->assertNotNull($registration->password_hash);
        $this->assertTrue(Hash::check('SecurePass123', $registration->password_hash));
        $this->assertDatabaseCount('users', 0);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student@example.test',
            'password' => 'SecurePass123',
        ])->assertOk()
            ->assertJsonPath('data.type', 'registration')
            ->assertJsonPath('data.registration.status', ReporterRegistrationStatus::Pending->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterRegistrationSubmitted->value,
        ]);
    }

    public function test_registration_rejects_duplicate_active_user_email_or_nim(): void
    {
        $this->makeUser('reporter', 'student@example.test', '230001');

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'An active account already exists for this email or NIM in the selected university');

        $this->assertDatabaseCount('reporter_registrations', 0);
    }

    public function test_registration_rejects_duplicate_pending_registration_email_or_nim(): void
    {
        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload())
            ->assertCreated();

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'email' => 'other@example.test',
        ]))->assertUnprocessable()
            ->assertJsonPath('message', 'A pending registration already exists for this email or NIM in the selected university');
    }

    public function test_admin_can_approve_registration_and_password_hash_is_cleared(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $registration = $this->submitRegistration();

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', ReporterRegistrationStatus::Approved->value)
            ->assertJsonMissingPath('data.password_hash');

        $registration->refresh();
        $reporter = User::query()->where('email', 'student@example.test')->firstOrFail();

        $this->assertSame(ReporterRegistrationStatus::Approved, $registration->status);
        $this->assertNull($registration->password_hash);
        $this->assertSame($reporter->id, $registration->approved_user_id);
        $this->assertTrue($reporter->is_active);
        $this->assertSame('reporter', $reporter->role->code);
        $this->assertSame($registration->university_id, $reporter->university_id);
        $this->assertSame($registration->faculty_id, $reporter->faculty_id);
        $this->assertSame($registration->study_program_id, $reporter->study_program_id);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student@example.test',
            'password' => 'SecurePass123',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'student@example.test');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterRegistrationApproved->value,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_can_reject_registration_and_password_hash_is_retained_without_user_creation(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $registration = $this->submitRegistration();

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/reject", [
            'rejection_reason' => 'NIM tidak sesuai dengan data kampus.',
        ])->assertOk()
            ->assertJsonPath('data.status', ReporterRegistrationStatus::Rejected->value);

        $registration->refresh();

        $this->assertSame(ReporterRegistrationStatus::Rejected, $registration->status);
        $this->assertNotNull($registration->password_hash);
        $this->assertTrue(Hash::check('SecurePass123', $registration->password_hash));
        $this->assertDatabaseMissing('users', ['email' => 'student@example.test']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReporterRegistrationRejected->value,
            'actor_id' => $admin->id,
        ]);

        $rawAudit = AuditLog::query()
            ->where('action', AuditAction::ReporterRegistrationRejected->value)
            ->firstOrFail()
            ->toJson();

        $this->assertStringNotContainsString('NIM tidak sesuai', $rawAudit);
        $this->assertStringNotContainsString('SecurePass123', $rawAudit);
    }

    public function test_satgas_and_reporter_cannot_review_registrations(): void
    {
        $registration = $this->submitRegistration();

        foreach ([
            $this->makeUser('satgas_ppks', 'satgas@example.test'),
            $this->makeUser('reporter', 'reporter@example.test', '230999'),
        ] as $user) {
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/v1/reporter-registrations')->assertForbidden();
            $this->getJson("/api/v1/reporter-registrations/{$registration->id}")->assertForbidden();
            $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/approve")->assertForbidden();
            $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/reject", [
                'rejection_reason' => 'Tidak memenuhi persyaratan pendaftaran.',
            ])->assertForbidden();
        }
    }

    public function test_admin_can_list_and_filter_registration_metadata(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $registration = $this->submitRegistration();

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/reporter-registrations?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registration->id)
            ->assertJsonMissingPath('data.0.password_hash');
    }

    public function test_approval_duplicate_conflict_keeps_registration_pending(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $registration = $this->submitRegistration();
        $this->makeUser('reporter', 'student@example.test', '999999');

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/reporter-registrations/{$registration->id}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'An active account already exists for this email or NIM in the selected university');

        $registration->refresh();

        $this->assertSame(ReporterRegistrationStatus::Pending, $registration->status);
        $this->assertNotNull($registration->password_hash);
    }

    public function test_public_registration_endpoint_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
                'email' => "student{$i}@example.test",
                'nim' => "23000{$i}",
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload([
            'email' => 'student6@example.test',
            'nim' => '230006',
        ]))->assertTooManyRequests();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $faculty = Faculty::query()->where('university_id', $university->id)->where('code', 'FT')->firstOrFail();
        $studyProgram = StudyProgram::query()->where('university_id', $university->id)->where('code', 'TI')->firstOrFail();

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

    private function submitRegistration(): ReporterRegistration
    {
        $this->postJson('/api/v1/reporter-registrations', $this->registrationPayload())
            ->assertCreated();

        return ReporterRegistration::query()->firstOrFail();
    }

    private function makeUser(string $roleCode, string $email, ?string $nim = null): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();
        $university = University::query()->where('code', 'DEMO-UNIV')->first();

        return User::query()->create([
            'role_id' => $role->id,
            'university_id' => $university?->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'nim' => $nim,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }
}
