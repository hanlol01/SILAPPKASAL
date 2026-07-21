<?php

namespace Tests\Feature;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Models\AuditLog;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentReviewDecision;
use App\Models\ContentVersion;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private University $campus;

    private User $admin;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(ContentFoundationSeeder::class);
        $this->campus = University::query()->create([
            'code' => 'C3-CAMPUS',
            'name' => 'Universitas C3',
            'type' => 'universitas',
            'is_active' => true,
        ]);
        $this->admin = $this->user('admin', $this->campus);
        $this->reviewer = $this->user('super_admin');
    }

    public function test_review_queue_is_private_filtered_and_governance_only(): void
    {
        $submitted = $this->submittedCampusArticle('Artikel Menunggu Review');
        $this->campusDraft('Draf Tidak Masuk Antrean');
        $guest = $this->getJson('/api/v1/content-governance/reviews')->assertUnauthorized();
        $this->assertStringContainsString('no-store', (string) $guest->headers->get('Cache-Control'));
        Sanctum::actingAs($this->reviewer, ['*']);

        $response = $this->getJson('/api/v1/content-governance/reviews?scope=campus&content_type=article&search=Menunggu')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $submitted->public_id)
            ->assertJsonPath('data.0.university.code', $this->campus->code)
            ->assertJsonPath('data.0.capabilities.start_review', true)
            ->assertJsonMissing(['email'])
            ->assertJsonMissing(['creator_id'])
            ->assertJsonMissing(['university_id']);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $invalid = $this->getJson('/api/v1/content-governance/reviews?category=not-a-uuid')
            ->assertUnprocessable();
        $this->assertStringContainsString('no-store', (string) $invalid->headers->get('Cache-Control'));

        foreach (['admin', 'reporter', 'satgas_ppks'] as $role) {
            $actor = $role === 'admin' ? $this->admin : $this->user($role, $this->campus);
            Sanctum::actingAs($actor, ['*']);
            $denied = $this->getJson('/api/v1/content-governance/reviews')->assertForbidden();
            $this->assertStringContainsString('no-store', (string) $denied->headers->get('Cache-Control'));
            $this->getJson('/api/v1/content-governance/items/'.$submitted->public_id)->assertForbidden();
        }

        Sanctum::actingAs($this->reviewer, ['*']);
        $this->getJson('/api/v1/content-governance/items/'.Str::uuid())->assertNotFound();
    }

    public function test_revision_request_requires_reason_detects_stale_state_and_returns_feedback_to_campus(): void
    {
        $item = $this->submittedCampusArticle('Perlu Perbaikan');
        Sanctum::actingAs($this->reviewer, ['*']);
        $versionId = $item->currentDraftVersion->public_id;

        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/start-review', [
            'lock_version' => $item->lock_version,
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'in_review');
        $item->refresh();

        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/request-revision', [
            'lock_version' => $item->lock_version,
            'reason' => 'Pendek',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/request-revision', [
            'lock_version' => $item->lock_version - 1,
            'reason' => 'Tambahkan sumber yang dapat diverifikasi oleh editor.',
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_stale_review');

        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/request-revision', [
            'lock_version' => $item->lock_version,
            'reason' => 'Tambahkan sumber yang dapat diverifikasi oleh editor.',
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'revision_requested');

        $this->assertDatabaseHas('content_review_decisions', [
            'content_version_id' => $item->current_draft_version_id,
            'decision_code' => 'revision_requested',
        ]);
        Sanctum::actingAs($this->admin, ['*']);
        $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.review_feedback.reason', 'Tambahkan sumber yang dapat diverifikasi oleh editor.');
    }

    public function test_rejection_is_confirmed_by_reason_append_only_and_concurrency_safe(): void
    {
        $item = $this->submittedCampusArticle('Kandidat Penolakan');
        Sanctum::actingAs($this->reviewer, ['*']);
        $version = $item->currentDraftVersion;
        $this->postJson('/api/v1/content-governance/versions/'.$version->public_id.'/start-review', [
            'lock_version' => $item->lock_version,
        ])->assertOk();
        $item->refresh();

        $this->postJson('/api/v1/content-governance/versions/'.$version->public_id.'/reject', [
            'lock_version' => $item->lock_version,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $lock = $item->lock_version;
        $this->postJson('/api/v1/content-governance/versions/'.$version->public_id.'/reject', [
            'lock_version' => $lock,
            'reason' => 'Materi tidak memenuhi standar editorial yang disyaratkan.',
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'rejected');

        $this->postJson('/api/v1/content-governance/versions/'.$version->public_id.'/approve', [
            'lock_version' => $lock,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_stale_review');
        $this->assertNull($item->fresh()->current_draft_version_id);
        $this->assertDatabaseHas('content_versions', ['id' => $version->id, 'lifecycle_status' => 'rejected']);
        $decision = ContentReviewDecision::query()->where('decision_code', 'rejected')->firstOrFail();
        $this->expectException(\LogicException::class);
        $decision->update(['narrative_reason' => 'Tidak boleh diubah']);
    }

    public function test_approval_and_publication_are_distinct_locked_transitions_that_preserve_versions(): void
    {
        $service = app(ContentPublicationService::class);
        $item = $this->submittedCampusArticle('Versi Pertama');
        Sanctum::actingAs($this->reviewer, ['*']);
        $versionId = $item->currentDraftVersion->public_id;
        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/start-review', ['lock_version' => $item->lock_version])->assertOk();
        $item->refresh();
        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/approve', [
            'lock_version' => $item->lock_version,
            'note' => 'Sumber dan struktur telah diverifikasi.',
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'approved');
        $item->refresh();
        $this->assertNull($item->published_version_id);
        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/publish', [
            'lock_version' => $item->lock_version,
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'published');
        $item->refresh();
        $firstPublishedId = $item->published_version_id;

        $revision = $service->createRevision($item, $this->admin, (int) $item->lock_version);
        $revision = $service->updateDraft($revision->currentDraftVersion, $this->admin, [
            'title' => 'Versi Kedua',
            'lock_version' => $revision->lock_version,
        ]);
        $revision = $service->submit($revision->currentDraftVersion, $this->admin, (int) $revision->lock_version);
        $secondVersionId = $revision->currentDraftVersion->public_id;

        Sanctum::actingAs($this->user('reporter', $this->campus), ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()->assertJsonPath('data.title', 'Versi Pertama');

        Sanctum::actingAs($this->reviewer, ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$secondVersionId.'/start-review', ['lock_version' => $revision->lock_version])->assertOk();
        $revision->refresh();
        $this->postJson('/api/v1/content-governance/versions/'.$secondVersionId.'/approve', ['lock_version' => $revision->lock_version])->assertOk();
        $revision->refresh();
        $this->postJson('/api/v1/content-governance/versions/'.$secondVersionId.'/publish', ['lock_version' => $revision->lock_version])->assertOk();

        $item->refresh();
        $this->assertNotSame($firstPublishedId, $item->published_version_id);
        $this->assertSame(2, ContentVersion::query()->where('content_item_id', $item->id)->count());
        $this->assertSame(ContentLifecycleStatus::Published, ContentVersion::query()->findOrFail($firstPublishedId)->lifecycle_status);
    }

    public function test_super_admin_global_authoring_requires_a_distinct_reviewer_and_campus_cannot_escalate(): void
    {
        Sanctum::actingAs($this->reviewer, ['*']);
        $payload = $this->articlePayload(ContentScope::Global, null, 'Konten Global C3');
        $created = $this->postJson('/api/v1/content-management/items', $payload)
            ->assertCreated()->assertJsonPath('data.scope', 'global')->json('data');
        $service = app(ContentPublicationService::class);
        $draft = ContentVersion::query()->where('public_id', $created['version']['public_id'])->firstOrFail();
        $secondReviewer = $this->user('super_admin');

        $this->assertFalse(method_exists($service, 'directGlobalPublish'));
        try {
            $service->publishApproved($draft, $secondReviewer, (int) $created['lock_version']);
            $this->fail('An editable global draft was published through direct service invocation.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
        }
        $this->postJson('/api/v1/content-management/versions/'.$created['version']['public_id'].'/submit', [
            'lock_version' => $created['lock_version'],
        ])->assertOk();

        $queueItem = $this->getJson('/api/v1/content-governance/reviews?scope=global')
            ->assertOk()->json('data.0');
        $this->assertFalse($queueItem['capabilities']['start_review']);
        $this->postJson('/api/v1/content-governance/versions/'.$created['version']['public_id'].'/start-review', [
            'lock_version' => $queueItem['lock_version'],
        ])->assertForbidden();

        Sanctum::actingAs($secondReviewer, ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$created['version']['public_id'].'/start-review', [
            'lock_version' => $queueItem['lock_version'],
        ])->assertOk();
        $item = ContentItem::query()->where('public_id', $created['public_id'])->firstOrFail();

        Sanctum::actingAs($this->reviewer, ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$created['version']['public_id'].'/approve', [
            'lock_version' => $item->lock_version,
        ])->assertForbidden();

        Sanctum::actingAs($secondReviewer, ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$created['version']['public_id'].'/approve', [
            'lock_version' => $item->lock_version,
        ])->assertOk();
        $item->refresh();

        Sanctum::actingAs($this->reviewer, ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$created['version']['public_id'].'/publish', [
            'lock_version' => $item->lock_version,
        ])->assertForbidden();

        Sanctum::actingAs($secondReviewer, ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$created['version']['public_id'].'/publish', [
            'lock_version' => $item->lock_version,
        ])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.approved', 'actor_id' => $secondReviewer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.published', 'actor_id' => $secondReviewer->id]);

        Sanctum::actingAs($this->admin, ['*']);
        $this->getJson('/api/v1/content-management/items/'.$created['public_id'])->assertNotFound();
        $this->patchJson('/api/v1/content-management/versions/'.$created['version']['public_id'], [
            'title' => 'Eskalasi Tidak Sah',
            'lock_version' => $queueItem['lock_version'],
        ])->assertNotFound();
    }

    public function test_published_library_and_reader_follow_only_the_published_pointer_across_rejected_and_approved_revisions(): void
    {
        $service = app(ContentPublicationService::class);
        $item = $this->submittedCampusArticle('Versi Terbit Pertama');
        $item = $service->startReview($item->currentDraftVersion, $this->reviewer, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $this->reviewer, (int) $item->lock_version);
        $item = $service->publishApproved($item->currentDraftVersion, $this->reviewer, (int) $item->lock_version);
        $firstPublished = $item->publishedVersion;

        $rejected = $service->createRevision($item, $this->admin, (int) $item->lock_version);
        $rejected = $service->updateDraft($rejected->currentDraftVersion, $this->admin, [
            'title' => 'Versi Revisi Ditolak',
            'lock_version' => $rejected->lock_version,
        ]);
        $rejected = $service->submit($rejected->currentDraftVersion, $this->admin, (int) $rejected->lock_version);
        $rejected = $service->startReview($rejected->currentDraftVersion, $this->reviewer, (int) $rejected->lock_version);
        $rejected = $service->reject(
            $rejected->currentDraftVersion,
            $this->reviewer,
            'Versi revisi belum memenuhi standar editorial.',
            (int) $rejected->lock_version,
        );

        Sanctum::actingAs($this->reviewer, ['*']);
        $this->getJson('/api/v1/content-governance/published?search=Versi%20Terbit%20Pertama')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.version.public_id', $firstPublished->public_id)
            ->assertJsonPath('data.0.version.title', 'Versi Terbit Pertama')
            ->assertJsonPath('data.0.version.status', 'published');
        $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.version.title', 'Versi Revisi Ditolak')
            ->assertJsonPath('data.version.status', 'rejected')
            ->assertJsonPath('data.previous_published_version.public_id', $firstPublished->public_id);

        Sanctum::actingAs($this->user('reporter', $this->campus), ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()->assertJsonPath('data.title', 'Versi Terbit Pertama');

        $approved = $service->createRevision($rejected, $this->admin, (int) $rejected->lock_version);
        $approved = $service->updateDraft($approved->currentDraftVersion, $this->admin, [
            'title' => 'Versi Revisi Disetujui',
            'lock_version' => $approved->lock_version,
        ]);
        $approved = $service->submit($approved->currentDraftVersion, $this->admin, (int) $approved->lock_version);
        $approved = $service->startReview($approved->currentDraftVersion, $this->reviewer, (int) $approved->lock_version);
        $approved = $service->approve($approved->currentDraftVersion, $this->reviewer, (int) $approved->lock_version);

        Sanctum::actingAs($this->reviewer, ['*']);
        $this->getJson('/api/v1/content-governance/published?search=Versi%20Terbit%20Pertama')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.version.public_id', $firstPublished->public_id);
        $publishedAuditCount = AuditLog::query()->where('action', 'content.published')->count();
        $this->postJson('/api/v1/content-governance/versions/'.$firstPublished->public_id.'/publish', [
            'lock_version' => $approved->lock_version,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_invalid_lifecycle_transition');
        $this->postJson('/api/v1/content-governance/versions/'.$approved->currentDraftVersion->public_id.'/publish', [
            'lock_version' => $approved->lock_version - 1,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_stale_review');
        $this->assertSame(
            $publishedAuditCount,
            AuditLog::query()->where('action', 'content.published')->count(),
            'Rejected publication attempts must not create a success audit.',
        );
        $this->postJson('/api/v1/content-governance/versions/'.$approved->currentDraftVersion->public_id.'/publish', [
            'lock_version' => $approved->lock_version,
        ])->assertOk();
        $this->assertSame(
            $publishedAuditCount + 1,
            AuditLog::query()->where('action', 'content.published')->count(),
        );

        $approved->refresh();
        $this->getJson('/api/v1/content-governance/published?search=Versi%20Revisi%20Disetujui')
            ->assertOk()
            ->assertJsonPath('data.0.version.public_id', $approved->publishedVersion->public_id)
            ->assertJsonPath('data.0.version.title', 'Versi Revisi Disetujui');
        Sanctum::actingAs($this->user('reporter', $this->campus), ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)
            ->assertOk()->assertJsonPath('data.title', 'Versi Revisi Disetujui');
    }

    public function test_global_revision_author_and_editor_cannot_review_their_own_version(): void
    {
        $service = app(ContentPublicationService::class);
        $published = $this->publishedGlobalArticle('Global Dengan Revisi');
        $revisionAuthor = $this->user('super_admin');
        $revision = $service->createRevision($published, $revisionAuthor, (int) $published->lock_version);
        $revision = $service->updateDraft($revision->currentDraftVersion, $revisionAuthor, [
            'title' => 'Global Dengan Revisi Kedua',
            'lock_version' => $revision->lock_version,
        ]);
        $revision = $service->submit(
            $revision->currentDraftVersion,
            $revisionAuthor,
            (int) $revision->lock_version,
        );

        Sanctum::actingAs($revisionAuthor, ['*']);
        $this->getJson('/api/v1/content-governance/reviews?scope=global&search=Kedua')
            ->assertOk()->assertJsonPath('data.0.capabilities.start_review', false);
        $this->postJson('/api/v1/content-governance/versions/'.$revision->currentDraftVersion->public_id.'/start-review', [
            'lock_version' => $revision->lock_version,
        ])->assertForbidden();

        Sanctum::actingAs($this->user('super_admin'), ['*']);
        $this->postJson('/api/v1/content-governance/versions/'.$revision->currentDraftVersion->public_id.'/start-review', [
            'lock_version' => $revision->lock_version,
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'in_review');
    }

    public function test_super_admin_cannot_rewrite_campus_body_and_detail_projects_history_and_capabilities(): void
    {
        $item = $this->submittedCampusArticle('Read Only Campus');
        Sanctum::actingAs($this->reviewer, ['*']);

        $this->patchJson('/api/v1/content-management/versions/'.$item->currentDraftVersion->public_id, [
            'title' => 'Tidak Boleh Diubah',
            'lock_version' => $item->lock_version,
        ])->assertNotFound();
        $detail = $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.version.title', 'Read Only Campus')
            ->assertJsonPath('data.capabilities.start_review', true)
            ->assertJsonPath('data.decision_history.0.state', 'submitted');
        $this->assertStringContainsString('no-store', (string) $detail->headers->get('Cache-Control'));
    }

    public function test_published_governance_supports_safe_discovery_and_audited_archive(): void
    {
        $item = $this->submittedCampusArticle('Materi Siap Arsip');
        Sanctum::actingAs($this->reviewer, ['*']);
        $versionId = $item->currentDraftVersion->public_id;
        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/start-review', [
            'lock_version' => $item->lock_version,
        ])->assertOk();
        $item->refresh();
        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/approve', [
            'lock_version' => $item->lock_version,
        ])->assertOk();
        $item->refresh();
        $this->postJson('/api/v1/content-governance/versions/'.$versionId.'/publish', [
            'lock_version' => $item->lock_version,
        ])->assertOk();
        $item->refresh();

        $categories = $this->getJson('/api/v1/content-governance/categories?section=education')
            ->assertOk()
            ->assertJsonPath('data.0.section_code', 'education');
        $this->assertStringContainsString('no-store', (string) $categories->headers->get('Cache-Control'));

        $published = $this->getJson('/api/v1/content-governance/published?search=Siap%20Arsip')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.public_id', $item->public_id)
            ->assertJsonPath('data.0.capabilities.archive', true)
            ->assertJsonMissing(['creator_id', 'university_id']);
        $this->assertStringContainsString('no-store', (string) $published->headers->get('Cache-Control'));

        $this->postJson('/api/v1/content-governance/items/'.$item->public_id.'/archive', [
            'lock_version' => $item->lock_version - 1,
            'reason' => 'Materi digantikan oleh panduan editorial yang lebih mutakhir.',
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_stale_review');
        $this->postJson('/api/v1/content-governance/items/'.$item->public_id.'/archive', [
            'lock_version' => $item->lock_version,
            'reason' => 'Materi digantikan oleh panduan editorial yang lebih mutakhir.',
        ])->assertOk()->assertJsonPath('data.lifecycle_status', 'archived');

        $this->getJson('/api/v1/content-governance/published?search=Siap%20Arsip')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.decision_history.4.state', 'archived');

        Sanctum::actingAs($this->user('reporter', $this->campus), ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)->assertNotFound();
    }

    public function test_featured_governance_enforces_eligibility_rank_windows_and_concurrency(): void
    {
        $published = $this->publishedGlobalArticle('Unggulan Terbit');
        $draft = app(ContentPublicationService::class)->createDraft(
            $this->reviewer,
            $this->articlePayload(ContentScope::Global, null, 'Belum Terbit'),
        );
        Sanctum::actingAs($this->reviewer, ['*']);
        $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $draft->public_id,
            'scope' => 'global',
            'rank' => 1,
        ])->assertNotFound();

        $placement = $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $published->public_id,
            'scope' => 'global',
            'rank' => 1,
        ])->assertCreated()->assertJsonPath('data.state', 'current')->json('data');
        $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $this->publishedGlobalArticle('Konflik Rank')->public_id,
            'scope' => 'global',
            'rank' => 1,
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_featured_conflict');
        $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $published->public_id,
            'scope' => 'global',
            'rank' => 2,
            'active_from' => now()->addDay()->toJSON(),
            'active_until' => now()->toJSON(),
        ])->assertUnprocessable()->assertJsonValidationErrors('active_until');

        $this->patchJson('/api/v1/content-governance/featured/'.$placement['public_id'], [
            'rank' => 2,
            'concurrency_token' => str_repeat('0', 64),
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_featured_stale');
        $updated = $this->patchJson('/api/v1/content-governance/featured/'.$placement['public_id'], [
            'rank' => 2,
            'concurrency_token' => $placement['concurrency_token'],
        ])->assertOk()->assertJsonPath('data.rank', 2)->json('data');
        $replacement = $this->publishedGlobalArticle('Pengganti Konten Unggulan');
        $replaced = $this->patchJson('/api/v1/content-governance/featured/'.$placement['public_id'], [
            'content_public_id' => $replacement->public_id,
            'concurrency_token' => $updated['concurrency_token'],
        ])->assertOk()->assertJsonPath('data.content.public_id', $replacement->public_id)->json('data');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.featured_placement_changed',
        ]);
        $this->assertStringContainsString(
            'replaced',
            AuditLog::query()->where('action', 'content.featured_placement_changed')->latest('id')->firstOrFail()->toJson(),
        );
        $this->deleteJson('/api/v1/content-governance/featured/'.$placement['public_id'], [
            'concurrency_token' => $placement['concurrency_token'],
        ])->assertStatus(409)->assertJsonPath('error_code', 'content_featured_stale');
        $this->deleteJson('/api/v1/content-governance/featured/'.$placement['public_id'], [
            'concurrency_token' => $replaced['concurrency_token'],
        ])->assertOk();

        $expiredArticle = $this->publishedGlobalArticle('Pernah Diunggulkan');
        $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $expiredArticle->public_id,
            'scope' => 'global',
            'rank' => 3,
            'active_until' => now()->subMinute()->toJSON(),
        ])->assertCreated()->assertJsonPath('data.state', 'expired');
        $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $this->publishedGlobalArticle('Pengganti Rank')->public_id,
            'scope' => 'global',
            'rank' => 3,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/content-governance/featured')->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertGreaterThanOrEqual(5, AuditLog::query()->where('action', 'content.featured_placement_changed')->count());
    }

    private function campusDraft(string $title): ContentItem
    {
        return app(ContentPublicationService::class)->createDraft(
            $this->admin,
            $this->articlePayload(ContentScope::Campus, $this->campus, $title),
        );
    }

    private function submittedCampusArticle(string $title): ContentItem
    {
        $item = $this->campusDraft($title);

        return app(ContentPublicationService::class)->submit(
            $item->currentDraftVersion,
            $this->admin,
            (int) $item->lock_version,
        );
    }

    private function publishedGlobalArticle(string $title): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $payload = $this->articlePayload(ContentScope::Global, null, $title);
        $item = $service->createDraft($this->reviewer, $payload);
        $secondReviewer = $this->user('super_admin');
        $item = $service->submit($item->currentDraftVersion, $this->reviewer, (int) $item->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $secondReviewer, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $secondReviewer, (int) $item->lock_version);

        return $service->publishApproved($item->currentDraftVersion, $secondReviewer, (int) $item->lock_version);
    }

    /** @return array<string, mixed> */
    private function articlePayload(ContentScope $scope, ?University $campus, string $title): array
    {
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();

        return [
            'content_type' => 'article',
            'section_code' => 'education',
            'category_public_id' => $category->public_id,
            'scope' => $scope->value,
            'university_id' => $campus?->id,
            'title' => $title,
            'excerpt' => 'Ringkasan edukasi yang aman dan memerlukan verifikasi editorial.',
            'document' => $this->document('Isi edukasi yang aman dan tidak memuat data sensitif.'),
            'requires_editorial_review' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]]];
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
