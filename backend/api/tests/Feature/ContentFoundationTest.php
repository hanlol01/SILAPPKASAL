<?php

namespace Tests\Feature;

use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ArticleVersionContent;
use App\Models\AuditLog;
use App\Models\ConsultationVersionContent;
use App\Models\ContentAttachment;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Models\FaqVersionContent;
use App\Models\FeaturedContent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentDocumentService;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentFoundationTest extends TestCase
{
    use RefreshDatabase;

    private University $campusA;

    private University $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(ContentFoundationSeeder::class);
        $this->campusA = $this->university('C1-A');
        $this->campusB = $this->university('C1-B');
    }

    public function test_storyboard_seed_is_complete_draft_only_idempotent_and_preserves_edits(): void
    {
        $this->assertSame(4, ContentSection::query()->count());
        $this->assertSame(10, ContentCategory::query()->whereNotNull('stable_seed_key')->count());
        $this->assertSame(41, ContentItem::query()->where('content_type', ContentType::Article)->count());
        $this->assertSame(8, ContentItem::query()->where('content_type', ContentType::Faq)->count());
        $this->assertSame(0, ContentItem::query()->where('content_type', ContentType::Consultation)->count());
        $this->assertSame(49, ContentVersion::query()->where('lifecycle_status', ContentLifecycleStatus::Draft)->count());
        $this->assertSame(49, ContentVersion::query()->where('requires_editorial_review', true)->count());
        $this->assertSame(0, ContentItem::query()->whereNotNull('published_version_id')->count());
        $this->assertSame(49, ContentVersion::query()->whereNotNull('seed_key')->distinct()->count('seed_key'));

        $edited = ContentVersion::query()->where('seed_key', 'storyboard.article.perspective_psychology_personal_boundaries.v1')->firstOrFail();
        $edited->update(['title' => 'Judul Editorial yang Dipertahankan']);
        $this->seed(ContentFoundationSeeder::class);

        $this->assertSame(41, ContentItem::query()->where('content_type', ContentType::Article)->count());
        $this->assertSame('Judul Editorial yang Dipertahankan', $edited->fresh()->title);
    }

    public function test_scope_invariants_and_authoring_permissions_are_enforced_server_side(): void
    {
        $adminA = $this->user('admin', $this->campusA);
        $adminB = $this->user('admin', $this->campusB);
        $reporter = $this->user('reporter', $this->campusA);
        $satgas = $this->user('satgas_ppks', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $payload = $this->articlePayload(ContentScope::Campus, $this->campusA);

        $item = $service->createDraft($adminA, $payload);
        $this->assertSame($this->campusA->id, $item->university_id);
        $this->assertSame('campus:'.$this->campusA->id, $item->scope_key);

        foreach ([$adminB, $reporter, $satgas] as $denied) {
            try {
                $service->createDraft($denied, $payload);
                $this->fail('Cross-campus or reader mutation was not denied.');
            } catch (HttpException|HttpResponseException $exception) {
                $this->assertTrue(true);
            }
        }

        try {
            $service->createDraft($adminA, $this->articlePayload(ContentScope::Global));
            $this->fail('Campus Admin was allowed to create global content.');
        } catch (HttpResponseException) {
            $this->assertTrue(true);
        }

        $global = $service->createDraft($super, $this->articlePayload(ContentScope::Global, null, 'Konten Global Super Admin'));
        $this->assertSame(ContentScope::Global, $global->scope);
        $this->assertNull($global->university_id);
    }

    public function test_model_scope_invariants_reject_inconsistent_university_values(): void
    {
        $section = ContentSection::query()->where('code', 'faq')->firstOrFail();

        foreach ([
            [ContentScope::Global, $this->campusA->id],
            [ContentScope::Campus, null],
        ] as [$scope, $universityId]) {
            try {
                ContentItem::query()->create([
                    'content_type' => ContentType::Faq,
                    'section_id' => $section->id,
                    'slug' => Str::uuid(),
                    'scope' => $scope,
                    'university_id' => $universityId,
                ]);
                $this->fail('Invalid scope/university combination was accepted.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_published_reader_uses_pointer_and_campus_scope_while_excluding_draft_and_archived_items(): void
    {
        $reader = $this->user('reporter', $this->campusA);
        $global = $this->publishedArticle(ContentScope::Global, null, 'Artikel Global');
        $own = $this->publishedArticle(ContentScope::Campus, $this->campusA, 'Artikel Kampus Sendiri');
        $other = $this->publishedArticle(ContentScope::Campus, $this->campusB, 'Artikel Kampus Lain');
        $archived = $this->publishedArticle(ContentScope::Global, null, 'Artikel Diarsipkan');
        $archived->forceFill(['archived_at' => now()])->save();
        $draft = $this->draftArticle(ContentScope::Global, null, 'Artikel Draf');

        Sanctum::actingAs($reader, ['*']);
        $response = $this->getJson('/api/v1/content/articles')->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $ids = collect($response->json('data'))->pluck('public_id');

        $this->assertTrue($ids->contains($global->public_id));
        $this->assertTrue($ids->contains($own->public_id));
        $this->assertFalse($ids->contains($other->public_id));
        $this->assertFalse($ids->contains($archived->public_id));
        $this->assertFalse($ids->contains($draft->public_id));
        $response->assertJsonMissing(['creator_id'])->assertJsonMissing(['published_version_id']);
    }

    public function test_old_published_version_remains_visible_while_revision_is_draft(): void
    {
        $reader = $this->user('satgas_ppks', $this->campusA);
        $item = $this->publishedArticle(ContentScope::Global, null, 'Versi Terbit');
        $draft = ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version_number' => 2,
            'lifecycle_status' => ContentLifecycleStatus::Draft,
            'title' => 'Versi Revisi Belum Terbit',
            'source_type' => 'revision',
        ]);
        ArticleVersionContent::query()->create([
            'content_version_id' => $draft->id,
            'document_json' => $this->document('Draf revisi.'),
            'sanitized_html' => '<p>Draf revisi.</p>',
            'search_text' => 'Draf revisi.',
            'estimated_reading_minutes' => 1,
        ]);
        $item->forceFill(['current_draft_version_id' => $draft->id])->save();

        Sanctum::actingAs($reader, ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Versi Terbit')
            ->assertJsonMissing(['Versi Revisi Belum Terbit']);
    }

    public function test_document_schema_rejects_h1_unsafe_nodes_and_unsafe_links_and_sanitizes_projection(): void
    {
        $documents = app(ContentDocumentService::class);
        $prepared = $documents->prepareArticle([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => '<script>alert(1)</script> Aman']],
            ]],
        ]);
        $this->assertStringNotContainsString('<script>', $prepared['html']);
        $this->assertStringContainsString('&lt;script&gt;', $prepared['html']);

        foreach ([
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'H1']]],
            ['type' => 'iframe', 'content' => []],
            ['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)'], 'content' => []],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'tautan', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
        ] as $node) {
            try {
                $documents->prepareArticle(['type' => 'doc', 'content' => [$node]]);
                $this->fail('Unsafe document input was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_faq_and_consultation_readers_return_only_published_verified_scope_safe_records(): void
    {
        $reader = $this->user('reporter', $this->campusA);
        $faq = $this->publishedFaq('Pertanyaan Terbit?');
        $this->draftFaq('Pertanyaan Draf?');
        $global = $this->publishedConsultation(ContentScope::Global, null, 'Layanan Global');
        $campus = $this->publishedConsultation(ContentScope::Campus, $this->campusA, 'Layanan Kampus');
        $this->publishedConsultation(ContentScope::Campus, $this->campusB, 'Layanan Kampus Lain');

        Sanctum::actingAs($reader, ['*']);
        $this->getJson('/api/v1/content/faqs')->assertOk()
            ->assertJsonFragment(['public_id' => $faq->public_id])
            ->assertJsonMissing(['Pertanyaan Draf?']);
        $consultation = $this->getJson('/api/v1/content/consultation')->assertOk();
        $this->assertSame($campus->public_id, $consultation->json('data.0.public_id'));
        $consultation->assertJsonFragment(['public_id' => $global->public_id])
            ->assertJsonMissing(['Layanan Kampus Lain']);
    }

    public function test_consultation_rejects_unsafe_urls_and_normalizes_phone_without_losing_leading_zero(): void
    {
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $base = [
            'content_type' => 'consultation',
            'section_code' => 'consultation',
            'scope' => 'global',
            'title' => 'Layanan Terverifikasi',
            'service_name' => 'Layanan Terverifikasi',
            'phone_display' => '0812 3456 7890',
            'verification_date' => now()->format('Y-m-d'),
            'verified_owner' => 'Pemilik Institusional',
        ];

        $item = $service->createDraft($super, $base + ['appointment_url' => 'https://example.org/janji']);
        $this->assertSame('081234567890', $item->currentDraftVersion->consultationContent->phone_normalized);

        $this->expectException(ValidationException::class);
        $service->createDraft($super, $base + [
            'title' => 'Layanan Tidak Aman',
            'appointment_url' => 'https://example.org/janji?registration=RAHASIA',
        ]);
    }

    public function test_attachment_validation_private_serialization_and_audit_redaction(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)->createDraft(
            $admin,
            $this->articlePayload(ContentScope::Campus, $this->campusA, 'Artikel Lampiran')
        );
        $version = $item->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $png = UploadedFile::fake()->createWithContent(
            'gambar rahasia.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );
        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Cover->value,
            'file' => $png,
            'alt_text' => 'Ilustrasi netral',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('content_attachments', 0);
        $this->assertSame([], Storage::disk('content')->allFiles());

        config()->set('content.attachments.image_uploads_enabled', true);
        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Cover->value,
            'file' => $png,
            'alt_text' => 'Ilustrasi netral',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $response = $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Attachment->value,
            'file' => UploadedFile::fake()->createWithContent('DEMO-001 dokumen rahasia.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated();

        $response->assertJsonMissingPath('data.storage_path')
            ->assertJsonMissingPath('data.checksum_sha256')
            ->assertJsonMissingPath('data.original_filename');
        $this->assertDatabaseCount('content_attachments', 1);
        $attachment = $version->attachments()->firstOrFail();
        Storage::disk('content')->assertExists($attachment->storage_path);

        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => 'attachment',
            'file' => UploadedFile::fake()->create('palsu.jpg', 10, 'application/pdf'),
        ])->assertUnprocessable();
        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => 'attachment',
            'file' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'),
        ])->assertUnprocessable();

        $auditJson = AuditLog::query()->where('action', 'content.attachment_uploaded')->firstOrFail()->toJson();
        $this->assertStringNotContainsString('DEMO-001 dokumen rahasia.pdf', $auditJson);
        $this->assertStringNotContainsString('Ilustrasi netral', $auditJson);
    }

    public function test_featured_content_accepts_only_rank_one_to_five_and_reader_is_campus_safe(): void
    {
        $reader = $this->user('reporter', $this->campusA);
        $creator = $this->user('super_admin');
        $own = $this->publishedArticle(ContentScope::Campus, $this->campusA, 'Unggulan Kampus');
        $other = $this->publishedArticle(ContentScope::Campus, $this->campusB, 'Unggulan Kampus Lain');
        $global = $this->publishedArticle(ContentScope::Global, null, 'Unggulan Global');
        FeaturedContent::query()->create(['scope' => 'campus', 'university_id' => $this->campusA->id, 'content_item_id' => $own->id, 'rank' => 1, 'creator_id' => $creator->id]);
        FeaturedContent::query()->create(['scope' => 'campus', 'university_id' => $this->campusB->id, 'content_item_id' => $other->id, 'rank' => 1, 'creator_id' => $creator->id]);
        FeaturedContent::query()->create(['scope' => 'global', 'university_id' => null, 'content_item_id' => $global->id, 'rank' => 1, 'creator_id' => $creator->id]);

        Sanctum::actingAs($reader, ['*']);
        $response = $this->getJson('/api/v1/content/featured')->assertOk();
        $response->assertJsonFragment(['public_id' => $own->public_id])
            ->assertJsonFragment(['public_id' => $global->public_id])
            ->assertJsonMissing(['Unggulan Kampus Lain']);

        $this->expectException(\InvalidArgumentException::class);
        FeaturedContent::query()->create(['scope' => 'global', 'content_item_id' => $global->id, 'rank' => 6, 'creator_id' => $creator->id]);
    }

    public function test_version_numbers_are_unique_and_published_versions_are_immutable(): void
    {
        $item = $this->publishedArticle(ContentScope::Global, null, 'Versi Imutabel');
        $published = $item->publishedVersion;

        try {
            ContentVersion::query()->create([
                'content_item_id' => $item->id,
                'version_number' => 1,
                'lifecycle_status' => 'draft',
                'title' => 'Duplikat',
            ]);
            $this->fail('Duplicate version number was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $published->update(['title' => 'Tidak Boleh Diubah']);
    }

    public function test_shared_review_publication_revision_and_archive_domain_preserves_privacy(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $reader = $this->user('reporter', $this->campusA);
        $service = app(ContentPublicationService::class);

        $revisionItem = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'Memerlukan Revisi'));
        $revisionItem = $service->submit($revisionItem->currentDraftVersion, $admin, (int) $revisionItem->lock_version);
        $service->startReview($revisionItem->currentDraftVersion->fresh(), $super, (int) $revisionItem->lock_version);
        try {
            $service->requestRevision($revisionItem->currentDraftVersion->fresh(), $super, '   ', (int) $revisionItem->fresh()->lock_version);
            $this->fail('A revision request without reason was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $service->requestRevision($revisionItem->currentDraftVersion->fresh(), $super, 'Perjelas sumber materi.', (int) $revisionItem->fresh()->lock_version);
        $this->assertSame(ContentLifecycleStatus::RevisionRequested, $revisionItem->currentDraftVersion->fresh()->lifecycle_status);
        $revisionItem = $service->updateDraft(
            $revisionItem->currentDraftVersion->fresh(),
            $admin,
            ['excerpt' => 'Ringkasan yang telah diperbaiki.'],
        );
        $this->assertSame(ContentLifecycleStatus::Draft, $revisionItem->currentDraftVersion->lifecycle_status);

        $approvedItem = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'Siap Diterbitkan'));
        $approvedItem = $service->submit($approvedItem->currentDraftVersion, $admin, (int) $approvedItem->lock_version);
        $approvedItem = $service->startReview($approvedItem->currentDraftVersion, $super, (int) $approvedItem->lock_version);
        $approvedItem = $service->approve($approvedItem->currentDraftVersion, $super, (int) $approvedItem->lock_version, 'Catatan persetujuan aman.');
        $approvedItem = $service->publishApproved($approvedItem->currentDraftVersion, $super, (int) $approvedItem->lock_version);
        $this->assertNotNull($approvedItem->published_version_id);
        $this->assertNull($approvedItem->current_draft_version_id);
        $this->assertSame(
            ['review_started', 'approved'],
            $approvedItem->publishedVersion->reviewDecisions()->orderBy('id')->get()
                ->map(fn ($decision) => $decision->decision_code->value)->all(),
        );

        Sanctum::actingAs($reader, ['*']);
        $this->getJson('/api/v1/content/articles/'.$approvedItem->public_id)->assertOk();
        $service->archive($approvedItem, $super, 'Materi tidak lagi berlaku.', (int) $approvedItem->lock_version);
        $this->getJson('/api/v1/content/articles/'.$approvedItem->public_id)->assertNotFound();

        $audit = AuditLog::query()->where('action', 'content.revision_requested')->firstOrFail()->toJson();
        $this->assertStringNotContainsString('Perjelas sumber materi.', $audit);
        $archiveAudit = AuditLog::query()->where('action', 'content.archived')->firstOrFail()->toJson();
        $this->assertStringNotContainsString('Materi tidak lagi berlaku.', $archiveAudit);
    }

    public function test_active_featured_rejects_drafts_and_inactive_history_can_reuse_rank(): void
    {
        $creator = $this->user('super_admin');
        $draft = $this->draftArticle(ContentScope::Global, null, 'Draf Unggulan');
        try {
            FeaturedContent::query()->create([
                'scope' => 'global', 'content_item_id' => $draft->id, 'rank' => 1,
                'is_active' => true, 'creator_id' => $creator->id,
            ]);
            $this->fail('Draft Article was accepted as active featured content.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $first = $this->publishedArticle(ContentScope::Global, null, 'Unggulan Lama');
        FeaturedContent::query()->create([
            'scope' => 'global', 'content_item_id' => $first->id, 'rank' => 1,
            'is_active' => false, 'creator_id' => $creator->id,
        ]);
        $second = $this->publishedArticle(ContentScope::Global, null, 'Unggulan Baru');
        FeaturedContent::query()->create([
            'scope' => 'global', 'content_item_id' => $second->id, 'rank' => 1,
            'is_active' => true, 'creator_id' => $creator->id,
        ]);

        $this->assertSame(2, FeaturedContent::query()->where('rank', 1)->count());
        $this->assertSame(1, FeaturedContent::query()->where('rank', 1)->where('is_active', true)->count());
    }

    public function test_content_rbac_reconciliation_is_idempotent_and_reader_roles_have_no_mutations(): void
    {
        $migration = require database_path('migrations/2026_07_21_010000_reconcile_content_permissions.php');
        $migration->up();
        $migration->up();

        $this->assertSame(12, Permission::query()->where('code', 'like', 'content.%')->count());
        $reporter = Role::query()->where('code', 'reporter')->with('permissions')->firstOrFail();
        $satgas = Role::query()->where('code', 'satgas_ppks')->with('permissions')->firstOrFail();
        $admin = Role::query()->where('code', 'admin')->with('permissions')->firstOrFail();
        $super = Role::query()->where('code', 'super_admin')->with('permissions')->firstOrFail();

        foreach ([$reporter, $satgas] as $reader) {
            $this->assertSame(['content.read.published'], $reader->permissions
                ->where('module', 'Konten')->pluck('code')->sort()->values()->all());
        }
        $this->assertTrue($admin->permissions->contains('code', 'content.create.campus'));
        $this->assertFalse($admin->permissions->contains('code', 'content.publish.global'));
        $this->assertTrue($super->permissions->contains('code', 'content.review'));
        $this->assertTrue($super->permissions->contains('code', 'content.publish.global'));
        $this->assertFalse($super->permissions->contains('code', 'content.create.campus'));
    }

    public function test_reader_search_escapes_wildcards_and_paginates_inside_scope(): void
    {
        $reader = $this->user('reporter', $this->campusA);
        $visible = $this->publishedArticle(ContentScope::Global, null, 'Judul Aman Dicari');
        $this->publishedArticle(ContentScope::Campus, $this->campusB, 'Judul Kampus Lain Dicari');
        Sanctum::actingAs($reader, ['*']);

        $this->getJson('/api/v1/content/articles?search=%25')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/content/articles?search=Judul+Aman&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $visible->public_id);
    }

    public function test_private_attachment_download_rechecks_published_scope_and_audits_access(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $otherAdmin = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $reader = $this->user('reporter', $this->campusA);
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'Lampiran Terbit'));
        $version = $item->currentDraftVersion;

        Sanctum::actingAs($admin, ['*']);
        $upload = $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => 'attachment',
            'file' => UploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated();
        $attachmentId = $upload->json('data.public_id');

        Sanctum::actingAs($otherAdmin, ['*']);
        $this->get('/api/v1/content/attachments/'.$attachmentId)->assertForbidden();

        $item = $service->submit($version->fresh(), $admin, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $super, (int) $item->lock_version);
        $service->publishApproved($item->currentDraftVersion, $super, (int) $item->lock_version);

        Sanctum::actingAs($reader, ['*']);
        $download = $this->get('/api/v1/content/attachments/'.$attachmentId)->assertOk();
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('lampiran-'.$attachmentId.'.pdf', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('dokumen.pdf', (string) $download->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.attachment_download_authorized',
            'actor_id' => $reader->id,
        ]);
    }

    public function test_published_typed_payload_is_immutable(): void
    {
        $published = $this->publishedArticle(ContentScope::Global, null, 'Payload Imutabel');
        $this->expectException(LogicException::class);
        $published->publishedVersion->articleContent->update(['search_text' => 'Perubahan terlarang']);
    }

    public function test_foreign_image_references_are_rejected_by_version_ownership(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $service = app(ContentPublicationService::class);
        $source = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'Sumber Gambar'));
        $target = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'Target Gambar'));
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $attachmentPublicId = (string) Str::uuid();
        $path = $source->currentDraftVersion->public_id.'/'.$attachmentPublicId.'.png';
        Storage::disk('content')->put($path, $bytes);
        ContentAttachment::query()->create([
            'public_id' => $attachmentPublicId,
            'content_version_id' => $source->currentDraftVersion->id,
            'purpose' => ContentAttachmentPurpose::InlineImage,
            'storage_disk' => 'content',
            'storage_path' => $path,
            'safe_filename' => 'gambar-'.$attachmentPublicId.'.png',
            'detected_mime' => 'image/png',
            'extension' => 'png',
            'file_size' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
            'width' => 1,
            'height' => 1,
            'uploader_id' => $admin->id,
        ]);

        $this->expectException(ValidationException::class);
        $service->updateDraft($target->currentDraftVersion, $admin, [
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'imageReference',
                    'attrs' => [
                        'attachment_public_id' => $attachmentPublicId,
                        'alt' => 'Gambar dari versi lain',
                    ],
                ]],
            ],
        ]);
    }

    public function test_published_campus_revision_is_owned_by_same_campus_admin_not_super_admin(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $item = $this->publishedArticle(ContentScope::Campus, $this->campusA, 'Revisi Kampus');
        $service = app(ContentPublicationService::class);

        try {
            $service->createRevision($item, $super, (int) $item->lock_version);
            $this->fail('Super Admin was allowed to author a campus revision.');
        } catch (HttpResponseException) {
            $this->assertTrue(true);
        }

        $revised = $service->createRevision($item, $admin, (int) $item->lock_version);
        $this->assertSame(2, $revised->currentDraftVersion->version_number);
        $this->assertSame($item->published_version_id, $revised->published_version_id);
    }

    public function test_super_admin_direct_global_publication_is_explicit_and_reader_safe(): void
    {
        $super = $this->user('super_admin');
        $reader = $this->user('reporter', $this->campusA);
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft(
            $super,
            $this->articlePayload(ContentScope::Global, null, 'Publikasi Global Langsung'),
        );
        $item = $service->directGlobalPublish($item->currentDraftVersion, $super, (int) $item->lock_version);

        $this->assertSame(ContentLifecycleStatus::Published, $item->publishedVersion->lifecycle_status);
        $this->assertSame(
            ['direct_global_published'],
            $item->publishedVersion->reviewDecisions->map(fn ($decision) => $decision->decision_code->value)->all(),
        );
        Sanctum::actingAs($reader, ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()
            ->assertJsonMissingPath('data.editorial_note')
            ->assertJsonMissingPath('data.review_decisions');
    }

    public function test_consultation_cta_is_published_and_scope_safe(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $reader = $this->user('reporter', $this->campusA);
        $globalCta = $this->publishedConsultation(ContentScope::Global, null, 'Konsultasi Global CTA');
        $foreignCta = $this->publishedConsultation(ContentScope::Campus, $this->campusB, 'Konsultasi Asing CTA');
        $service = app(ContentPublicationService::class);

        try {
            $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'CTA Asing') + [
                'consultation_cta_public_id' => $foreignCta->public_id,
            ]);
            $this->fail('A cross-campus Consultation CTA was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $item = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'CTA Aman') + [
            'consultation_cta_public_id' => $globalCta->public_id,
        ]);
        $item = $service->submit($item->currentDraftVersion, $admin, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->publishApproved($item->currentDraftVersion, $super, (int) $item->lock_version);

        Sanctum::actingAs($reader, ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.consultation_cta_public_id', $globalCta->public_id);

        $globalCta->forceFill(['archived_at' => now()])->save();
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.consultation_cta_public_id', null);

        $staleCta = $this->publishedConsultation(ContentScope::Global, null, 'CTA Menjadi Kedaluwarsa');
        $pending = $service->createDraft($admin, $this->articlePayload(ContentScope::Campus, $this->campusA, 'CTA Kedaluwarsa') + [
            'consultation_cta_public_id' => $staleCta->public_id,
        ]);
        $pending = $service->submit($pending->currentDraftVersion, $admin, (int) $pending->lock_version);
        $pending = $service->startReview($pending->currentDraftVersion, $super, (int) $pending->lock_version);
        $pending = $service->approve($pending->currentDraftVersion, $super, (int) $pending->lock_version);
        $staleCta->forceFill(['archived_at' => now()])->save();
        $this->expectException(ValidationException::class);
        $service->publishApproved($pending->currentDraftVersion, $super, (int) $pending->lock_version);
    }

    private function articlePayload(ContentScope $scope, ?University $campus = null, string $title = 'Artikel Uji'): array
    {
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();

        return [
            'content_type' => 'article',
            'section_code' => 'education',
            'category_public_id' => $category->public_id,
            'scope' => $scope->value,
            'university_id' => $campus?->id,
            'title' => $title.' '.Str::random(6),
            'excerpt' => 'Ringkasan netral untuk pengujian.',
            'document' => $this->document('Isi edukasi netral untuk pengujian.'),
        ];
    }

    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]]];
    }

    private function publishedArticle(ContentScope $scope, ?University $campus, string $title): ContentItem
    {
        return $this->content($scope, $campus, ContentType::Article, 'education', $title, true);
    }

    private function draftArticle(ContentScope $scope, ?University $campus, string $title): ContentItem
    {
        return $this->content($scope, $campus, ContentType::Article, 'education', $title, false);
    }

    private function publishedFaq(string $title): ContentItem
    {
        return $this->content(ContentScope::Global, null, ContentType::Faq, 'faq', $title, true);
    }

    private function draftFaq(string $title): ContentItem
    {
        return $this->content(ContentScope::Global, null, ContentType::Faq, 'faq', $title, false);
    }

    private function publishedConsultation(ContentScope $scope, ?University $campus, string $title): ContentItem
    {
        return $this->content($scope, $campus, ContentType::Consultation, 'consultation', $title, true);
    }

    private function content(ContentScope $scope, ?University $campus, ContentType $type, string $sectionCode, string $title, bool $published): ContentItem
    {
        $section = ContentSection::query()->where('code', $sectionCode)->firstOrFail();
        $category = $type === ContentType::Article
            ? ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail()
            : null;
        $item = ContentItem::query()->create([
            'content_type' => $type,
            'section_id' => $section->id,
            'category_id' => $category?->id,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'scope' => $scope,
            'university_id' => $campus?->id,
        ]);
        $version = ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version_number' => 1,
            'lifecycle_status' => ContentLifecycleStatus::Draft,
            'title' => $title,
            'excerpt' => 'Ringkasan aman.',
            'source_type' => 'test',
            'published_at' => null,
        ]);

        match ($type) {
            ContentType::Article => ArticleVersionContent::query()->create([
                'content_version_id' => $version->id,
                'document_json' => $this->document('Isi aman.'),
                'sanitized_html' => '<p>Isi aman.</p>',
                'search_text' => 'Isi aman.',
                'estimated_reading_minutes' => 1,
            ]),
            ContentType::Faq => FaqVersionContent::query()->create([
                'content_version_id' => $version->id,
                'question' => $title,
                'answer_document_json' => $this->document('Jawaban aman.'),
                'sanitized_answer_html' => '<p>Jawaban aman.</p>',
                'plain_search_text' => 'Jawaban aman.',
                'display_order' => 10,
            ]),
            ContentType::Consultation => ConsultationVersionContent::query()->create([
                'content_version_id' => $version->id,
                'service_name' => $title,
                'description' => 'Deskripsi layanan terverifikasi.',
                'is_active' => true,
                'verification_date' => now()->toDateString(),
                'verified_owner' => 'Pemilik Institusional',
            ]),
        };

        if ($published) {
            $version->forceFill([
                'lifecycle_status' => ContentLifecycleStatus::Published,
                'published_at' => now(),
            ])->save();
        }

        $item->forceFill($published
            ? ['published_version_id' => $version->id]
            : ['current_draft_version_id' => $version->id])->save();

        return $item->fresh(['publishedVersion.articleContent', 'publishedVersion.faqContent', 'publishedVersion.consultationContent']);
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
