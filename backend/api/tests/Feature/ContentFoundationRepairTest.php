<?php

namespace Tests\Feature;

use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ArticleVersionContent;
use App\Models\ConsultationVersionContent;
use App\Models\ContentAttachment;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Models\FaqVersionContent;
use App\Models\FeaturedContent;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentDocumentService;
use App\Services\ContentPublicationService;
use App\Services\TestDatabaseGuard;
use Carbon\CarbonImmutable;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ContentFoundationRepairTest extends TestCase
{
    use RefreshDatabase;

    private University $campusA;

    private University $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacSeeder::class, ContentFoundationSeeder::class]);
        $this->campusA = $this->university('REPAIR-A');
        $this->campusB = $this->university('REPAIR-B');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_revision_clones_private_attachments_rewrites_references_and_can_publish(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Revision attachment integrity'));
        $source = $item->currentDraftVersion;
        $cover = $this->attachment($source, $admin, ContentAttachmentPurpose::Cover, 'cover bytes', 'image/png', 'png');
        $inline = $this->attachment($source, $admin, ContentAttachmentPurpose::InlineImage, 'inline bytes', 'image/png', 'png');
        $general = $this->attachment($source, $admin, ContentAttachmentPurpose::Attachment, "%PDF-1.4\ngeneral", 'application/pdf', 'pdf');

        $service->updateDraft($source, $admin, ['document' => $this->imageDocument($inline->public_id)]);
        $source->articleContent->forceFill(['cover_attachment_id' => $cover->id])->save();
        $item = $this->publishCampusArticle($item, $admin, $super);
        $revisionItem = $service->createRevision($item, $admin, (int) $item->lock_version);
        $revision = $revisionItem->currentDraftVersion;
        $clones = $revision->attachments()->get()->keyBy(fn (ContentAttachment $file) => $file->purpose->value);

        $this->assertCount(3, $clones);
        $this->assertNotSame($cover->public_id, $clones['cover']->public_id);
        $this->assertNotSame($inline->public_id, $clones['inline_image']->public_id);
        $this->assertNotSame($general->public_id, $clones['attachment']->public_id);
        $this->assertNull($clones['attachment']->original_filename);
        $this->assertSame($clones['cover']->id, $revision->articleContent->cover_attachment_id);
        $this->assertSame(
            $clones['inline_image']->public_id,
            $revision->articleContent->document_json['content'][0]['attrs']['attachment_public_id'],
        );
        foreach ($clones as $clone) {
            Storage::disk('content')->assertExists($clone->storage_path);
        }

        $service->updateDraft($revision, $admin, ['requires_editorial_review' => false]);
        $revisionItem = $service->submit($revision->fresh(), $admin, (int) $revisionItem->fresh()->lock_version);
        $revisionItem = $service->startReview($revisionItem->currentDraftVersion, $super, (int) $revisionItem->lock_version);
        $revisionItem = $service->approve($revisionItem->currentDraftVersion, $super, (int) $revisionItem->lock_version);
        $revisionItem = $service->publishApproved($revisionItem->currentDraftVersion, $super, (int) $revisionItem->lock_version);

        $this->assertSame(2, $revisionItem->publishedVersion->version_number);
        Sanctum::actingAs($this->user('reporter', $this->campusA), ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath(
                'data.body.content.0.attrs.attachment_public_id',
                $clones['inline_image']->public_id,
            )
            ->assertJsonPath('data.cover.public_id', $clones['cover']->public_id)
            ->assertJsonFragment(['public_id' => $clones['attachment']->public_id]);
    }

    public function test_revision_clone_failure_rolls_back_database_and_copied_files(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Revision rollback'));
        $source = $item->currentDraftVersion;
        $this->attachment($source, $admin, ContentAttachmentPurpose::Attachment, "%PDF-1.4\nfirst", 'application/pdf', 'pdf');
        $missing = $this->attachment($source, $admin, ContentAttachmentPurpose::Attachment, "%PDF-1.4\nsecond", 'application/pdf', 'pdf');
        $item = $this->publishCampusArticle($item, $admin, $super);
        Storage::disk('content')->delete($missing->storage_path);
        $filesBefore = Storage::disk('content')->allFiles();

        try {
            $service->createRevision($item, $admin, (int) $item->lock_version);
            $this->fail('Revision creation accepted a missing source attachment.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, $item->versions()->count());
        $this->assertNull($item->fresh()->current_draft_version_id);
        $this->assertSame($filesBefore, Storage::disk('content')->allFiles());
    }

    public function test_submit_rejects_foreign_cover_image_reference_and_missing_private_bytes(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $service = app(ContentPublicationService::class);
        $source = $service->createDraft($admin, $this->articlePayload('Foreign source'));
        $target = $service->createDraft($admin, $this->articlePayload('Foreign target'));
        $cover = $this->attachment($source->currentDraftVersion, $admin, ContentAttachmentPurpose::Cover, 'cover', 'image/png', 'png');
        $inline = $this->attachment($source->currentDraftVersion, $admin, ContentAttachmentPurpose::InlineImage, 'inline', 'image/png', 'png');
        $targetContent = $target->currentDraftVersion->articleContent;
        $targetContent->forceFill([
            'cover_attachment_id' => $cover->id,
            'document_json' => $this->imageDocument($inline->public_id),
        ])->save();

        try {
            $service->submit($target->currentDraftVersion->fresh(), $admin, (int) $target->fresh()->lock_version);
            $this->fail('Foreign attachment references were accepted at submit.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $targetContent->forceFill([
            'cover_attachment_id' => null,
            'document_json' => $this->document('Valid body'),
        ])->save();
        $missing = $this->attachment(
            $target->currentDraftVersion,
            $admin,
            ContentAttachmentPurpose::Attachment,
            "%PDF-1.4\nmissing",
            'application/pdf',
            'pdf',
        );
        Storage::disk('content')->delete($missing->storage_path);

        $this->expectException(ValidationException::class);
        $service->submit($target->currentDraftVersion->fresh(), $admin, (int) $target->fresh()->lock_version);
    }

    public function test_consultation_is_a_dedicated_reader_type_not_an_article_cta(): void
    {
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $cta = $this->publishedGlobalConsultation($super, 'Layanan Konsultasi');
        $article = $service->createDraft($admin, $this->articlePayload('Article without CTA') + [
            'consultation_cta_public_id' => $cta->public_id,
        ]);
        $article = $this->publishCampusArticle($article, $admin, $super);
        Sanctum::actingAs($this->user('reporter', $this->campusA), ['*']);
        $this->getJson('/api/v1/content/articles/'.$article->public_id)
            ->assertOk()
            ->assertJsonMissingPath('data.consultation_cta_public_id');
        $this->getJson('/api/v1/content/consultation')->assertOk()
            ->assertJsonFragment(['public_id' => $cta->public_id, 'service_name' => 'Layanan Konsultasi']);
    }

    public function test_article_slug_detail_is_section_aware_scope_safe_and_published_only(): void
    {
        $reader = $this->user('reporter', $this->campusA);
        $globalEducation = $this->manualPublishedArticle(ContentScope::Global, null, 'Global Education', sectionCode: 'education');
        $ownPolicy = $this->manualPublishedArticle(ContentScope::Campus, $this->campusA, 'Own Policy', sectionCode: 'policy');
        $foreignEducation = $this->manualPublishedArticle(ContentScope::Campus, $this->campusB, 'Foreign Education', sectionCode: 'education');
        foreach ([$globalEducation, $ownPolicy, $foreignEducation] as $item) {
            $item->forceFill(['slug' => 'shared-slug'])->save();
        }

        $future = $this->manualPublishedArticle(ContentScope::Global, null, 'Future Education');
        $future->forceFill(['slug' => 'future-hidden'])->save();
        DB::table('content_versions')->where('id', $future->published_version_id)
            ->update(['published_at' => now()->addDay()]);

        $draft = app(ContentPublicationService::class)->createDraft(
            $this->user('admin', $this->campusA),
            $this->articlePayload('Draft Education'),
        );
        $draft->forceFill(['slug' => 'draft-hidden'])->save();

        Sanctum::actingAs($reader, ['*']);
        $this->getJson('/api/v1/content/articles/'.$globalEducation->public_id)
            ->assertOk()
            ->assertJsonPath('data.public_id', $globalEducation->public_id);
        $this->getJson('/api/v1/content/articles/'.$ownPolicy->public_id)
            ->assertOk()
            ->assertJsonPath('data.public_id', $ownPolicy->public_id);
        $this->getJson('/api/v1/content/articles/'.$foreignEducation->public_id)->assertNotFound();
        $this->getJson('/api/v1/content/articles/slug/education/shared-slug')
            ->assertOk()
            ->assertJsonPath('data.public_id', $globalEducation->public_id)
            ->assertJsonPath('data.section.code', 'education');
        $this->getJson('/api/v1/content/articles/slug/policy/shared-slug')
            ->assertOk()
            ->assertJsonPath('data.public_id', $ownPolicy->public_id)
            ->assertJsonPath('data.section.code', 'policy');
        $this->getJson('/api/v1/content/articles/slug/education/future-hidden')->assertNotFound();
        $this->getJson('/api/v1/content/articles/slug/education/draft-hidden')->assertNotFound();
        $this->getJson('/api/v1/content/articles/slug/shared-slug')->assertNotFound();
    }

    public function test_database_constraints_reject_invalid_scope_type_lifecycle_rank_and_window(): void
    {
        $item = $this->manualPublishedArticle(ContentScope::Campus, $this->campusA, 'Constraint item');
        $category = ContentCategory::query()->where('scope', 'global')->firstOrFail();
        $creator = $this->user('super_admin');

        $this->assertQueryRejected(fn () => DB::table('content_items')->where('id', $item->id)->update([
            'scope' => 'global',
        ]));
        $this->assertQueryRejected(fn () => DB::table('content_categories')->where('id', $category->id)->update([
            'scope' => 'campus',
        ]));
        $this->assertQueryRejected(fn () => DB::table('content_items')->where('id', $item->id)->update([
            'content_type' => 'unknown',
        ]));
        $this->assertQueryRejected(fn () => DB::table('content_versions')->where('id', $item->published_version_id)->update([
            'lifecycle_status' => 'unknown',
        ]));
        $this->assertQueryRejected(fn () => DB::table('featured_content')->insert([
            'public_id' => (string) Str::uuid(),
            'scope' => 'campus',
            'scope_key' => 'campus:'.$this->campusA->id,
            'university_id' => $this->campusA->id,
            'content_item_id' => $item->id,
            'rank' => 6,
            'is_active' => false,
            'active_from' => now()->addDay(),
            'active_until' => now(),
            'creator_id' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_download_uses_generic_ascii_filename_for_sensitive_untrusted_names(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)->createDraft($admin, $this->articlePayload('Filename privacy'));
        Sanctum::actingAs($admin, ['*']);

        foreach (['DEMO-2026-001 Nama Pelapor.pdf', 'bukti-unikode-é.pdf', "..\\rahasia\r\nX-Evil.pdf"] as $name) {
            $upload = $this->postJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id.'/attachments', [
                'purpose' => 'attachment',
                'file' => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF"),
            ])->assertCreated();
            $publicId = $upload->json('data.public_id');
            $upload->assertJsonPath('data.filename', 'lampiran-'.$publicId.'.pdf')
                ->assertJsonMissingPath('data.original_filename');

            $header = (string) $this->get('/api/v1/content/attachments/'.$publicId)
                ->assertOk()
                ->headers->get('Content-Disposition');
            $this->assertStringContainsString('lampiran-'.$publicId.'.pdf', $header);
            $this->assertStringNotContainsString('DEMO-2026-001', $header);
            $this->assertStringNotContainsString('rahasia', $header);
            $this->assertStringNotContainsString("\r", $header);
            $this->assertStringNotContainsString("\n", $header);
        }
    }

    public function test_ordering_and_featured_windows_are_deterministic_and_expired_rank_is_reusable(): void
    {
        CarbonImmutable::setTestNow('2026-07-21 12:00:00');
        $reader = $this->user('reporter', $this->campusA);
        $creator = $this->user('super_admin');
        $ids = [];
        foreach (range(1, 6) as $number) {
            $publicId = sprintf('00000000-0000-4000-8000-%012d', $number);
            $ids[] = $publicId;
            $this->manualPublishedArticle(ContentScope::Global, null, 'Ordered '.$number, $publicId);
        }

        Sanctum::actingAs($reader, ['*']);
        $response = $this->getJson('/api/v1/content/articles?per_page=10')->assertOk();
        $this->assertSame($ids, collect($response->json('data'))->pluck('public_id')->all());

        $future = ContentItem::query()->where('public_id', $ids[5])->firstOrFail();
        FeaturedContent::query()->create([
            'scope' => 'global',
            'content_item_id' => $future->id,
            'rank' => 1,
            'active_from' => now()->addDay(),
            'active_until' => now()->addDays(2),
            'creator_id' => $creator->id,
        ]);
        $featuredIds = collect($this->getJson('/api/v1/content/featured')->assertOk()->json('data'))->pluck('public_id');
        $this->assertSame([], $featuredIds->all());
        $this->assertFalse($featuredIds->contains($future->public_id));

        $expiredItem = ContentItem::query()->where('public_id', $ids[0])->firstOrFail();
        $expired = FeaturedContent::query()->create([
            'scope' => 'global',
            'content_item_id' => $expiredItem->id,
            'rank' => 2,
            'active_from' => now()->subDays(2),
            'active_until' => now()->subDay(),
            'creator_id' => $creator->id,
        ]);
        $this->assertFalse($expired->is_active);
        FeaturedContent::query()->create([
            'scope' => 'global',
            'content_item_id' => ContentItem::query()->where('public_id', $ids[1])->value('id'),
            'rank' => 2,
            'creator_id' => $creator->id,
        ]);

        try {
            FeaturedContent::query()->create([
                'scope' => 'global',
                'content_item_id' => ContentItem::query()->where('public_id', $ids[2])->value('id'),
                'rank' => 3,
                'active_from' => now()->addDay(),
                'active_until' => now(),
                'creator_id' => $creator->id,
            ]);
            $this->fail('An invalid featured date window was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->assertQueryRejected(fn () => FeaturedContent::query()->create([
            'scope' => 'global',
            'content_item_id' => ContentItem::query()->where('public_id', $ids[3])->value('id'),
            'rank' => 2,
            'creator_id' => $creator->id,
        ]));
    }

    public function test_category_related_faq_and_consultation_ties_use_public_id_as_final_order(): void
    {
        CarbonImmutable::setTestNow('2026-07-21 13:00:00');
        $reader = $this->user('reporter', $this->campusA);
        $categoryIds = [
            '10000000-0000-4000-8000-000000000001',
            '10000000-0000-4000-8000-000000000002',
        ];
        $categories = ContentCategory::query()->where('section_id', ContentSection::query()->where('code', 'education')->value('id'))
            ->orderBy('id')->limit(2)->get();
        foreach ($categories as $index => $category) {
            $category->forceFill([
                'public_id' => $categoryIds[$index],
                'name' => 'Tie category',
                'display_order' => 99,
                'scope' => $index === 0 ? ContentScope::Global : ContentScope::Campus,
                'university_id' => $index === 0 ? null : $this->campusA->id,
            ])->save();
        }

        $articleIds = [
            '20000000-0000-4000-8000-000000000001',
            '20000000-0000-4000-8000-000000000002',
            '20000000-0000-4000-8000-000000000003',
        ];
        $articles = collect($articleIds)->map(fn (string $publicId, int $index) => $this->manualPublishedArticle(ContentScope::Global, null, 'Related '.$index, $publicId)
        );

        $faqIds = [
            '30000000-0000-4000-8000-000000000001',
            '30000000-0000-4000-8000-000000000002',
        ];
        foreach ($faqIds as $publicId) {
            $this->manualPublishedFaq($publicId, 'Tie FAQ');
        }
        $consultationIds = [
            '40000000-0000-4000-8000-000000000001',
            '40000000-0000-4000-8000-000000000002',
        ];
        foreach ($consultationIds as $publicId) {
            $this->manualPublishedConsultation($publicId, 'Tie consultation');
        }

        Sanctum::actingAs($reader, ['*']);
        $categoryResponse = collect($this->getJson('/api/v1/content/categories?section=education')->assertOk()->json('data'))
            ->where('name', 'Tie category')->pluck('public_id')->all();
        $this->assertSame($categoryIds, $categoryResponse);
        $related = collect($this->getJson('/api/v1/content/articles/'.$articles[0]->public_id)->assertOk()->json('data.related_articles'))
            ->pluck('public_id')->all();
        $this->assertSame(array_slice($articleIds, 1), $related);
        $this->assertSame(
            $faqIds,
            collect($this->getJson('/api/v1/content/faqs')->assertOk()->json('data'))
                ->where('question', 'Tie FAQ')->pluck('public_id')->all(),
        );
        $this->assertSame(
            $consultationIds,
            collect($this->getJson('/api/v1/content/consultation')->assertOk()->json('data'))
                ->where('service_name', 'Tie consultation')->pluck('public_id')->all(),
        );
    }

    public function test_structured_documents_reject_excessive_size_depth_marks_and_links_at_boundaries(): void
    {
        $documents = app(ContentDocumentService::class);
        $invalid = [
            $this->document(str_repeat('a', 500001)),
            $this->document(str_repeat('a', 20001)),
            ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'marks',
                    'marks' => array_fill(0, 5, ['type' => 'bold']),
                ]],
            ]]],
            ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'link',
                    'marks' => [[
                        'type' => 'link',
                        'attrs' => ['href' => 'https://example.test/'.str_repeat('a', 2050)],
                    ]],
                ]],
            ]]],
            ['type' => 'doc', 'content' => [$this->nestedNode(13)]],
        ];
        $invalid[] = ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => array_fill(0, 1000, ['type' => 'text', 'text' => 'node']),
        ]]];
        $invalid[] = ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => array_fill(0, 600, [
                'type' => 'text',
                'text' => 'marks',
                'marks' => [
                    ['type' => 'bold'],
                    ['type' => 'italic'],
                    ['type' => 'bold'],
                    ['type' => 'italic'],
                ],
            ]),
        ]]];

        foreach ($invalid as $document) {
            try {
                $documents->prepareArticle($document);
                $this->fail('An excessive structured document was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $valid = $documents->prepareArticle([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Boundary-valid content',
                    'marks' => [
                        ['type' => 'bold'],
                        ['type' => 'italic'],
                        ['type' => 'link', 'attrs' => ['href' => 'https://example.test/reference']],
                    ],
                ]],
            ]],
        ]);
        $this->assertSame('Boundary-valid content', $valid['text']);
    }

    public function test_test_database_guard_allows_only_explicit_disposable_targets(): void
    {
        $guard = app(TestDatabaseGuard::class);
        $original = [
            'env' => config('app.env'),
            'default' => config('database.default'),
            'sqlite' => config('database.connections.sqlite'),
            'pgsql' => config('database.connections.pgsql'),
            'confirmation' => config('database.testing_confirmation'),
        ];

        try {
            config()->set('database.default', 'sqlite');
            config()->set('database.connections.sqlite.database', ':memory:');
            $this->assertSame(':memory:', $guard->assertSafe()['database']);

            config()->set('database.default', 'pgsql');
            config()->set('database.connections.pgsql.url', null);
            config()->set('database.connections.pgsql.host', '127.0.0.1');
            config()->set('database.connections.pgsql.database', 'silappkasal');
            $this->assertGuardRejected($guard);

            config()->set('database.connections.pgsql.database', 'silappkasal_test');
            config()->set('database.testing_confirmation', null);
            $this->assertGuardRejected($guard);

            config()->set('database.testing_confirmation', 'silappkasal_test');
            $this->assertSame('silappkasal_test', $guard->assertSafe()['database']);

            config()->set('app.env', 'local');
            $this->assertGuardRejected($guard);
        } finally {
            config()->set('app.env', $original['env']);
            config()->set('database.default', $original['default']);
            config()->set('database.connections.sqlite', $original['sqlite']);
            config()->set('database.connections.pgsql', $original['pgsql']);
            config()->set('database.testing_confirmation', $original['confirmation']);
        }
    }

    private function publishCampusArticle(ContentItem $item, User $admin, User $super): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $item->refresh();
        $item = $service->submit($item->currentDraftVersion->fresh(), $admin, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $super, (int) $item->lock_version);

        return $service->publishApproved($item->currentDraftVersion, $super, (int) $item->lock_version);
    }

    private function publishedGlobalConsultation(User $super, string $title): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($super, [
            'content_type' => 'consultation',
            'section_code' => 'consultation',
            'scope' => 'global',
            'title' => $title.' '.Str::random(6),
            'service_name' => $title,
            'description' => 'Verified consultation description.',
            'is_active' => true,
            'verification_date' => now()->toDateString(),
            'verified_owner' => 'Verified institutional owner',
        ]);

        return $this->publishGlobalItem($item, $super);
    }

    private function publishGlobalItem(ContentItem $item, User $author): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $reviewer = $this->user('super_admin');
        $item->refresh();
        $item = $service->submit($item->currentDraftVersion->fresh(), $author, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $reviewer, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $reviewer, (int) $item->lock_version);

        return $service->publishApproved($item->currentDraftVersion, $reviewer, (int) $item->lock_version);
    }

    /** @return array<string, mixed> */
    private function articlePayload(string $title): array
    {
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();

        return [
            'content_type' => 'article',
            'section_code' => 'education',
            'category_public_id' => $category->public_id,
            'category_name' => $category->name,
            'scope' => 'campus',
            'university_id' => $this->campusA->id,
            'title' => $title.' '.Str::random(6),
            'excerpt' => 'Safe repair test excerpt.',
            'document' => $this->document('Safe repair test body.'),
        ];
    }

    private function manualPublishedArticle(
        ContentScope $scope,
        ?University $campus,
        string $title,
        ?string $publicId = null,
        string $sectionCode = 'education',
    ): ContentItem {
        $section = ContentSection::query()->where('code', $sectionCode)->firstOrFail();
        $category = ContentCategory::query()
            ->where('section_id', $section->id)
            ->where('scope', ContentScope::Global->value)
            ->firstOrFail();
        $item = ContentItem::query()->create([
            'public_id' => $publicId,
            'content_type' => ContentType::Article,
            'section_id' => $section->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'scope' => $scope,
            'university_id' => $campus?->id,
        ]);
        $version = ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version_number' => 1,
            'lifecycle_status' => ContentLifecycleStatus::Draft,
            'title' => $title,
            'excerpt' => 'Safe excerpt.',
            'source_type' => 'test',
        ]);
        ArticleVersionContent::query()->create([
            'content_version_id' => $version->id,
            'document_json' => $this->document('Safe body.'),
            'sanitized_html' => '<p>Safe body.</p>',
            'search_text' => 'Safe body.',
            'estimated_reading_minutes' => 1,
        ]);
        $version->forceFill([
            'lifecycle_status' => ContentLifecycleStatus::Published,
            'published_at' => now(),
        ])->save();
        $item->forceFill(['published_version_id' => $version->id])->save();

        return $item->fresh(['publishedVersion.articleContent']);
    }

    private function manualPublishedFaq(string $publicId, string $title): ContentItem
    {
        $section = ContentSection::query()->where('code', 'faq')->firstOrFail();
        $item = ContentItem::query()->create([
            'public_id' => $publicId,
            'content_type' => ContentType::Faq,
            'section_id' => $section->id,
            'slug' => 'faq-'.$publicId,
            'scope' => ContentScope::Global,
        ]);
        $version = ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version_number' => 1,
            'lifecycle_status' => ContentLifecycleStatus::Draft,
            'title' => $title,
            'source_type' => 'test',
        ]);
        FaqVersionContent::query()->create([
            'content_version_id' => $version->id,
            'question' => $title,
            'answer_document_json' => $this->document('Safe FAQ answer.'),
            'sanitized_answer_html' => '<p>Safe FAQ answer.</p>',
            'plain_search_text' => 'Safe FAQ answer.',
            'display_order' => 10,
        ]);
        $version->forceFill(['lifecycle_status' => ContentLifecycleStatus::Published, 'published_at' => now()])->save();
        $item->forceFill(['published_version_id' => $version->id])->save();

        return $item;
    }

    private function manualPublishedConsultation(string $publicId, string $title): ContentItem
    {
        $section = ContentSection::query()->where('code', 'consultation')->firstOrFail();
        $item = ContentItem::query()->create([
            'public_id' => $publicId,
            'content_type' => ContentType::Consultation,
            'section_id' => $section->id,
            'slug' => 'consultation-'.$publicId,
            'scope' => ContentScope::Global,
        ]);
        $version = ContentVersion::query()->create([
            'content_item_id' => $item->id,
            'version_number' => 1,
            'lifecycle_status' => ContentLifecycleStatus::Draft,
            'title' => $title,
            'source_type' => 'test',
        ]);
        ConsultationVersionContent::query()->create([
            'content_version_id' => $version->id,
            'service_name' => $title,
            'description' => 'Safe consultation description.',
            'sort_order' => 10,
            'is_active' => true,
            'verification_date' => now()->toDateString(),
            'verified_owner' => 'Verified owner',
        ]);
        $version->forceFill(['lifecycle_status' => ContentLifecycleStatus::Published, 'published_at' => now()])->save();
        $item->forceFill(['published_version_id' => $version->id])->save();

        return $item;
    }

    private function attachment(
        ContentVersion $version,
        User $uploader,
        ContentAttachmentPurpose $purpose,
        string $bytes,
        string $mime,
        string $extension,
    ): ContentAttachment {
        $publicId = (string) Str::uuid();
        $path = $version->public_id.'/'.$publicId.'.'.$extension;
        Storage::disk('content')->put($path, $bytes);

        return ContentAttachment::query()->create([
            'public_id' => $publicId,
            'content_version_id' => $version->id,
            'purpose' => $purpose,
            'storage_disk' => 'content',
            'storage_path' => $path,
            'safe_filename' => $purpose->value.'-'.$publicId.'.'.$extension,
            'original_filename' => 'sensitive-original-'.$publicId.'.'.$extension,
            'detected_mime' => $mime,
            'extension' => $extension,
            'file_size' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
            'width' => str_starts_with($mime, 'image/') ? 1 : null,
            'height' => str_starts_with($mime, 'image/') ? 1 : null,
            'display_order' => 0,
            'uploader_id' => $uploader->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]]];
    }

    /** @return array<string, mixed> */
    private function imageDocument(string $publicId): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'imageReference',
            'attrs' => ['attachment_public_id' => $publicId, 'alt' => 'Safe illustration'],
        ]]];
    }

    /** @return array<string, mixed> */
    private function nestedNode(int $depth): array
    {
        if ($depth <= 1) {
            return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'deep']]];
        }

        return ['type' => 'blockquote', 'content' => [$this->nestedNode($depth - 1)]];
    }

    private function assertQueryRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A database integrity violation was accepted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    private function assertGuardRejected(TestDatabaseGuard $guard): void
    {
        try {
            $guard->assertSafe();
            $this->fail('An unsafe test database configuration was accepted.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
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
