<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Database\Seeders\CampusMasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampusMasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_only_super_admin_can_access_campus_admin_endpoints(): void
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();

        $this->getJson('/api/v1/campus-admin/universities')->assertUnauthorized();

        foreach (['admin', 'satgas_ppks', 'reporter'] as $role) {
            Sanctum::actingAs($this->makeUser($role), ['*']);

            $this->getJson('/api/v1/campus-admin/universities')->assertForbidden();
            $this->postJson('/api/v1/campus-admin/universities', [])->assertForbidden();
            $this->putJson("/api/v1/campus-admin/universities/{$university->id}", [])->assertForbidden();
            $this->patchJson("/api/v1/campus-admin/universities/{$university->id}/toggle-active")->assertForbidden();
        }

        Sanctum::actingAs($this->makeUser('super_admin'), ['*']);
        $this->getJson('/api/v1/campus-admin/universities')->assertOk();
    }

    public function test_super_admin_can_create_update_and_toggle_university_with_audit_logs(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $create = $this->postJson('/api/v1/campus-admin/universities', [
            'code' => 'QA-UNIV',
            'name' => 'Universitas QA',
            'abbreviation' => 'UQA',
            'type' => 'universitas',
            'has_faculties' => true,
            'website' => 'https://qa.example.test',
            'email' => 'info@qa.example.test',
            'hotline' => '0800-1111',
            'address' => 'Jalan Kampus QA Nomor 1',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'QA-UNIV')
            ->assertJsonPath('data.is_active', true);

        $universityId = $create->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CampusUniversityCreated->value,
            'actor_id' => $superAdmin->id,
            'subject_id' => $universityId,
        ]);

        $this->putJson("/api/v1/campus-admin/universities/{$universityId}", [
            'code' => 'QA-UNIV',
            'name' => 'Universitas QA Updated',
            'abbreviation' => 'UQA',
            'type' => 'universitas',
            'has_faculties' => true,
            'website' => 'https://qa.example.test',
            'email' => 'info@qa.example.test',
            'hotline' => '0800-1111',
            'address' => 'Jalan Kampus QA Nomor 2',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Universitas QA Updated');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CampusUniversityUpdated->value,
            'actor_id' => $superAdmin->id,
            'subject_id' => $universityId,
        ]);

        $this->patchJson("/api/v1/campus-admin/universities/{$universityId}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CampusUniversityDeactivated->value,
            'actor_id' => $superAdmin->id,
            'subject_id' => $universityId,
        ]);
    }

    public function test_public_registration_endpoints_expose_active_records_only_and_new_active_records_immediately(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->postJson('/api/v1/campus-admin/universities', [
            'code' => 'REG-UNIV',
            'name' => 'Universitas Registrasi',
            'type' => 'sekolah_tinggi',
            'has_faculties' => false,
            'address' => 'Jalan Kampus Registrasi Nomor 1',
        ])->assertCreated();

        $universityId = $response->json('data.id');

        $this->postJson('/api/v1/campus-admin/study-programs', [
            'university_id' => $universityId,
            'faculty_id' => null,
            'code' => 'REG-SP',
            'name' => 'Program Registrasi',
            'degree_level' => 'S1',
        ])->assertCreated();

        $this->getJson('/api/v1/universities')
            ->assertOk()
            ->assertJsonFragment(['code' => 'REG-UNIV']);

        $this->getJson("/api/v1/study-programs?university_id={$universityId}")
            ->assertOk()
            ->assertJsonFragment(['code' => 'REG-SP']);

        $this->patchJson("/api/v1/campus-admin/universities/{$universityId}/toggle-active")->assertOk();

        $this->getJson('/api/v1/universities')
            ->assertOk()
            ->assertJsonMissing(['code' => 'REG-UNIV']);

        $this->getJson("/api/v1/study-programs?university_id={$universityId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['university_id']);
    }

    public function test_faculty_crud_validates_university_and_cascades_deactivation(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['*']);

        $facultyResponse = $this->postJson('/api/v1/campus-admin/faculties', [
            'university_id' => $university->id,
            'code' => 'QA-F',
            'name' => 'Fakultas QA',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'QA-F');

        $facultyId = $facultyResponse->json('data.id');

        $studyProgram = StudyProgram::query()->create([
            'university_id' => $university->id,
            'faculty_id' => $facultyId,
            'code' => 'QA-F-SP',
            'name' => 'Program Fakultas QA',
            'degree_level' => 'S1',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CampusFacultyCreated->value,
            'actor_id' => $superAdmin->id,
            'subject_id' => $facultyId,
        ]);

        $this->patchJson("/api/v1/campus-admin/faculties/{$facultyId}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($studyProgram->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CampusFacultyDeactivated->value,
            'actor_id' => $superAdmin->id,
            'subject_id' => $facultyId,
        ]);

        $college = University::query()->where('code', 'DEMO-ST')->firstOrFail();
        $this->postJson('/api/v1/campus-admin/faculties', [
            'university_id' => $college->id,
            'code' => 'NOFAC',
            'name' => 'Fakultas Tidak Valid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['university_id']);
    }

    public function test_study_program_crud_validates_faculty_university_consistency_and_parent_activation(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $otherUniversity = University::query()->where('code', 'IMA-BJR')->firstOrFail();
        $faculty = $university->faculties()->firstOrFail();
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/v1/campus-admin/study-programs', [
            'university_id' => $otherUniversity->id,
            'faculty_id' => $faculty->id,
            'code' => 'BAD-SP',
            'name' => 'Program Tidak Valid',
            'degree_level' => 'S1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['faculty_id']);

        $response = $this->postJson('/api/v1/campus-admin/study-programs', [
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
            'code' => 'QA-SP',
            'name' => 'Program QA',
            'degree_level' => 'S1',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'QA-SP');

        $studyProgramId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CampusStudyProgramCreated->value,
            'actor_id' => $superAdmin->id,
            'subject_id' => $studyProgramId,
        ]);

        $this->patchJson("/api/v1/campus-admin/study-programs/{$studyProgramId}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/v1/campus-admin/faculties/{$faculty->id}/toggle-active")->assertOk();

        $this->patchJson("/api/v1/campus-admin/study-programs/{$studyProgramId}/toggle-active")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot activate a study program while its faculty is inactive');
    }

    public function test_no_hard_delete_routes_exist_for_campus_master_data(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $faculty = $university->faculties()->firstOrFail();
        $studyProgram = $university->studyPrograms()->firstOrFail();
        Sanctum::actingAs($superAdmin, ['*']);

        $this->deleteJson("/api/v1/campus-admin/universities/{$university->id}")->assertStatus(405);
        $this->deleteJson("/api/v1/campus-admin/faculties/{$faculty->id}")->assertStatus(405);
        $this->deleteJson("/api/v1/campus-admin/study-programs/{$studyProgram->id}")->assertStatus(405);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();
        $university = University::query()->where('code', 'DEMO-UNIV')->first();

        return User::query()->create([
            'role_id' => $role->id,
            'university_id' => $roleCode === 'super_admin' ? null : $university?->id,
            'name' => "{$roleCode} User",
            'email' => "{$roleCode}-".uniqid().'@example.test',
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }
}
