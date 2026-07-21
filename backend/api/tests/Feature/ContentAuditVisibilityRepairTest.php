<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentAuditVisibilityRepairTest extends TestCase
{
    use RefreshDatabase;

    private University $campusA;

    private University $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->campusA = $this->university('AUDIT-A');
        $this->campusB = $this->university('AUDIT-B');
    }

    public function test_content_audit_index_is_campus_scoped_and_global_hidden_for_admin(): void
    {
        $own = $this->contentAudit('campus', $this->campusA->code);
        $foreign = $this->contentAudit('campus', $this->campusB->code);
        $global = $this->contentAudit('global', null);
        $admin = $this->user('admin', $this->campusA);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->getJson('/api/v1/audit-logs?category=content&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['public_id' => $own->public_id]);
        $response->assertJsonMissing(['public_id' => $foreign->public_id])
            ->assertJsonMissing(['public_id' => $global->public_id]);

        Sanctum::actingAs($this->user('admin'), ['*']);
        $this->getJson('/api/v1/audit-logs?category=content&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        foreach (['reporter', 'satgas_ppks'] as $role) {
            Sanctum::actingAs($this->user($role, $this->campusA), ['*']);
            $this->getJson('/api/v1/audit-logs?category=content')->assertForbidden();
        }
    }

    public function test_content_audit_detail_uses_non_disclosing_scope_and_super_admin_sees_all(): void
    {
        $own = $this->contentAudit('campus', $this->campusA->code);
        $foreign = $this->contentAudit('campus', $this->campusB->code);
        $global = $this->contentAudit('global', null);

        Sanctum::actingAs($this->user('admin', $this->campusA), ['*']);
        $this->getJson('/api/v1/audit-logs/'.$own->public_id)->assertOk();
        $this->getJson('/api/v1/audit-logs/'.$foreign->public_id)->assertNotFound();
        $this->getJson('/api/v1/audit-logs/'.$global->public_id)->assertNotFound();

        Sanctum::actingAs($this->user('super_admin'), ['*']);
        $response = $this->getJson('/api/v1/audit-logs?category=content&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
        foreach ([$own, $foreign, $global] as $audit) {
            $response->assertJsonFragment(['public_id' => $audit->public_id]);
            $this->getJson('/api/v1/audit-logs/'.$audit->public_id)->assertOk();
        }
    }

    private function contentAudit(string $scope, ?string $universityCode): AuditLog
    {
        return app(AuditLogService::class)->record(
            action: AuditAction::ContentItemCreated,
            category: AuditCategory::Content,
            metadata: [
                'content_public_id' => (string) Str::uuid(),
                'version_number' => 1,
                'content_type' => 'article',
                'section_code' => 'education',
                'scope' => $scope,
                'university_code' => $universityCode,
                'to_status' => 'draft',
            ],
        );
    }

    private function university(string $code): University
    {
        return University::query()->create([
            'code' => $code,
            'name' => 'Universitas '.$code,
            'type' => 'universitas',
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?University $campus = null): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('code', $role)->value('id'),
            'university_id' => $campus?->id,
            'is_active' => true,
            'email' => Str::uuid().'@example.test',
        ]);
    }
}
