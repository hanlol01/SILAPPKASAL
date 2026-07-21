<?php

namespace Tests\Feature;

use App\Enums\ContentScope;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentAttachmentService;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ContentManagementRepairTest extends TestCase
{
    use RefreshDatabase;

    private University $campusA;

    private University $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(ContentFoundationSeeder::class);
        $this->campusA = $this->university('C2-REPAIR-A');
        $this->campusB = $this->university('C2-REPAIR-B');
    }

    public function test_submit_requires_the_current_optimistic_lock_version(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload($this->campusA, 'Kunci Optimistis'));
        $versionId = $item->currentDraftVersion->public_id;
        $staleLock = (int) $item->lock_version;
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/content-management/versions/{$versionId}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_version');

        $this->patchJson("/api/v1/content-management/versions/{$versionId}", [
            'title' => 'Kunci Optimistis Diperbarui',
            'lock_version' => $staleLock,
        ])->assertOk();

        $stale = $this->postJson("/api/v1/content-management/versions/{$versionId}/submit", [
            'lock_version' => $staleLock,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_stale_version');
        $this->assertStringContainsString('no-store', (string) $stale->headers->get('Cache-Control'));
        $this->assertDatabaseHas('content_versions', [
            'public_id' => $versionId,
            'lifecycle_status' => 'draft',
        ]);

        $item->refresh();
        $this->postJson("/api/v1/content-management/versions/{$versionId}/submit", [
            'lock_version' => $item->lock_version,
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'submitted');
    }

    public function test_archived_content_rejects_every_admin_mutation_and_is_read_only(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $service = app(ContentPublicationService::class);
        $attachments = app(ContentAttachmentService::class);
        $item = $service->createDraft($admin, $this->articlePayload($this->campusA, 'Arsip Lama'));
        $attachment = $attachments->upload(
            $item->currentDraftVersion,
            $admin,
            $this->pdf('arsip.pdf'),
            ['purpose' => 'attachment'],
        );
        $storedPath = $attachment->storage_path;
        $item->forceFill(['archived_at' => now()])->save();
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.has_editable_version', false);
        $this->patchJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id, [
            'title' => 'Tidak Boleh',
            'lock_version' => $item->lock_version,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_archived');
        $this->postJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id.'/submit', [
            'lock_version' => $item->lock_version,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_archived');
        $this->postJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id.'/attachments', [
            'purpose' => 'attachment',
            'file' => $this->pdf('baru.pdf'),
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_archived');
        $this->deleteJson('/api/v1/content-management/attachments/'.$attachment->public_id)
            ->assertStatus(409)->assertJsonPath('error_code', 'content_archived');

        $this->assertDatabaseHas('content_attachments', ['public_id' => $attachment->public_id]);
        Storage::disk('content')->assertExists($storedPath);

        $published = $this->publishCampusArticle($admin, $this->user('super_admin'), 'Arsip Resmi');
        $published = $service->archive($published, $this->user('super_admin'), 'Sudah tidak berlaku.');
        $this->postJson('/api/v1/content-management/items/'.$published->public_id.'/revisions')
            ->assertStatus(409)->assertJsonPath('error_code', 'content_archived');
    }

    public function test_foreign_and_global_management_identifiers_are_indistinguishable_from_missing(): void
    {
        Storage::fake('content');
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $publication = app(ContentPublicationService::class);
        $attachments = app(ContentAttachmentService::class);
        $foreign = $publication->createDraft($adminB, $this->articlePayload($this->campusB, 'Kampus B'));
        $global = $publication->createDraft($super, $this->globalArticlePayload('Global'));
        $foreignAttachment = $attachments->upload(
            $foreign->currentDraftVersion,
            $adminB,
            $this->pdf('foreign.pdf'),
            ['purpose' => 'attachment'],
        );
        $globalAttachment = $attachments->upload(
            $global->currentDraftVersion,
            $super,
            $this->pdf('global.pdf'),
            ['purpose' => 'attachment'],
        );
        Sanctum::actingAs($adminA, ['*']);

        foreach ([[$foreign, $foreignAttachment], [$global, $globalAttachment]] as [$target, $attachment]) {
            $versionId = $target->currentDraftVersion->public_id;
            $this->getJson('/api/v1/content-management/items/'.$target->public_id)->assertNotFound();
            $this->postJson('/api/v1/content-management/items/'.$target->public_id.'/revisions')->assertNotFound();
            $this->patchJson('/api/v1/content-management/versions/'.$versionId, [
                'title' => 'Probe',
                'lock_version' => $target->lock_version,
            ])->assertNotFound();
            $this->postJson('/api/v1/content-management/versions/'.$versionId.'/submit', [
                'lock_version' => $target->lock_version,
            ])->assertNotFound();
            $this->postJson('/api/v1/content-management/versions/'.$versionId.'/attachments', [
                'purpose' => 'attachment',
                'file' => $this->pdf(Str::uuid().'.pdf'),
            ])->assertNotFound();
            $this->deleteJson('/api/v1/content-management/attachments/'.$attachment->public_id)
                ->assertNotFound();
        }
    }

    public function test_archive_refuses_to_silently_preserve_an_active_authoring_version(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $reviewer = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $this->publishCampusArticle($admin, $reviewer, 'Revisi Aktif');
        $item = $service->createRevision($item, $admin);
        $draftId = $item->current_draft_version_id;

        try {
            $service->archive($item, $reviewer, 'Tidak boleh menghilangkan draf aktif.');
            $this->fail('Archiving should fail while an authoring version is active.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame(
                'content_active_authoring_version',
                $exception->getResponse()->getData(true)['error_code'] ?? null,
            );
        }

        $item->refresh();
        $this->assertNull($item->archived_at);
        $this->assertSame($draftId, $item->current_draft_version_id);
    }

    public function test_attachment_metadata_and_audit_remain_when_private_storage_delete_fails(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload($this->campusA, 'Gagal Hapus'));
        $attachment = app(ContentAttachmentService::class)->upload(
            $item->currentDraftVersion,
            $admin,
            $this->pdf('gagal-hapus.pdf'),
            ['purpose' => 'attachment'],
        );
        $path = $attachment->storage_path;
        $realDisk = Storage::disk('content');
        $bytes = $realDisk->get($path);
        $failingDisk = Mockery::mock($realDisk);
        $failingDisk->shouldReceive('exists')->with($path)->twice()->andReturnTrue();
        $failingDisk->shouldReceive('get')->with($path)->once()->andReturn($bytes);
        $failingDisk->shouldReceive('delete')->with($path)->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('content')->andReturn($failingDisk);
        Sanctum::actingAs($admin, ['*']);

        $this->deleteJson('/api/v1/content-management/attachments/'.$attachment->public_id)
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'content_attachment_deletion_failed');

        $this->assertDatabaseHas('content_attachments', ['public_id' => $attachment->public_id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'content.attachment_removed',
            'actor_id' => $admin->id,
        ]);
        $this->assertTrue($realDisk->exists($path));
    }

    private function publishCampusArticle(User $admin, User $reviewer, string $title): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload($this->campusA, $title));
        $item = $service->submit($item->currentDraftVersion, $admin, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $reviewer);
        $item = $service->approve($item->currentDraftVersion, $reviewer);

        return $service->publishApproved($item->currentDraftVersion, $reviewer);
    }

    private function articlePayload(University $campus, string $title): array
    {
        return [
            'content_type' => 'article',
            'section_code' => 'education',
            'category_public_id' => ContentCategory::query()->where('code', 'perspective_psychology')->value('public_id'),
            'scope' => 'campus',
            'university_id' => $campus->id,
            'title' => $title,
            'excerpt' => 'Ringkasan aman.',
            'document' => $this->document('Isi artikel aman.'),
        ];
    }

    private function globalArticlePayload(string $title): array
    {
        $payload = $this->articlePayload($this->campusA, $title);
        $payload['scope'] = ContentScope::Global->value;
        $payload['university_id'] = null;

        return $payload;
    }

    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]]];
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF");
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
