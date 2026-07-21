<?php

namespace Tests\Feature;

use App\Enums\ContentScope;
use App\Models\ContentCategory;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    private University $campusA;

    private University $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(ContentFoundationSeeder::class);
        $this->campusA = $this->university('C2-A');
        $this->campusB = $this->university('C2-B');
    }

    public function test_admin_management_list_and_detail_are_strictly_own_campus(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $own = $service->createDraft($adminA, $this->articlePayload($this->campusA, 'Artikel Kampus A'));
        $foreign = $service->createDraft($adminB, $this->articlePayload($this->campusB, 'Artikel Kampus B'));
        $global = $service->createDraft($super, $this->globalArticlePayload('Draf Global'));

        Sanctum::actingAs($adminA, ['*']);
        $list = $this->getJson('/api/v1/content-management/items')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $own->public_id)
            ->assertJsonMissing(['Draf Global'])
            ->assertJsonMissing(['Artikel Kampus B']);
        $this->assertStringContainsString('no-store', (string) $list->headers->get('Cache-Control'));

        $this->getJson('/api/v1/content-management/items/'.$own->public_id)
            ->assertOk()
            ->assertJsonPath('data.version.article.document.type', 'doc')
            ->assertJsonMissingPath('data.version.article.sanitized_html');
        $this->getJson('/api/v1/content-management/items/'.$foreign->public_id)->assertForbidden();
        $this->getJson('/api/v1/content-management/items/'.$global->public_id)->assertForbidden();
    }

    public function test_admin_can_create_each_campus_type_but_readers_cannot_manage_content(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);

        $article = $this->postJson('/api/v1/content-management/items', $this->articlePayload($this->campusA, 'Artikel C2'))
            ->assertCreated()->json('data');
        $faq = $this->postJson('/api/v1/content-management/items', [
            'content_type' => 'faq', 'section_code' => 'faq', 'scope' => 'campus',
            'university_id' => $this->campusA->id, 'title' => 'Bagaimana mencari bantuan?',
            'question' => 'Bagaimana mencari bantuan?', 'answer_document' => $this->document('Hubungi layanan kampus yang terverifikasi.'),
            'display_order' => 10,
        ])->assertCreated()->json('data');
        $consultation = $this->postJson('/api/v1/content-management/items', [
            'content_type' => 'consultation', 'section_code' => 'consultation', 'scope' => 'campus',
            'university_id' => $this->campusA->id, 'title' => 'Layanan Konsultasi Kampus',
            'service_name' => 'Layanan Konsultasi Kampus', 'description' => 'Informasi layanan yang telah diverifikasi.',
            'appointment_url' => 'https://example.test/konsultasi', 'is_active' => true,
        ])->assertCreated()->json('data');

        $this->assertSame('campus', $article['scope']);
        $this->assertSame('faq', $faq['content_type']);
        $this->assertSame('consultation', $consultation['content_type']);

        foreach (['reporter', 'satgas_ppks'] as $role) {
            Sanctum::actingAs($this->user($role, $this->campusA), ['*']);
            $this->getJson('/api/v1/content-management/items')->assertForbidden();
            $this->postJson('/api/v1/content-management/items', $this->articlePayload($this->campusA, 'Terlarang'))->assertForbidden();
        }
    }

    public function test_edit_submit_revision_feedback_and_published_revision_rules_are_exposed_safely(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload($this->campusA, 'Alur Editorial'));

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id, [
            'title' => 'Alur Editorial Diperbarui', 'lock_version' => $item->lock_version,
        ])->assertOk()->assertJsonPath('data.version.title', 'Alur Editorial Diperbarui');
        $this->postJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id.'/submit')
            ->assertOk()->assertJsonPath('data.lifecycle_status', 'submitted');
        $this->patchJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id, ['title' => 'Tidak Boleh'])
            ->assertForbidden();

        $item = $service->startReview($item->currentDraftVersion->fresh(), $super);
        $item = $service->requestRevision($item->currentDraftVersion, $super, 'Tambahkan sumber yang telah diverifikasi.');
        $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.lifecycle_status', 'revision_requested')
            ->assertJsonPath('data.review_feedback.reason', 'Tambahkan sumber yang telah diverifikasi.');

        $item = $service->updateDraft($item->currentDraftVersion, $admin, ['excerpt' => 'Ringkasan revisi.']);
        $item = $service->submit($item->currentDraftVersion, $admin);
        $item = $service->startReview($item->currentDraftVersion, $super);
        $item = $service->approve($item->currentDraftVersion, $super);
        $item = $service->publishApproved($item->currentDraftVersion, $super);

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson('/api/v1/content-management/versions/'.$item->publishedVersion->public_id, ['title' => 'Tidak Boleh'])
            ->assertForbidden();
        $this->postJson('/api/v1/content-management/items/'.$item->public_id.'/revisions')
            ->assertCreated()
            ->assertJsonPath('data.lifecycle_status', 'draft')
            ->assertJsonPath('data.published_version.version_number', 1);
    }

    public function test_admin_can_upload_and_remove_only_pdf_from_editable_own_campus_version(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)->createDraft($admin, $this->articlePayload($this->campusA, 'Lampiran C2'));
        Sanctum::actingAs($admin, ['*']);

        $upload = $this->postJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id.'/attachments', [
            'purpose' => 'attachment',
            'file' => UploadedFile::fake()->createWithContent('nama-asli-rahasia.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated();
        $attachment = $upload->json('data');
        $this->assertStringNotContainsString('nama-asli-rahasia', $attachment['filename']);
        $this->assertArrayNotHasKey('storage_path', $attachment);

        $this->deleteJson('/api/v1/content-management/attachments/'.$attachment['public_id'])->assertOk();
        $this->assertDatabaseMissing('content_attachments', ['public_id' => $attachment['public_id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.attachment_removed', 'actor_id' => $admin->id]);
    }

    public function test_eligible_consultation_choices_are_published_active_and_scope_safe(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $global = $service->createDraft($super, $this->consultationPayload(ContentScope::Global, null, 'Global Aktif'));
        $global = $service->directGlobalPublish($global->currentDraftVersion, $super);
        $own = $service->createDraft($adminA, $this->consultationPayload(ContentScope::Campus, $this->campusA, 'Kampus Aktif'));
        $own = $service->submit($own->currentDraftVersion, $adminA);
        $own = $service->startReview($own->currentDraftVersion, $super);
        $own = $service->approve($own->currentDraftVersion, $super);
        $own = $service->publishApproved($own->currentDraftVersion, $super);
        $foreign = $service->createDraft($adminB, $this->consultationPayload(ContentScope::Campus, $this->campusB, 'Kampus Asing'));
        $foreign = $service->submit($foreign->currentDraftVersion, $adminB);
        $foreign = $service->startReview($foreign->currentDraftVersion, $super);
        $foreign = $service->approve($foreign->currentDraftVersion, $super);
        $service->publishApproved($foreign->currentDraftVersion, $super);

        Sanctum::actingAs($adminA, ['*']);
        $response = $this->getJson('/api/v1/content-management/consultation-options')->assertOk();
        $ids = collect($response->json('data'))->pluck('public_id');
        $this->assertTrue($ids->contains($global->public_id));
        $this->assertTrue($ids->contains($own->public_id));
        $this->assertFalse($ids->contains($foreign->public_id));
    }

    private function articlePayload(University $campus, string $title): array
    {
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();

        return [
            'content_type' => 'article', 'section_code' => 'education',
            'category_public_id' => $category->public_id, 'scope' => 'campus',
            'university_id' => $campus->id, 'title' => $title,
            'excerpt' => 'Ringkasan aman.', 'document' => $this->document('Isi artikel aman.'),
        ];
    }

    private function globalArticlePayload(string $title): array
    {
        $payload = $this->articlePayload($this->campusA, $title);
        $payload['scope'] = 'global';
        $payload['university_id'] = null;

        return $payload;
    }

    private function consultationPayload(ContentScope $scope, ?University $campus, string $title): array
    {
        return [
            'content_type' => 'consultation', 'section_code' => 'consultation',
            'scope' => $scope->value, 'university_id' => $campus?->id,
            'title' => $title, 'service_name' => $title, 'description' => 'Layanan terverifikasi.',
            'is_active' => true, 'verification_date' => now()->toDateString(),
            'verified_owner' => 'Pemilik Institusional',
        ];
    }

    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]],
        ]]];
    }

    private function university(string $code): University
    {
        return University::query()->create(['code' => $code, 'name' => 'Universitas '.$code, 'type' => 'universitas', 'is_active' => true]);
    }

    private function user(string $role, ?University $campus = null): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('code', $role)->value('id'),
            'university_id' => $campus?->id, 'is_active' => true,
            'email' => Str::uuid().'@example.test',
        ]);
    }
}
