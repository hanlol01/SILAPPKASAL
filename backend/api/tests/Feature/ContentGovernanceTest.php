<?php

namespace Tests\Feature;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentReviewDecisionCode;
use App\Enums\ContentScope;
use App\Models\AuditLog;
use App\Models\ContentAttachment;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentReviewDecision;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Models\FeaturedContent;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentGovernanceQueryService;
use App\Services\ContentManagementQueryService;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ->assertJsonPath('data.0.created_by.name', $this->admin->name)
            ->assertJsonPath('data.0.submitted_by.name', $this->admin->name)
            ->assertJsonPath('data.0.submitted_by.email', $this->admin->email)
            ->assertJsonPath('data.0.version.submitted_at', fn (mixed $value): bool => is_string($value))
            ->assertJsonPath('data.0.capabilities.start_review', true)
            ->assertJsonMissing(['creator_id'])
            ->assertJsonMissing(['submitted_by' => $this->reviewer->name])
            ->assertJsonMissing(['university_id']);
        $this->assertDatabaseHas('content_versions', [
            'id' => $submitted->current_draft_version_id,
            'submitted_by' => $this->admin->id,
        ]);
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

    public function test_editorial_attribution_migration_rolls_back_and_reapplies_on_sqlite(): void
    {
        $item = $this->submittedCampusArticle('Preservasi Migration Attribution');
        $item = app(ContentPublicationService::class)->startReview(
            $item->currentDraftVersion,
            $this->reviewer,
            (int) $item->lock_version,
        );
        $version = $item->currentDraftVersion;
        ContentAttachment::query()->create([
            'content_version_id' => $version->id,
            'purpose' => 'attachment',
            'storage_disk' => 'local',
            'storage_path' => 'test/'.Str::uuid().'.pdf',
            'safe_filename' => 'preservation-test.pdf',
            'original_filename' => 'preservation-test.pdf',
            'detected_mime' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 512,
            'checksum_sha256' => str_repeat('a', 64),
            'display_order' => 0,
            'uploader_id' => $this->admin->id,
        ]);
        $pointerBefore = DB::table('content_items')->where('id', $item->id)
            ->first(['current_draft_version_id', 'published_version_id']);
        $versionBefore = DB::table('content_versions')->where('id', $version->id)
            ->first([
                'id', 'public_id', 'content_item_id', 'version_number', 'lifecycle_status',
                'title', 'submitted_at', 'review_started_at',
            ]);
        $attachmentBefore = DB::table('content_attachments')
            ->where('content_version_id', $version->id)->orderBy('id')->get()->toArray();
        $decisionBefore = DB::table('content_review_decisions')
            ->where('content_version_id', $version->id)->orderBy('id')->get()->toArray();
        $articleBefore = DB::table('article_version_contents')
            ->where('content_version_id', $version->id)->first();

        $migration = require database_path('migrations/2026_07_23_030000_add_editorial_attribution_to_content_versions.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('content_versions', 'submitted_by'));
        $this->assertFalse(Schema::hasColumn('content_versions', 'published_by'));
        $this->assertEquals($pointerBefore, DB::table('content_items')->where('id', $item->id)
            ->first(['current_draft_version_id', 'published_version_id']));
        $this->assertEquals($versionBefore, DB::table('content_versions')->where('id', $version->id)
            ->first([
                'id', 'public_id', 'content_item_id', 'version_number', 'lifecycle_status',
                'title', 'submitted_at', 'review_started_at',
            ]));
        $this->assertEquals($attachmentBefore, DB::table('content_attachments')
            ->where('content_version_id', $version->id)->orderBy('id')->get()->toArray());
        $this->assertEquals($decisionBefore, DB::table('content_review_decisions')
            ->where('content_version_id', $version->id)->orderBy('id')->get()->toArray());
        $this->assertEquals($articleBefore, DB::table('article_version_contents')
            ->where('content_version_id', $version->id)->first());

        $migration->up();
        $this->assertTrue(Schema::hasColumn('content_versions', 'submitted_by'));
        $this->assertTrue(Schema::hasColumn('content_versions', 'published_by'));
        $this->assertEquals($pointerBefore, DB::table('content_items')->where('id', $item->id)
            ->first(['current_draft_version_id', 'published_version_id']));
        $this->assertEquals($versionBefore, DB::table('content_versions')->where('id', $version->id)
            ->first([
                'id', 'public_id', 'content_item_id', 'version_number', 'lifecycle_status',
                'title', 'submitted_at', 'review_started_at',
            ]));
        $this->assertEquals($attachmentBefore, DB::table('content_attachments')
            ->where('content_version_id', $version->id)->orderBy('id')->get()->toArray());
        $this->assertEquals($decisionBefore, DB::table('content_review_decisions')
            ->where('content_version_id', $version->id)->orderBy('id')->get()->toArray());
        $this->assertEquals($articleBefore, DB::table('article_version_contents')
            ->where('content_version_id', $version->id)->first());
    }

    public function test_sqlite_attribution_migration_is_atomic_when_dependent_restore_fails(): void
    {
        $item = $this->campusDraft('Atomic Migration Failure');
        $version = $item->currentDraftVersion;
        $attachment = ContentAttachment::query()->create([
            'content_version_id' => $version->id,
            'purpose' => 'attachment',
            'storage_disk' => 'local',
            'storage_path' => 'test/'.Str::uuid().'.pdf',
            'safe_filename' => 'atomic-test.pdf',
            'original_filename' => 'atomic-test.pdf',
            'detected_mime' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => 256,
            'checksum_sha256' => str_repeat('b', 64),
            'display_order' => 0,
            'uploader_id' => $this->admin->id,
        ]);
        $pointerBefore = DB::table('content_items')->where('id', $item->id)
            ->first(['current_draft_version_id', 'published_version_id']);
        $attachmentBefore = DB::table('content_attachments')->where('id', $attachment->id)->first();
        DB::statement(
            "CREATE TRIGGER fail_content_attachment_restore_insert
            BEFORE INSERT ON content_attachments
            BEGIN SELECT RAISE(ABORT, 'forced dependent restore failure'); END",
        );
        DB::statement(
            "CREATE TRIGGER fail_content_attachment_restore_update
            BEFORE UPDATE ON content_attachments
            BEGIN SELECT RAISE(ABORT, 'forced dependent restore failure'); END",
        );
        $migration = require database_path('migrations/2026_07_23_030000_add_editorial_attribution_to_content_versions.php');

        try {
            $migration->down();
            $this->fail('The forced SQLite dependent restoration failure was not raised.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('forced dependent restore failure', $exception->getMessage());
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_content_attachment_restore_insert');
            DB::statement('DROP TRIGGER IF EXISTS fail_content_attachment_restore_update');
        }

        $this->assertTrue(Schema::hasColumn('content_versions', 'submitted_by'));
        $this->assertTrue(Schema::hasColumn('content_versions', 'published_by'));
        $this->assertEquals($pointerBefore, DB::table('content_items')->where('id', $item->id)
            ->first(['current_draft_version_id', 'published_version_id']));
        $this->assertEquals(
            $attachmentBefore,
            DB::table('content_attachments')->where('id', $attachment->id)->first(),
        );
        $this->assertDatabaseHas('content_versions', ['id' => $version->id]);
    }

    public function test_attribution_is_action_safe_deterministic_and_campus_timeline_masks_internal_actors(): void
    {
        $service = app(ContentPublicationService::class);
        $secondReviewer = $this->user('super_admin');
        $item = $this->submittedCampusArticle('Atribusi Deterministik');
        $sameTimestamp = now()->startOfSecond();
        $this->travelTo($sameTimestamp);

        $item = $service->startReview(
            $item->currentDraftVersion,
            $this->reviewer,
            (int) $item->lock_version,
        );
        $item = $service->requestRevision(
            $item->currentDraftVersion,
            $this->reviewer,
            'Catatan pertama untuk urutan deterministik.',
            (int) $item->lock_version,
        );
        $item = $service->updateDraft($item->currentDraftVersion, $this->admin, [
            'excerpt' => 'Diperbaiki setelah catatan pertama.',
        ]);
        $item = $service->submit(
            $item->currentDraftVersion,
            $this->admin,
            (int) $item->lock_version,
        );
        $item = $service->startReview(
            $item->currentDraftVersion,
            $secondReviewer,
            (int) $item->lock_version,
        );
        $item = $service->requestRevision(
            $item->currentDraftVersion,
            $secondReviewer,
            'Catatan kedua harus menjadi feedback terbaru.',
            (int) $item->lock_version,
        );
        $version = $item->currentDraftVersion;
        ContentReviewDecision::query()->create([
            'content_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'decision_code' => ContentReviewDecisionCode::Approved,
            'decided_at' => $sameTimestamp,
        ]);
        ContentReviewDecision::query()->create([
            'content_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'decision_code' => ContentReviewDecisionCode::DirectGlobalPublished,
            'decided_at' => $sameTimestamp,
        ]);
        foreach (range(1, 24) as $index) {
            ContentReviewDecision::query()->create([
                'content_version_id' => $version->id,
                'reviewer_id' => $this->reviewer->id,
                'decision_code' => ContentReviewDecisionCode::DirectGlobalPublished,
                'decided_at' => $sameTimestamp->copy()->addSeconds($index),
            ]);
        }
        ContentReviewDecision::query()->create([
            'content_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'decision_code' => ContentReviewDecisionCode::Archived,
            'narrative_reason' => 'Aksi administratif tidak boleh menjadi reviewer terbaru.',
            'decided_at' => $sameTimestamp,
        ]);
        $this->travelBack();

        Sanctum::actingAs($this->admin, ['*']);
        $adminResponse = $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.created_by.email', $this->admin->email)
            ->assertJsonPath('data.submitted_by.email', $this->admin->email)
            ->assertJsonPath('data.reviewed_by', null)
            ->assertJsonPath('data.approved_by', null)
            ->assertJsonPath('data.published_by', null)
            ->assertJsonPath('data.review_feedback.reason', 'Catatan kedua harus menjadi feedback terbaru.')
            ->assertJsonPath('data.editorial_timeline_truncated', false);
        $adminTimeline = collect($adminResponse->json('data.editorial_timeline'));
        $this->assertSame(
            ['Catatan pertama untuk urutan deterministik.', 'Catatan kedua harus menjadi feedback terbaru.'],
            $adminTimeline->where('state', 'revision_requested')->pluck('note')->values()->all(),
        );
        $internalEvents = $adminTimeline->whereIn('state', [
            'review_started', 'revision_requested', 'approved', 'published', 'archived',
        ]);
        $this->assertNotEmpty($internalEvents);
        $this->assertTrue($internalEvents->every(
            fn (array $event): bool => $event['actor']['label'] === 'central_team'
                && $event['actor']['name'] === null
                && $event['actor']['email'] === null
                && $event['actor']['role'] === null,
        ));
        $this->assertStringNotContainsString($this->reviewer->email, $adminResponse->getContent());
        $this->assertStringNotContainsString($secondReviewer->email, $adminResponse->getContent());
        $adminPage = app(ContentManagementQueryService::class)->items($this->admin, [
            'lifecycle_status' => ContentLifecycleStatus::RevisionRequested->value,
        ]);
        $adminVersion = collect($adminPage->items())
            ->firstWhere('public_id', $item->public_id)
            ?->currentDraftVersion;
        $this->assertNotNull($adminVersion);
        $this->assertFalse($adminVersion->relationLoaded('reviewDecisions'));
        $this->assertFalse($adminVersion->relationLoaded('latestReviewAttributionDecision'));
        $this->assertFalse($adminVersion->relationLoaded('latestApprovalDecision'));
        $this->assertFalse($adminVersion->relationLoaded('publisher'));

        Sanctum::actingAs($this->reviewer, ['*']);
        $governance = $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.reviewed_by.email', $secondReviewer->email)
            ->assertJsonPath('data.approved_by.email', $this->reviewer->email)
            ->assertJsonPath('data.published_by', null)
            ->assertJsonPath('data.decision_history_truncated', false);
        $this->assertSame(
            [$this->reviewer->email, $secondReviewer->email],
            collect($governance->json('data.decision_history'))
                ->where('state', 'review_started')
                ->pluck('actor.email')
                ->values()
                ->all(),
        );

        $page = app(ContentGovernanceQueryService::class)->reviewQueue($this->reviewer, [
            'lifecycle_status' => ContentLifecycleStatus::RevisionRequested->value,
        ]);
        $projectedVersion = collect($page->items())
            ->firstWhere('public_id', $item->public_id)
            ?->currentDraftVersion;
        $this->assertNotNull($projectedVersion);
        $this->assertFalse($projectedVersion->relationLoaded('reviewDecisions'));
        $this->assertTrue($projectedVersion->relationLoaded('latestReviewAttributionDecision'));
        $this->assertTrue($projectedVersion->relationLoaded('latestApprovalDecision'));
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

    public function test_resubmission_keeps_both_submission_events_and_latest_submitter_attribution(): void
    {
        $service = app(ContentPublicationService::class);
        $item = $this->submittedCampusArticle('Artikel Dikirim Ulang');
        $item = $service->startReview(
            $item->currentDraftVersion,
            $this->reviewer,
            (int) $item->lock_version,
        );
        $item = $service->requestRevision(
            $item->currentDraftVersion,
            $this->reviewer,
            'Perbarui referensi dan penjelasan layanan kampus.',
            (int) $item->lock_version,
        );
        $item = $service->updateDraft($item->currentDraftVersion, $this->admin, [
            'excerpt' => 'Ringkasan telah diperbarui setelah review.',
        ]);
        $item = $service->submit(
            $item->currentDraftVersion,
            $this->admin,
            (int) $item->lock_version,
        );

        $this->assertSame($this->admin->id, $item->currentDraftVersion->submitted_by);
        $this->assertSame(
            2,
            AuditLog::query()
                ->where('subject_id', $item->id)
                ->where('action', 'content.submitted')
                ->count(),
        );

        Sanctum::actingAs($this->reviewer, ['*']);
        $history = $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.submitted_by.email', $this->admin->email)
            ->json('data.decision_history');
        $this->assertCount(2, collect($history)->where('state', 'submitted'));
        $this->assertSame(
            ['draft', 'draft'],
            collect($history)->where('state', 'submitted')->pluck('from_status')->values()->all(),
        );
        $this->assertCount(1, collect($history)->where('state', 'revision_requested'));
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

        foreach (['reporter', 'satgas_ppks'] as $readerRole) {
            Sanctum::actingAs($this->user($readerRole, $this->campus), ['*']);
            $this->getJson('/api/v1/content/articles/'.$item->public_id)
                ->assertOk()
                ->assertJsonPath('data.title', 'Versi Pertama')
                ->assertJsonMissingPath('data.created_by')
                ->assertJsonMissingPath('data.submitted_by')
                ->assertJsonMissingPath('data.reviewed_by')
                ->assertJsonMissingPath('data.approved_by')
                ->assertJsonMissingPath('data.published_by')
                ->assertJsonMissingPath('data.decision_history')
                ->assertJsonMissingPath('data.editorial_timeline')
                ->assertJsonMissing(['email']);
        }

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
        $this->assertSame($this->reviewer->id, $item->publishedVersion->published_by);
        $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.submitted_by.email', $this->admin->email)
            ->assertJsonPath('data.reviewed_by.email', $this->reviewer->email)
            ->assertJsonPath('data.approved_by.email', $this->reviewer->email)
            ->assertJsonPath('data.published_by.email', $this->reviewer->email);
    }

    public function test_super_admin_global_authoring_requires_a_distinct_reviewer_and_campus_cannot_escalate(): void
    {
        Sanctum::actingAs($this->reviewer, ['*']);
        $payload = $this->articlePayload(ContentScope::Global, null, 'Konten Global C3');
        $created = $this->postJson('/api/v1/content-management/items', $payload)
            ->assertCreated()
            ->assertJsonPath('data.scope', 'global')
            ->assertJsonPath('data.university', null)
            ->assertJsonPath('data.created_by.email', $this->reviewer->email)
            ->assertJsonPath('data.lifecycle_status', 'draft')
            ->assertJsonPath('data.published_version', null)
            ->json('data');
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
        ])->assertOk()
            ->assertJsonPath('data.submitted_by.email', $this->reviewer->email)
            ->assertJsonPath('data.version.submitted_at', fn (mixed $value): bool => is_string($value));

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
        ])->assertOk()
            ->assertJsonPath('data.reviewed_by.email', $secondReviewer->email)
            ->assertJsonPath('data.approved_by.email', $secondReviewer->email)
            ->assertJsonPath('data.published_by.email', $secondReviewer->email);
        $this->assertDatabaseHas('content_versions', [
            'public_id' => $created['version']['public_id'],
            'submitted_by' => $this->reviewer->id,
            'published_by' => $secondReviewer->id,
        ]);
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
            ->assertJsonFragment(['state' => 'draft_created'])
            ->assertJsonFragment(['state' => 'version_created'])
            ->assertJsonFragment(['state' => 'submitted']);
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
            ->assertJsonFragment(['state' => 'archived']);

        Sanctum::actingAs($this->user('reporter', $this->campus), ['*']);
        $this->getJson('/api/v1/content/articles/'.$item->public_id)->assertNotFound();
    }

    public function test_featured_governance_enforces_eligibility_rank_windows_and_concurrency(): void
    {
        $published = $this->publishedGlobalArticle('Unggulan Terbit');
        $policy = $this->publishedGlobalPolicy('Kebijakan Tidak Boleh Disorot');
        $draft = app(ContentPublicationService::class)->createDraft(
            $this->reviewer,
            $this->articlePayload(ContentScope::Global, null, 'Belum Terbit'),
        );
        Sanctum::actingAs($this->reviewer, ['*']);
        $this->getJson('/api/v1/content-governance/featured/eligible?scope=global')
            ->assertOk()
            ->assertJsonFragment(['public_id' => $published->public_id])
            ->assertJsonMissing(['public_id' => $policy->public_id]);
        $this->postJson('/api/v1/content-governance/featured', [
            'content_public_id' => $policy->public_id,
            'scope' => 'global',
            'rank' => 5,
        ])->assertNotFound();
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

        FeaturedContent::query()->create([
            'scope' => 'global',
            'content_item_id' => $policy->id,
            'rank' => 5,
            'creator_id' => $this->reviewer->id,
        ]);
        Sanctum::actingAs($this->user('reporter', $this->campus), ['*']);
        $this->getJson('/api/v1/content/featured?section=education')
            ->assertOk()
            ->assertJsonMissing(['public_id' => $policy->public_id]);
        Sanctum::actingAs($this->reviewer, ['*']);

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

    private function publishedGlobalPolicy(string $title): ContentItem
    {
        $service = app(ContentPublicationService::class);
        $payload = $this->articlePayload(ContentScope::Global, null, $title);
        $policySection = ContentSection::query()->where('code', 'policy')->firstOrFail();
        $policyCategory = ContentCategory::query()
            ->where('section_id', $policySection->id)
            ->where('scope', ContentScope::Global->value)
            ->firstOrFail();
        $payload['section_code'] = 'policy';
        $payload['category_public_id'] = $policyCategory->public_id;
        $payload['category_name'] = $policyCategory->name;
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
            'category_name' => $category->name,
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
