<?php

namespace Tests\Feature;

use App\Enums\ContentScope;
use App\Models\AuditLog;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
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
        $this->getJson('/api/v1/content-management/items/'.$foreign->public_id)->assertNotFound();
        $this->getJson('/api/v1/content-management/items/'.$global->public_id)->assertNotFound();
    }

    public function test_admin_can_create_each_campus_type_but_readers_cannot_manage_content(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);

        $spoofedArticle = $this->articlePayload($this->campusB, 'Artikel C2');
        $spoofedArticle['scope'] = 'global';
        $this->postJson('/api/v1/content-management/items', $spoofedArticle)->assertForbidden();
        $article = $this->postJson(
            '/api/v1/content-management/items',
            $this->articlePayload($this->campusA, 'Artikel C2'),
        )
            ->assertCreated()
            ->assertJsonPath('data.scope', 'campus')
            ->assertJsonPath('data.university.code', $this->campusA->code)
            ->assertJsonPath('data.created_by.name', $admin->name)
            ->assertJsonPath('data.created_by.email', $admin->email)
            ->json('data');
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

        $this->assertDatabaseHas('content_items', [
            'public_id' => $article['public_id'],
            'scope' => 'campus',
            'university_id' => $this->campusA->id,
            'creator_id' => $admin->id,
        ]);
        $this->assertSame('faq', $faq['content_type']);
        $this->assertSame('consultation', $consultation['content_type']);

        foreach (['reporter', 'satgas_ppks'] as $role) {
            Sanctum::actingAs($this->user($role, $this->campusA), ['*']);
            $this->getJson('/api/v1/content-management/items')->assertForbidden();
            $this->postJson('/api/v1/content-management/items', $this->articlePayload($this->campusA, 'Terlarang'))->assertForbidden();
        }
    }

    public function test_article_api_rejects_malformed_json_and_persists_the_normalized_document(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);

        $malformed = $this->call(
            'POST',
            '/api/v1/content-management/items',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{"content_type":"article","document":',
        );
        $malformed->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation_failed');

        $payload = $this->articlePayload($this->campusA, 'Artikel Tiptap');
        $payload['document'] = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading_2', 'content' => [['type' => 'text', 'text' => 'Judul']]],
                ['type' => 'paragraph', 'content' => [[
                    'type' => 'text',
                    'text' => 'Garis bawah',
                    'marks' => [['type' => 'underline']],
                ]]],
                ['type' => 'divider'],
            ],
        ];

        $created = $this->postJson('/api/v1/content-management/items', $payload)
            ->assertCreated()
            ->json('data');
        $item = ContentItem::query()->where('public_id', $created['public_id'])->firstOrFail();
        $document = $item->currentDraftVersion()->firstOrFail()->articleContent()->firstOrFail()->document_json;

        $this->assertSame('heading', $document['content'][0]['type']);
        $this->assertSame(2, $document['content'][0]['attrs']['level']);
        $this->assertSame('underline', $document['content'][1]['content'][0]['marks'][0]['type']);
        $this->assertSame('horizontalRule', $document['content'][2]['type']);
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
        $item->refresh();
        $this->postJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id.'/submit', [
            'lock_version' => $item->lock_version,
        ])
            ->assertOk()->assertJsonPath('data.lifecycle_status', 'submitted');
        $this->patchJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id, ['title' => 'Tidak Boleh'])
            ->assertForbidden();

        $item->refresh();
        $item = $service->startReview($item->currentDraftVersion->fresh(), $super, (int) $item->lock_version);
        $item = $service->requestRevision($item->currentDraftVersion, $super, 'Tambahkan sumber yang telah diverifikasi.', (int) $item->lock_version);
        $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.lifecycle_status', 'revision_requested')
            ->assertJsonPath('data.review_feedback.reason', 'Tambahkan sumber yang telah diverifikasi.');

        $item = $service->updateDraft($item->currentDraftVersion, $admin, ['excerpt' => 'Ringkasan revisi.']);
        $item = $service->submit($item->currentDraftVersion, $admin, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->publishApproved($item->currentDraftVersion, $super, (int) $item->lock_version);

        Sanctum::actingAs($admin, ['*']);
        $this->patchJson('/api/v1/content-management/versions/'.$item->publishedVersion->public_id, ['title' => 'Tidak Boleh'])
            ->assertForbidden();
        $this->postJson('/api/v1/content-management/items/'.$item->public_id.'/revisions', [
            'lock_version' => $item->lock_version,
        ])
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

    public function test_article_consultation_cta_endpoint_is_not_active(): void
    {
        Sanctum::actingAs($this->user('admin', $this->campusA), ['*']);
        $this->getJson('/api/v1/content-management/consultation-options')->assertNotFound();
    }

    public function test_article_category_is_required_trimmed_and_suggested_without_cross_campus_leakage(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        Sanctum::actingAs($adminA, ['*']);
        $payload = $this->articlePayload($this->campusA, 'Kategori Baru');
        unset($payload['category_name']);
        $this->postJson('/api/v1/content-management/items', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('category_name');

        $payload['category_name'] = '  Kesehatan Kampus  ';
        $created = $this->postJson('/api/v1/content-management/items', $payload)
            ->assertCreated()->assertJsonPath('data.category_name', 'Kesehatan Kampus')->json('data');

        app(ContentPublicationService::class)->createDraft($adminB, [
            ...$this->articlePayload($this->campusB, 'Kategori Asing'),
            'category_name' => 'Kategori Rahasia Kampus B',
        ]);
        $this->getJson('/api/v1/content-management/article-categories?section=education')
            ->assertOk()->assertJsonFragment(['name' => 'Kesehatan Kampus'])
            ->assertJsonMissing(['Kategori Rahasia Kampus B']);
        $this->getJson('/api/v1/content-management/items?article_category='.urlencode('Kesehatan Kampus'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $created['public_id']);
    }

    public function test_category_registry_is_scope_safe_audited_and_blocks_deactivation_when_used(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);

        Sanctum::actingAs($adminA, ['*']);
        $campusCategory = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => '  Kesejahteraan Mahasiswa  ',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Kesejahteraan Mahasiswa')
            ->assertJsonPath('data.scope', 'campus')
            ->json('data');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.category_created',
            'actor_id' => $adminA->id,
        ]);

        Sanctum::actingAs($adminB, ['*']);
        $this->getJson('/api/v1/content-management/article-categories?section=education')
            ->assertOk()
            ->assertJsonMissing(['Kesejahteraan Mahasiswa']);
        $this->deleteJson('/api/v1/content-management/article-categories/'.$campusCategory['public_id'])
            ->assertNotFound();

        Sanctum::actingAs($adminA, ['*']);
        $this->postJson('/api/v1/content-management/items', [
            ...$this->articlePayload($this->campusA, 'Artikel Menggunakan Registry'),
            'category_name' => 'Kesejahteraan Mahasiswa',
        ])->assertCreated();

        $this->getJson('/api/v1/content-management/article-categories?section=education')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Kesejahteraan Mahasiswa',
                'usage_count' => 1,
                'can_deactivate' => false,
            ]);
        $this->deleteJson('/api/v1/content-management/article-categories/'.$campusCategory['public_id'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'content_category_in_use')
            ->assertJsonPath('data.usage_count', 1);
    }

    public function test_unused_categories_can_be_deactivated_with_role_appropriate_scope(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $campusCategory = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'policy',
            'name' => 'Peraturan Kampus Baru',
        ])->assertCreated()->json('data');
        $this->deleteJson('/api/v1/content-management/article-categories/'.$campusCategory['public_id'])
            ->assertOk();
        $this->assertDatabaseHas('content_categories', [
            'public_id' => $campusCategory['public_id'],
            'is_active' => false,
            'university_id' => $this->campusA->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.category_deactivated',
            'actor_id' => $admin->id,
        ]);

        $super = $this->user('super_admin');
        Sanctum::actingAs($super, ['*']);
        $globalCategory = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Edukasi Nasional Baru',
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'global')
            ->json('data');
        $this->deleteJson('/api/v1/content-management/article-categories/'.$globalCategory['public_id'])
            ->assertOk();

        foreach (['reporter', 'satgas_ppks'] as $role) {
            Sanctum::actingAs($this->user($role, $this->campusA), ['*']);
            $this->getJson('/api/v1/content-management/article-categories?section=education')
                ->assertForbidden();
            $this->postJson('/api/v1/content-management/article-categories', [
                'section' => 'education', 'name' => 'Kategori Terlarang '.$role,
            ])->assertForbidden();
            $this->deleteJson('/api/v1/content-management/article-categories/'.$globalCategory['public_id'])
                ->assertForbidden();
        }
    }

    public function test_global_category_deactivation_counts_campus_usage(): void
    {
        $super = $this->user('super_admin');
        Sanctum::actingAs($super, ['*']);
        $category = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Global Terpakai',
        ])->assertCreated()->json('data');

        $admin = $this->user('admin', $this->campusA);
        app(ContentPublicationService::class)->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Pemakai Kategori Global'),
            'category_name' => 'Kategori Global Terpakai',
        ]);

        Sanctum::actingAs($super, ['*']);
        $this->deleteJson('/api/v1/content-management/article-categories/'.$category['public_id'])
            ->assertStatus(409)
            ->assertJsonPath('data.usage_count', 1);
    }

    public function test_category_registry_validates_section_name_and_preserves_legacy_fallback(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'faq', 'name' => 'Tidak Valid',
        ])->assertUnprocessable()->assertJsonValidationErrors('section');
        $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education', 'name' => '---',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $legacy = app(ContentPublicationService::class)->createDraft(
            $admin,
            $this->articlePayload($this->campusA, 'Artikel Fallback Legacy'),
        );
        $legacyCategory = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();
        $legacy->forceFill([
            'category_id' => $legacyCategory->id,
            'category_name' => null,
        ])->save();

        $this->getJson('/api/v1/content-management/article-categories?section=education')
            ->assertOk()
            ->assertJsonFragment(['name' => $legacyCategory->name]);
    }

    public function test_category_name_is_canonical_when_legacy_category_id_is_stale(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);

        $categoryA = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Canonical A',
        ])->assertCreated()->json('data');
        $categoryB = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Canonical B',
        ])->assertCreated()->json('data');

        $item = app(ContentPublicationService::class)->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Pindah Kategori'),
            'category_public_id' => $categoryA['public_id'],
            'category_name' => $categoryB['name'],
        ]);
        $this->assertSame($categoryB['name'], $item->category_name);
        $this->assertNull($item->category_id);

        $categoryAModel = ContentCategory::query()->where('public_id', $categoryA['public_id'])->firstOrFail();
        $item->forceFill([
            'category_id' => $categoryAModel->id,
            'category_name' => $categoryB['name'],
        ])->save();

        $this->patchJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id, [
            'excerpt' => 'Ringkasan diperbarui tanpa menyentuh kategori.',
            'lock_version' => $item->lock_version,
        ])->assertOk()
            ->assertJsonPath('data.category_name', $categoryB['name'])
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.version.excerpt', 'Ringkasan diperbarui tanpa menyentuh kategori.');

        $item->refresh();
        $this->assertNull($item->category_id);

        $categories = collect(
            $this->getJson('/api/v1/content-management/article-categories?section=education')
                ->assertOk()
                ->json('data')
        );

        $this->assertSame(0, $categories->firstWhere('name', $categoryA['name'])['usage_count']);
        $this->assertTrue($categories->firstWhere('name', $categoryA['name'])['can_deactivate']);
        $this->assertSame(1, $categories->firstWhere('name', $categoryB['name'])['usage_count']);

        $this->deleteJson('/api/v1/content-management/article-categories/'.$categoryA['public_id'])
            ->assertOk();
        $this->assertDatabaseHas('content_categories', [
            'public_id' => $categoryA['public_id'],
            'is_active' => false,
        ]);
        $this->assertNull($item->fresh()->category_id);
    }

    public function test_legacy_null_category_name_uses_category_id_for_usage_and_deactivation(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $category = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Legacy Null',
        ])->assertCreated()->json('data');

        $item = app(ContentPublicationService::class)->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Legacy Null'),
            'category_public_id' => $category['public_id'],
            'category_name' => $category['name'],
        ]);
        $categoryModel = ContentCategory::query()->where('public_id', $category['public_id'])->firstOrFail();
        $item->forceFill([
            'category_id' => $categoryModel->id,
            'category_name' => null,
        ])->save();
        $item->currentDraftVersion->forceFill([
            'category_id' => $categoryModel->id,
            'category_name' => null,
        ])->save();

        $this->getJson('/api/v1/content-management/article-categories?section=education')
            ->assertOk()
            ->assertJsonFragment([
                'name' => $category['name'],
                'usage_count' => 1,
                'can_deactivate' => false,
            ]);
        $this->deleteJson('/api/v1/content-management/article-categories/'.$category['public_id'])
            ->assertStatus(409)
            ->assertJsonPath('data.usage_count', 1);
    }

    public function test_normalized_duplicate_returns_existing_metadata_and_database_constraint_blocks_races(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $created = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => '  Dukungan   Kampus  ',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Dukungan Kampus')
            ->assertJsonPath('data.result', 'created')
            ->json('data');

        app(ContentPublicationService::class)->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Duplicate Metadata'),
            'category_public_id' => $created['public_id'],
            'category_name' => $created['name'],
        ]);

        $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'DUKUNGAN KAMPUS',
        ])->assertOk()
            ->assertJsonPath('data.public_id', $created['public_id'])
            ->assertJsonPath('data.result', 'existing')
            ->assertJsonPath('data.usage_count', 1)
            ->assertJsonPath('data.can_deactivate', false);

        $stored = ContentCategory::query()->where('public_id', $created['public_id'])->firstOrFail();
        $this->expectException(QueryException::class);
        ContentCategory::query()->create([
            'section_id' => $stored->section_id,
            'code' => 'education-duplicate-race-'.Str::lower(Str::random(8)),
            'name' => "  dukungan\t kampus ",
            'slug' => 'dukungan-race-'.Str::lower(Str::random(8)),
            'display_order' => 999,
            'scope' => ContentScope::Campus,
            'university_id' => $this->campusA->id,
            'is_active' => true,
            'creator_id' => $admin->id,
        ]);
    }

    public function test_duplicate_post_reactivates_unused_category_with_200(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $created = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'policy',
            'name' => 'Kategori Untuk Reaktivasi',
        ])->assertCreated()->json('data');
        $this->deleteJson('/api/v1/content-management/article-categories/'.$created['public_id'])
            ->assertOk();

        $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'policy',
            'name' => ' kategori   untuk reaktivasi ',
        ])->assertOk()
            ->assertJsonPath('data.public_id', $created['public_id'])
            ->assertJsonPath('data.result', 'reactivated')
            ->assertJsonPath('data.usage_count', 0)
            ->assertJsonPath('data.can_deactivate', true);

        $audit = AuditLog::query()
            ->where('action', 'content.category_created')
            ->where('actor_id', $admin->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('reactivated', $audit->metadata['result'] ?? null);
    }

    public function test_same_category_name_is_allowed_across_global_and_campus_scopes_in_any_order(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');

        Sanctum::actingAs($super, ['*']);
        $globalFirst = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Dukungan Lintas Scope Satu',
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'global')
            ->json('data');

        Sanctum::actingAs($adminA, ['*']);
        $campusAfterGlobal = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => ' dukungan   lintas scope satu ',
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'campus')
            ->json('data');
        $this->assertNotSame($globalFirst['public_id'], $campusAfterGlobal['public_id']);

        $campusFirst = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'policy',
            'name' => 'Dukungan Lintas Scope Dua',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($super, ['*']);
        $globalAfterCampus = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'policy',
            'name' => 'DUKUNGAN LINTAS SCOPE DUA',
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'global')
            ->json('data');
        $this->assertNotSame($campusFirst['public_id'], $globalAfterCampus['public_id']);

        Sanctum::actingAs($adminA, ['*']);
        $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'DUKUNGAN LINTAS SCOPE SATU',
        ])->assertOk()
            ->assertJsonPath('data.public_id', $campusAfterGlobal['public_id'])
            ->assertJsonPath('data.result', 'existing');

        Sanctum::actingAs($super, ['*']);
        $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Dukungan Lintas Scope Satu',
        ])->assertOk()
            ->assertJsonPath('data.public_id', $globalFirst['public_id'])
            ->assertJsonPath('data.result', 'existing');

        Sanctum::actingAs($adminB, ['*']);
        $campusBCategory = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Dukungan Lintas Scope Satu',
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'campus')
            ->json('data');
        $this->assertNotSame($campusAfterGlobal['public_id'], $campusBCategory['public_id']);
    }

    public function test_published_article_category_is_isolated_until_its_revision_is_published(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $reporter = $this->user('reporter', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $categoryA = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Revisi A',
        ])->assertCreated()->json('data');
        $categoryB = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Revisi B',
        ])->assertCreated()->json('data');

        $item = app(ContentPublicationService::class)->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Publik Kategori A'),
            'category_public_id' => $categoryA['public_id'],
            'category_name' => $categoryA['name'],
            'excerpt' => 'Ringkasan versi A.',
            'document' => $this->document('Isi versi A.'),
        ]);
        $item = $this->publishCampusItem($item, $admin, $super);
        $publishedVersionId = $item->published_version_id;
        $sectionId = $item->section_id;
        $scope = $item->scope;
        $universityId = $item->university_id;
        $detailUri = '/api/v1/content/articles/slug/education/'.$item->slug;

        Sanctum::actingAs($reporter, ['*']);
        $this->getJson($detailUri)->assertOk()
            ->assertJsonPath('data.category_name', $categoryA['name'])
            ->assertJsonPath('data.title', 'Artikel Publik Kategori A')
            ->assertJsonPath('data.body.content.0.content.0.text', 'Isi versi A.')
            ->assertJsonPath('data.related_articles', []);
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode($categoryA['name']))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $item->public_id)
            ->assertJsonPath('data.0.category_name', $categoryA['name']);
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode($categoryB['name']))
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/content/article-categories?section=education')
            ->assertOk()->assertJsonPath('data', [$categoryA['name']]);

        Sanctum::actingAs($admin, ['*']);
        $revision = $this->postJson('/api/v1/content-management/items/'.$item->public_id.'/revisions', [
            'lock_version' => $item->lock_version,
        ])->assertCreated()->json('data');

        $this->patchJson('/api/v1/content-management/versions/'.$revision['version']['public_id'], [
            'category_name' => $categoryB['name'],
            'category_public_id' => $categoryA['public_id'],
            'title' => 'Artikel Publik Kategori B',
            'excerpt' => 'Ringkasan versi B.',
            'document' => $this->document('Isi versi B.'),
            'lock_version' => $revision['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.category_name', $categoryB['name'])
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.version.title', 'Artikel Publik Kategori B')
            ->assertJsonPath('data.lifecycle_status', 'draft');

        $item->refresh();
        $this->assertNull($item->category_id);
        $this->assertSame($categoryB['name'], $item->category_name);
        $this->assertSame($publishedVersionId, $item->published_version_id);
        $this->assertSame($sectionId, $item->section_id);
        $this->assertSame($scope, $item->scope);
        $this->assertSame($universityId, $item->university_id);
        $this->assertDatabaseHas('content_versions', [
            'id' => $publishedVersionId,
            'lifecycle_status' => 'published',
            'category_name' => $categoryA['name'],
        ]);
        $this->assertDatabaseHas('content_versions', [
            'id' => $item->current_draft_version_id,
            'lifecycle_status' => 'draft',
            'category_name' => $categoryB['name'],
            'category_id' => null,
        ]);

        $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.category_name', $categoryB['name'])
            ->assertJsonPath('data.version.title', 'Artikel Publik Kategori B');
        $usageBeforePublish = collect(
            $this->getJson('/api/v1/content-management/article-categories?section=education')
                ->assertOk()->json('data')
        );
        $this->assertSame(1, $usageBeforePublish->firstWhere('name', $categoryA['name'])['usage_count']);
        $this->assertSame(1, $usageBeforePublish->firstWhere('name', $categoryB['name'])['usage_count']);

        Sanctum::actingAs($reporter, ['*']);
        $this->getJson($detailUri)->assertOk()
            ->assertJsonPath('data.category_name', $categoryA['name'])
            ->assertJsonPath('data.title', 'Artikel Publik Kategori A')
            ->assertJsonPath('data.body.content.0.content.0.text', 'Isi versi A.')
            ->assertJsonPath('data.related_articles', []);
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode($categoryA['name']))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $item->public_id);
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode($categoryB['name']))
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/content/article-categories?section=education')
            ->assertOk()->assertJsonPath('data', [$categoryA['name']]);

        $item = $this->publishCampusItem($item->fresh(['currentDraftVersion']), $admin, $super);
        $this->assertNotSame($publishedVersionId, $item->published_version_id);
        $this->assertSame($categoryB['name'], $item->category_name);
        $this->assertNull($item->category_id);
        $this->assertDatabaseHas('content_versions', [
            'id' => $publishedVersionId,
            'lifecycle_status' => 'published',
            'category_name' => $categoryA['name'],
        ]);

        Sanctum::actingAs($reporter, ['*']);
        $this->getJson($detailUri)->assertOk()
            ->assertJsonPath('data.category_name', $categoryB['name'])
            ->assertJsonPath('data.title', 'Artikel Publik Kategori B')
            ->assertJsonPath('data.body.content.0.content.0.text', 'Isi versi B.');
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode($categoryB['name']))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $item->public_id)
            ->assertJsonPath('data.0.category_name', $categoryB['name']);
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode($categoryA['name']))
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/content/article-categories?section=education')
            ->assertOk()->assertJsonPath('data', [$categoryB['name']]);

        Sanctum::actingAs($admin, ['*']);
        $usageAfterPublish = collect(
            $this->getJson('/api/v1/content-management/article-categories?section=education')
                ->assertOk()->json('data')
        );
        $this->assertSame(0, $usageAfterPublish->firstWhere('name', $categoryA['name'])['usage_count']);
        $this->assertSame(1, $usageAfterPublish->firstWhere('name', $categoryB['name'])['usage_count']);
        $this->deleteJson('/api/v1/content-management/article-categories/'.$categoryA['public_id'])
            ->assertOk();
    }

    public function test_related_articles_follow_the_published_category_pointer(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $reporter = $this->user('reporter', $this->campusA);
        $service = app(ContentPublicationService::class);

        Sanctum::actingAs($admin, ['*']);
        $categoryA = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Related A',
        ])->assertCreated()->json('data');
        $categoryB = $this->postJson('/api/v1/content-management/article-categories', [
            'section' => 'education',
            'name' => 'Kategori Related B',
        ])->assertCreated()->json('data');

        $relatedA = $this->publishCampusItem($service->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Related A'),
            'category_name' => $categoryA['name'],
        ]), $admin, $super);
        $relatedB = $this->publishCampusItem($service->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Related B'),
            'category_name' => $categoryB['name'],
        ]), $admin, $super);
        $target = $this->publishCampusItem($service->createDraft($admin, [
            ...$this->articlePayload($this->campusA, 'Artikel Target Related'),
            'category_name' => $categoryA['name'],
        ]), $admin, $super);
        $detailUri = '/api/v1/content/articles/slug/education/'.$target->slug;

        Sanctum::actingAs($reporter, ['*']);
        $relatedBeforeRevision = collect(
            $this->getJson($detailUri)->assertOk()->json('data.related_articles')
        )->pluck('public_id');
        $this->assertTrue($relatedBeforeRevision->contains($relatedA->public_id));
        $this->assertFalse($relatedBeforeRevision->contains($relatedB->public_id));

        Sanctum::actingAs($admin, ['*']);
        $revision = $this->postJson('/api/v1/content-management/items/'.$target->public_id.'/revisions', [
            'lock_version' => $target->lock_version,
        ])->assertCreated()->json('data');
        $this->patchJson('/api/v1/content-management/versions/'.$revision['version']['public_id'], [
            'category_name' => $categoryB['name'],
            'lock_version' => $revision['lock_version'],
        ])->assertOk();

        Sanctum::actingAs($reporter, ['*']);
        $relatedWhileDraft = collect(
            $this->getJson($detailUri)->assertOk()->json('data.related_articles')
        )->pluck('public_id');
        $this->assertTrue($relatedWhileDraft->contains($relatedA->public_id));
        $this->assertFalse($relatedWhileDraft->contains($relatedB->public_id));

        $target = $this->publishCampusItem($target->fresh(['currentDraftVersion']), $admin, $super);

        Sanctum::actingAs($reporter, ['*']);
        $relatedAfterPublish = collect(
            $this->getJson($detailUri)->assertOk()->json('data.related_articles')
        )->pluck('public_id');
        $this->assertFalse($relatedAfterPublish->contains($relatedA->public_id));
        $this->assertTrue($relatedAfterPublish->contains($relatedB->public_id));
    }

    public function test_new_information_center_get_routes_have_explicit_throttles(): void
    {
        foreach ([
            '/api/v1/content/article-categories?section=education',
            '/api/v1/content/articles/slug/education/example-slug',
            '/api/v1/content-management/capabilities',
            '/api/v1/content-management/article-categories?section=education',
        ] as $uri) {
            $route = app('router')->getRoutes()->match(Request::create($uri, 'GET'));
            $this->assertContains('throttle:60,1', $route->gatherMiddleware(), $uri);
        }

        foreach ([
            ['POST', '/api/v1/content-management/article-categories'],
            ['DELETE', '/api/v1/content-management/article-categories/'.Str::uuid()],
        ] as [$method, $uri]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $this->assertContains('throttle:30,1', $route->gatherMiddleware(), $uri);
        }
    }

    public function test_consultation_configuration_fields_are_preserved_and_article_cta_is_absent(): void
    {
        $admin = $this->user('admin', $this->campusA);
        Sanctum::actingAs($admin, ['*']);
        $created = $this->postJson('/api/v1/content-management/items', [
            ...$this->consultationPayload(ContentScope::Campus, $this->campusA, 'Konsultasi Terstruktur'),
            'service_type' => 'Pendampingan awal',
            'procedure' => "Hubungi kanal resmi.\nTentukan jadwal.",
            'confidentiality_info' => 'Informasi ditangani sesuai kebijakan institusi.',
        ])->assertCreated()->json('data');

        $this->getJson('/api/v1/content-management/items/'.$created['public_id'])
            ->assertOk()
            ->assertJsonPath('data.version.consultation.service_type', 'Pendampingan awal')
            ->assertJsonPath('data.version.consultation.procedure', "Hubungi kanal resmi.\nTentukan jadwal.")
            ->assertJsonPath('data.version.consultation.confidentiality_info', 'Informasi ditangani sesuai kebijakan institusi.')
            ->assertJsonMissingPath('data.version.article.consultation_cta_public_id');
    }

    public function test_management_reports_image_upload_capability_fail_closed(): void
    {
        Sanctum::actingAs($this->user('admin', $this->campusA), ['*']);
        $this->getJson('/api/v1/content-management/capabilities')
            ->assertOk()->assertJsonPath('data.image_upload_available', false);
    }

    public function test_reporter_article_categories_are_unique_published_scoped_and_filterable(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        foreach ([['Batasan Diri', 'Artikel Satu'], ['Batasan Diri', 'Artikel Dua'], ['Dukungan Kampus', 'Artikel Tiga']] as [$category, $title]) {
            $item = $service->createDraft($adminA, [...$this->articlePayload($this->campusA, $title), 'category_name' => $category]);
            $this->publishCampusItem($item, $adminA, $super);
        }
        $foreign = $service->createDraft($adminB, [...$this->articlePayload($this->campusB, 'Artikel Asing'), 'category_name' => 'Kategori Kampus B']);
        $this->publishCampusItem($foreign, $adminB, $super);

        Sanctum::actingAs($this->user('reporter', $this->campusA), ['*']);
        $this->getJson('/api/v1/content/article-categories?section=education')
            ->assertOk()
            ->assertJsonPath('data', ['Batasan Diri', 'Dukungan Kampus'])
            ->assertJsonMissing(['Kategori Kampus B']);
        $this->getJson('/api/v1/content/articles?section=education&article_category='.urlencode('Batasan Diri'))
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.category_name', 'Batasan Diri');
    }

    public function test_information_center_extension_migration_rolls_back_reapplies_and_backfills_legacy_category(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)->createDraft($admin, $this->articlePayload($this->campusA, 'Artikel Legacy'));
        $legacyCategory = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();
        $item->forceFill([
            'category_id' => $legacyCategory->id,
            'category_name' => null,
        ])->save();
        $migration = require database_path('migrations/2026_07_22_000000_extend_content_for_reporter_information_center.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('content_items', 'category_name'));
        $this->assertFalse(Schema::hasColumn('consultation_version_contents', 'procedure'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('content_items', 'category_name'));
        $this->assertTrue(Schema::hasColumn('consultation_version_contents', 'procedure'));
        $this->assertSame(
            $legacyCategory->name,
            ContentItem::query()->whereKey($item->id)->value('category_name'),
        );
    }

    public function test_normalized_category_migration_fails_before_schema_mutation_for_invalid_scope_key(): void
    {
        $migration = require database_path('migrations/2026_07_23_010000_add_normalized_name_to_content_categories.php');
        $category = ContentCategory::query()->where('scope', ContentScope::Global->value)->firstOrFail();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('content_categories', 'normalized_name'));

        DB::table('content_categories')->where('id', $category->id)->update([
            'scope_key' => 'campus:999999',
        ]);

        try {
            $migration->up();
            $this->fail('The migration must reject an invalid scope_key before changing the schema.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid scope_key', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('content_categories', 'normalized_name'));
        DB::table('content_categories')->where('id', $category->id)->update([
            'scope_key' => 'global',
        ]);

        $migration->up();
        $this->assertTrue(Schema::hasColumn('content_categories', 'normalized_name'));
    }

    public function test_versioned_category_migration_fails_before_schema_mutation_for_ambiguous_active_pointers(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $this->publishCampusItem(
            $service->createDraft($admin, $this->articlePayload($this->campusA, 'Backfill Ambigu')),
            $admin,
            $super,
        );
        $item = $service->createRevision($item, $admin, (int) $item->lock_version);
        $item = $service->updateDraft($item->currentDraftVersion, $admin, [
            'category_name' => 'Kategori Draft Ambigu',
            'title' => 'Backfill Ambigu Draft',
            'lock_version' => $item->lock_version,
        ]);
        $publishedVersionId = $item->published_version_id;
        $draftVersionId = $item->current_draft_version_id;
        $pointerBefore = DB::table('content_items')->where('id', $item->id)->first([
            'published_version_id',
            'current_draft_version_id',
            'category_name',
            'category_id',
        ]);
        $versionsBefore = DB::table('content_versions')
            ->whereIn('id', [$publishedVersionId, $draftVersionId])
            ->orderBy('id')
            ->get(['id', 'title', 'lifecycle_status'])
            ->toArray();
        $articleContentsBefore = DB::table('article_version_contents')
            ->whereIn('content_version_id', [$publishedVersionId, $draftVersionId])
            ->orderBy('content_version_id')
            ->get(['content_version_id', 'document_json', 'search_text'])
            ->toArray();
        $migration = require database_path('migrations/2026_07_23_020000_add_versioned_category_metadata_to_content_versions.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('content_versions', 'category_name'));
        $this->assertFalse(Schema::hasColumn('content_versions', 'category_id'));

        $exception = null;
        try {
            $migration->up();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('distinct published and current draft pointers', $exception->getMessage());
        $this->assertStringContainsString($item->public_id, $exception->getMessage());
        $this->assertFalse(Schema::hasColumn('content_versions', 'category_name'));
        $this->assertFalse(Schema::hasColumn('content_versions', 'category_id'));
        $this->assertEquals(
            $pointerBefore,
            DB::table('content_items')->where('id', $item->id)->first([
                'published_version_id',
                'current_draft_version_id',
                'category_name',
                'category_id',
            ]),
        );
        $this->assertEquals(
            $versionsBefore,
            DB::table('content_versions')
                ->whereIn('id', [$publishedVersionId, $draftVersionId])
                ->orderBy('id')
                ->get(['id', 'title', 'lifecycle_status'])
                ->toArray(),
        );
        $this->assertEquals(
            $articleContentsBefore,
            DB::table('article_version_contents')
                ->whereIn('content_version_id', [$publishedVersionId, $draftVersionId])
                ->orderBy('content_version_id')
                ->get(['content_version_id', 'document_json', 'search_text'])
                ->toArray(),
        );

        DB::table('content_items')->where('id', $item->id)->update(['current_draft_version_id' => null]);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('content_versions', 'category_name'));
        $this->assertTrue(Schema::hasColumn('content_versions', 'category_id'));
    }

    public function test_versioned_category_migration_backfills_only_the_published_pointer(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $item = $this->publishCampusItem(
            app(ContentPublicationService::class)->createDraft(
                $admin,
                $this->articlePayload($this->campusA, 'Backfill Published Pointer'),
            ),
            $admin,
            $super,
        );
        $historical = ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version_number' => 2,
            'lifecycle_status' => 'rejected',
            'title' => 'Historical Non Pointer',
            'author_id' => $admin->id,
            'editor_id' => $admin->id,
            'source_type' => 'manual',
        ]);
        $migration = require database_path('migrations/2026_07_23_020000_add_versioned_category_metadata_to_content_versions.php');

        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('content_versions', [
            'id' => $item->published_version_id,
            'category_name' => $item->category_name,
            'category_id' => null,
        ]);
        $this->assertDatabaseHas('content_versions', [
            'id' => $historical->id,
            'category_name' => null,
            'category_id' => null,
        ]);
        $this->assertSame($item->published_version_id, ContentItem::query()->findOrFail($item->id)->published_version_id);
    }

    public function test_versioned_category_migration_backfills_only_the_current_draft_pointer(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();
        $draft = app(ContentPublicationService::class)->createDraft(
            $admin,
            $this->articlePayload($this->campusA, 'Backfill Draft Pointer'),
        );
        $draft->forceFill([
            'category_name' => null,
            'category_id' => $category->id,
        ])->save();
        $historical = ContentVersion::query()->create([
            'content_item_id' => $draft->id,
            'version_number' => 2,
            'lifecycle_status' => 'rejected',
            'title' => 'Historical Draft Non Pointer',
            'author_id' => $admin->id,
            'editor_id' => $admin->id,
            'source_type' => 'manual',
        ]);
        $migration = require database_path('migrations/2026_07_23_020000_add_versioned_category_metadata_to_content_versions.php');

        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('content_versions', [
            'id' => $draft->current_draft_version_id,
            'category_name' => null,
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('content_versions', [
            'id' => $historical->id,
            'category_name' => null,
            'category_id' => null,
        ]);
        $this->assertNull(ContentItem::query()->findOrFail($draft->id)->published_version_id);
    }

    public function test_versioned_category_migration_backfills_a_shared_active_pointer_once(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $item = $this->publishCampusItem(
            app(ContentPublicationService::class)->createDraft(
                $admin,
                $this->articlePayload($this->campusA, 'Backfill Shared Pointer'),
            ),
            $admin,
            $super,
        );
        DB::table('content_items')->where('id', $item->id)->update([
            'current_draft_version_id' => $item->published_version_id,
        ]);
        $migration = require database_path('migrations/2026_07_23_020000_add_versioned_category_metadata_to_content_versions.php');

        $migration->down();
        $migration->up();

        $reloaded = ContentItem::query()->findOrFail($item->id);
        $this->assertSame($reloaded->published_version_id, $reloaded->current_draft_version_id);
        $this->assertSame(1, ContentVersion::query()
            ->whereKey($reloaded->published_version_id)
            ->where('category_name', $item->category_name)
            ->whereNull('category_id')
            ->count());
    }

    public function test_revision_creation_fails_closed_when_published_category_metadata_is_missing(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $this->publishCampusItem(
            $service->createDraft(
                $admin,
                $this->articlePayload($this->campusA, 'Revision Missing Published Category'),
            ),
            $admin,
            $super,
        );
        $this->assertNotNull($item->category_name);
        DB::table('content_versions')->where('id', $item->published_version_id)->update([
            'category_name' => null,
            'category_id' => null,
        ]);
        $versionCount = ContentVersion::query()->where('content_item_id', $item->id)->count();

        $exception = null;
        try {
            $service->createRevision($item->fresh(), $admin, (int) $item->lock_version);
        } catch (ValidationException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertArrayHasKey('category_name', $exception->errors());
        $this->assertStringContainsString('cannot be created safely', $exception->errors()['category_name'][0]);
        $this->assertNull(ContentItem::query()->findOrFail($item->id)->current_draft_version_id);
        $this->assertSame($versionCount, ContentVersion::query()->where('content_item_id', $item->id)->count());
    }

    private function articlePayload(University $campus, string $title): array
    {
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();

        return [
            'content_type' => 'article', 'section_code' => 'education',
            'category_public_id' => $category->public_id, 'scope' => 'campus',
            'category_name' => $category->name,
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

    private function publishGlobalItem(ContentItem $item, User $author): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $reviewer = $this->user('super_admin');
        $item = $service->submit($item->currentDraftVersion, $author, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $reviewer, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $reviewer, (int) $item->lock_version);

        return $service->publishApproved($item->currentDraftVersion, $reviewer, (int) $item->lock_version);
    }

    private function publishCampusItem(ContentItem $item, User $admin, User $reviewer): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $item = $service->submit($item->currentDraftVersion, $admin, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $reviewer, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $reviewer, (int) $item->lock_version);

        return $service->publishApproved($item->currentDraftVersion, $reviewer, (int) $item->lock_version);
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
