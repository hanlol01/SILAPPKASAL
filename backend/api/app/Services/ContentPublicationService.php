<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentReviewDecisionCode;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ArticleVersionContent;
use App\Models\ConsultationVersionContent;
use App\Models\ContentAttachment;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentReviewDecision;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Models\FaqVersionContent;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use App\Support\ApiErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContentPublicationService
{
    public function __construct(
        private readonly ContentItemPolicy $policy,
        private readonly ContentDocumentService $documents,
        private readonly ContentContactNormalizer $contacts,
        private readonly AuditLogService $auditLogs,
        private readonly ContentRevisionAttachmentService $revisionAttachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(User $actor, array $data): ContentItem
    {
        return DB::transaction(function () use ($actor, $data): ContentItem {
            $actor = $this->lockedActor($actor);
            $type = ContentType::from((string) $data['content_type']);
            $scope = ContentScope::from((string) $data['scope']);
            $universityId = $scope === ContentScope::Campus
                ? (int) ($data['university_id'] ?? $actor->university_id ?? 0)
                : null;

            $this->authorizeCreation($actor, $scope, $universityId);

            $section = ContentSection::query()
                ->where('code', $data['section_code'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $category = $this->resolveCategory($data['category_public_id'] ?? null, $section, $scope, $universityId);
            $this->validateTypePlacement($type, $section, $category);

            $title = trim((string) $data['title']);
            $slug = $this->availableSlug($type, $scope, $universityId, (string) ($data['slug'] ?? $title));

            try {
                $item = ContentItem::query()->create([
                    'content_type' => $type,
                    'section_id' => $section->id,
                    'category_id' => $category?->id,
                    'slug' => $slug,
                    'scope' => $scope,
                    'university_id' => $universityId,
                    'creator_id' => $actor->id,
                    'lock_version' => 1,
                ]);

                $version = ContentVersion::query()->create([
                    'content_item_id' => $item->id,
                    'version_number' => 1,
                    'lifecycle_status' => ContentLifecycleStatus::Draft,
                    'title' => $title,
                    'excerpt' => $this->plainExcerpt($data['excerpt'] ?? null),
                    'author_id' => $actor->id,
                    'editor_id' => $actor->id,
                    'source_type' => 'manual',
                    'requires_editorial_review' => (bool) ($data['requires_editorial_review'] ?? false),
                ]);

                $this->writeTypedContent($version, $type, $data);
                $item->forceFill(['current_draft_version_id' => $version->id])->save();
            } catch (QueryException $exception) {
                if ($this->isIntegrityViolation($exception)) {
                    throw ValidationException::withMessages(['slug' => ['A content item or editable version already exists for this key.']]);
                }

                throw $exception;
            }

            $item = $this->loadManagement($item);
            $this->record(AuditAction::ContentItemCreated, $actor, $item, $version);
            $this->record(AuditAction::ContentVersionCreated, $actor, $item, $version);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(ContentVersion $version, User $actor, array $data): ContentItem
    {
        return DB::transaction(function () use ($version, $actor, $data): ContentItem {
            $actor = $this->lockedActor($actor);
            $version = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->firstOrFail();

            $this->ensureNotArchived($item);

            if (! $this->policy->updateDraft($actor, $item, $version)) {
                throw $this->forbidden();
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $item->lock_version) {
                throw $this->conflict(
                    'Content changed after it was loaded. Reload the authoritative version before continuing.',
                    ApiErrorCode::ContentStaleVersion,
                );
            }

            if (array_key_exists('section_code', $data) || array_key_exists('category_public_id', $data)) {
                $section = array_key_exists('section_code', $data)
                    ? ContentSection::query()->where('code', $data['section_code'])->where('is_active', true)->lockForUpdate()->firstOrFail()
                    : $item->section()->lockForUpdate()->firstOrFail();
                $categoryPublicId = array_key_exists('category_public_id', $data)
                    ? $data['category_public_id']
                    : $item->category?->public_id;
                $category = $this->resolveCategory($categoryPublicId, $section, $item->scope, $item->university_id);
                $this->validateTypePlacement($item->content_type, $section, $category);

                $placementChanged = (int) $item->section_id !== (int) $section->id
                    || (int) $item->category_id !== (int) $category?->id;
                if ($placementChanged && $item->published_version_id !== null) {
                    throw ValidationException::withMessages([
                        'category_public_id' => ['Section and category cannot change after the first publication.'],
                    ]);
                }
                $item->section_id = $section->id;
                $item->category_id = $category?->id;
            }

            $fromStatus = $version->lifecycle_status->value;
            if ($version->lifecycle_status === ContentLifecycleStatus::RevisionRequested) {
                $version->lifecycle_status = ContentLifecycleStatus::Draft;
            }
            $version->fill(array_filter([
                'title' => isset($data['title']) ? trim((string) $data['title']) : null,
                'excerpt' => array_key_exists('excerpt', $data) ? $this->plainExcerpt($data['excerpt']) : null,
                'editor_id' => $actor->id,
                'requires_editorial_review' => $data['requires_editorial_review'] ?? null,
            ], static fn (mixed $value): bool => $value !== null))->save();

            $this->writeTypedContent($version, $item->content_type, $data, true);
            $item->forceFill(['lock_version' => $item->lock_version + 1])->save();

            $item = $this->loadManagement($item);
            $this->record(AuditAction::ContentDraftUpdated, $actor, $item, $version, $fromStatus);

            return $item;
        });
    }

    public function submit(ContentVersion $version, User $actor, int $expectedLockVersion): ContentItem
    {
        return DB::transaction(function () use ($version, $actor, $expectedLockVersion): ContentItem {
            $actor = $this->lockedActor($actor);
            $version = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->firstOrFail();

            $this->ensureNotArchived($item);

            if (! $this->policy->submit($actor, $item, $version)) {
                throw $this->forbidden();
            }
            if ($expectedLockVersion !== (int) $item->lock_version) {
                throw $this->conflict(
                    'Content changed after it was loaded. Reload the authoritative version before submitting.',
                    ApiErrorCode::ContentStaleVersion,
                );
            }

            $this->ensurePublishablePayload($version, false);
            $from = $version->lifecycle_status->value;
            $version->forceFill([
                'lifecycle_status' => ContentLifecycleStatus::Submitted,
                'submitted_at' => now(),
                'editor_id' => $actor->id,
            ])->save();
            $item->forceFill(['lock_version' => $item->lock_version + 1])->save();

            $item = $this->loadManagement($item);
            $this->record(AuditAction::ContentSubmitted, $actor, $item, $version, $from);

            return $item;
        });
    }

    public function startReview(ContentVersion $version, User $actor, int $expectedLockVersion): ContentItem
    {
        return $this->reviewTransition(
            $version,
            $actor,
            ContentLifecycleStatus::Submitted,
            ContentLifecycleStatus::InReview,
            ContentReviewDecisionCode::ReviewStarted,
            AuditAction::ContentReviewStarted,
            expectedLockVersion: $expectedLockVersion,
        );
    }

    public function approve(ContentVersion $version, User $actor, int $expectedLockVersion, ?string $note = null): ContentItem
    {
        return $this->reviewTransition(
            $version,
            $actor,
            ContentLifecycleStatus::InReview,
            ContentLifecycleStatus::Approved,
            ContentReviewDecisionCode::Approved,
            AuditAction::ContentApproved,
            expectedLockVersion: $expectedLockVersion,
            reason: $note,
        );
    }

    public function requestRevision(ContentVersion $version, User $actor, string $reason, int $expectedLockVersion): ContentItem
    {
        return $this->reviewTransition(
            $version,
            $actor,
            ContentLifecycleStatus::InReview,
            ContentLifecycleStatus::RevisionRequested,
            ContentReviewDecisionCode::RevisionRequested,
            AuditAction::ContentRevisionRequested,
            expectedLockVersion: $expectedLockVersion,
            reason: $reason,
        );
    }

    public function reject(ContentVersion $version, User $actor, string $reason, int $expectedLockVersion): ContentItem
    {
        return $this->reviewTransition(
            $version,
            $actor,
            ContentLifecycleStatus::InReview,
            ContentLifecycleStatus::Rejected,
            ContentReviewDecisionCode::Rejected,
            AuditAction::ContentRejected,
            expectedLockVersion: $expectedLockVersion,
            reason: $reason,
        );
    }

    public function archive(ContentItem $item, User $actor, string $reason, int $expectedLockVersion): ContentItem
    {
        return DB::transaction(function () use ($item, $actor, $reason, $expectedLockVersion): ContentItem {
            $actor = $this->lockedActor($actor);
            $item = ContentItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $reason = trim($reason);

            if (! $this->policy->archive($actor) || $item->published_version_id === null) {
                throw $this->forbidden();
            }
            $this->assertLockVersion($item, $expectedLockVersion, ApiErrorCode::ContentStaleReview);
            if ($item->current_draft_version_id !== null) {
                throw $this->conflict(
                    'Resolve the active authoring version before archiving this content item.',
                    ApiErrorCode::ContentActiveAuthoringVersion,
                );
            }
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => ['An archive reason is required.']]);
            }

            $version = ContentVersion::query()->whereKey($item->published_version_id)->lockForUpdate()->firstOrFail();
            ContentReviewDecision::query()->create([
                'content_version_id' => $version->id,
                'reviewer_id' => $actor->id,
                'decision_code' => ContentReviewDecisionCode::Archived,
                'narrative_reason' => $reason,
                'decided_at' => now(),
            ]);
            $item->forceFill([
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
                'lock_version' => $item->lock_version + 1,
            ])->save();

            $item = $this->loadManagement($item);
            $this->record(AuditAction::ContentArchived, $actor, $item, $version, ContentLifecycleStatus::Published->value);

            return $item;
        });
    }

    public function createRevision(ContentItem $item, User $actor, int $expectedLockVersion): ContentItem
    {
        $clonedPaths = [];

        try {
            return DB::transaction(function () use ($item, $actor, $expectedLockVersion, &$clonedPaths): ContentItem {
                $actor = $this->lockedActor($actor);
                $item = ContentItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                $this->ensureNotArchived($item);
                $published = ContentVersion::query()->whereKey($item->published_version_id)->first();

                if ($published === null || ! $this->policy->createRevision($actor, $item)) {
                    throw $this->forbidden();
                }
                $this->assertLockVersion($item, $expectedLockVersion, ApiErrorCode::ContentStaleVersion);

                if ($item->current_draft_version_id !== null) {
                    throw $this->conflict('An authoring version already exists for this content item.');
                }

                $nextVersion = (int) $item->versions()->lockForUpdate()->max('version_number') + 1;
                $revision = ContentVersion::query()->create([
                    'content_item_id' => $item->id,
                    'version_number' => $nextVersion,
                    'lifecycle_status' => ContentLifecycleStatus::Draft,
                    'title' => $published->title,
                    'excerpt' => $published->excerpt,
                    'author_id' => $actor->id,
                    'editor_id' => $actor->id,
                    'source_type' => 'revision',
                    'requires_editorial_review' => true,
                ]);

                $attachmentMap = $this->revisionAttachments->clone($published, $revision, $actor);
                $clonedPaths = $attachmentMap['paths'];
                $this->copyTypedContent($published, $revision, $item->content_type, $attachmentMap);
                $item->forceFill([
                    'current_draft_version_id' => $revision->id,
                    'lock_version' => $item->lock_version + 1,
                ])->save();

                $item = $this->loadManagement($item);
                $this->record(AuditAction::ContentVersionCreated, $actor, $item, $revision);

                return $item;
            });
        } catch (Throwable $exception) {
            $this->revisionAttachments->cleanup($clonedPaths);

            throw $exception;
        }
    }

    private function reviewTransition(
        ContentVersion $version,
        User $actor,
        ContentLifecycleStatus $required,
        ContentLifecycleStatus $target,
        ContentReviewDecisionCode $decision,
        AuditAction $action,
        int $expectedLockVersion,
        ?string $reason = null,
    ): ContentItem {
        return DB::transaction(function () use ($version, $actor, $required, $target, $decision, $action, $reason, $expectedLockVersion): ContentItem {
            $actor = $this->lockedActor($actor);
            $version = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->firstOrFail();

            $this->ensureNotArchived($item);
            if (! $this->policy->review($actor, $item, $version)) {
                throw $this->forbidden();
            }
            $this->assertLockVersion($item, $expectedLockVersion, ApiErrorCode::ContentStaleReview);
            if ((int) $item->current_draft_version_id !== (int) $version->id) {
                throw $this->invalidLifecycle($required, $version->lifecycle_status);
            }
            if ($version->lifecycle_status !== $required) {
                throw $this->invalidLifecycle($required, $version->lifecycle_status);
            }
            $reason = $reason === null ? null : trim($reason);
            if ($decision->requiresReason() && blank($reason)) {
                throw ValidationException::withMessages(['reason' => ['A review reason is required for this decision.']]);
            }
            if ($target === ContentLifecycleStatus::Approved) {
                $version->forceFill(['requires_editorial_review' => false])->save();
                $this->ensurePublishablePayload($version, true);
            }

            $now = now();
            $timestamps = match ($target) {
                ContentLifecycleStatus::InReview => ['review_started_at' => $now],
                ContentLifecycleStatus::Approved => ['reviewed_at' => $now, 'approved_at' => $now],
                ContentLifecycleStatus::RevisionRequested => ['reviewed_at' => $now, 'revision_requested_at' => $now],
                ContentLifecycleStatus::Rejected => ['reviewed_at' => $now, 'rejected_at' => $now],
                default => [],
            };
            $from = $version->lifecycle_status->value;
            $version->forceFill(['lifecycle_status' => $target, ...$timestamps])->save();
            ContentReviewDecision::query()->create([
                'content_version_id' => $version->id,
                'reviewer_id' => $actor->id,
                'decision_code' => $decision,
                'narrative_reason' => $reason,
                'decided_at' => $now,
            ]);
            if ($target === ContentLifecycleStatus::Rejected && (int) $item->current_draft_version_id === (int) $version->id) {
                $item->current_draft_version_id = null;
            }
            $item->lock_version++;
            $item->save();

            $item = $this->loadManagement($item);
            $this->record($action, $actor, $item, $version, $from);

            return $item;
        });
    }

    public function publishApproved(ContentVersion $version, User $actor, int $expectedLockVersion): ContentItem
    {
        return $this->publish($version, $actor, false, $expectedLockVersion);
    }

    public function directGlobalPublish(ContentVersion $version, User $actor, int $expectedLockVersion): ContentItem
    {
        return $this->publish($version, $actor, true, $expectedLockVersion);
    }

    private function publish(ContentVersion $version, User $actor, bool $directGlobal, int $expectedLockVersion): ContentItem
    {
        return DB::transaction(function () use ($version, $actor, $directGlobal, $expectedLockVersion): ContentItem {
            $actor = $this->lockedActor($actor);
            $version = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->firstOrFail();

            $this->ensureNotArchived($item);
            if ($directGlobal) {
                if (! $this->policy->publishGlobal($actor, $item)) {
                    throw $this->forbidden();
                }
            } elseif (! $this->policy->review($actor, $item, $version)) {
                throw $this->forbidden();
            }
            $this->assertLockVersion($item, $expectedLockVersion, ApiErrorCode::ContentStaleReview);
            $requiredStatus = $directGlobal ? null : ContentLifecycleStatus::Approved;
            if ((int) $item->current_draft_version_id !== (int) $version->id) {
                throw $this->invalidLifecycle($requiredStatus, $version->lifecycle_status);
            }
            if (($directGlobal && ! $version->lifecycle_status?->editable())
                || ($requiredStatus !== null && $version->lifecycle_status !== $requiredStatus)) {
                throw $this->invalidLifecycle($requiredStatus, $version->lifecycle_status);
            }

            $this->ensurePublishablePayload($version, true);
            $from = $version->lifecycle_status->value;
            $now = now();
            $version->forceFill([
                'lifecycle_status' => ContentLifecycleStatus::Published,
                'reviewed_at' => $version->reviewed_at ?? $now,
                'approved_at' => $version->approved_at ?? $now,
                'published_at' => $now,
            ])->save();

            if ($directGlobal) {
                ContentReviewDecision::query()->create([
                    'content_version_id' => $version->id,
                    'reviewer_id' => $actor->id,
                    'decision_code' => ContentReviewDecisionCode::DirectGlobalPublished,
                    'decided_at' => $now,
                ]);
            }

            $item->forceFill([
                'published_version_id' => $version->id,
                'current_draft_version_id' => $item->current_draft_version_id === $version->id
                    ? null
                    : $item->current_draft_version_id,
                'lock_version' => $item->lock_version + 1,
            ])->save();

            $item = $this->loadManagement($item);
            $this->record(
                $directGlobal ? AuditAction::ContentDirectGlobalPublished : AuditAction::ContentPublished,
                $actor,
                $item,
                $version,
                $from,
            );

            return $item;
        });
    }

    /** @param array<string, mixed> $data */
    private function writeTypedContent(ContentVersion $version, ContentType $type, array $data, bool $partial = false): void
    {
        match ($type) {
            ContentType::Article => $this->writeArticle($version, $data, $partial),
            ContentType::Faq => $this->writeFaq($version, $data, $partial),
            ContentType::Consultation => $this->writeConsultation($version, $data, $partial),
        };
    }

    /** @param array<string, mixed> $data */
    private function writeArticle(ContentVersion $version, array $data, bool $partial): void
    {
        $attributes = [];

        if (array_key_exists('document', $data) && is_array($data['document'])) {
            $prepared = $this->documents->prepareArticle($data['document']);
            $this->assertArticleImageReferencesOwned($version, $prepared['document']);
            $attributes = [
                'document_json' => $prepared['document'],
                'sanitized_html' => $prepared['html'],
                'search_text' => $prepared['text'],
                'estimated_reading_minutes' => $prepared['reading_minutes'],
            ];
        }

        if (array_key_exists('cover_alt_text', $data)) {
            $attributes['cover_alt_text'] = $data['cover_alt_text'];
        }
        if (array_key_exists('consultation_cta_public_id', $data)) {
            $attributes['consultation_cta_item_id'] = $this->resolveConsultationCta(
                $version,
                $data['consultation_cta_public_id'],
            );
        }

        ArticleVersionContent::query()->updateOrCreate(
            ['content_version_id' => $version->id],
            $attributes + ($partial ? [] : ['estimated_reading_minutes' => $attributes['estimated_reading_minutes'] ?? 0]),
        );
    }

    /** @param array<string, mixed> $data */
    private function writeFaq(ContentVersion $version, array $data, bool $partial): void
    {
        $attributes = [];
        if (array_key_exists('question', $data)) {
            $attributes['question'] = trim((string) $data['question']);
        } elseif (! $partial) {
            $attributes['question'] = $version->title;
        }

        if (array_key_exists('answer_document', $data) && is_array($data['answer_document'])) {
            $prepared = $this->documents->prepareFaq($data['answer_document']);
            $attributes += [
                'answer_document_json' => $prepared['document'],
                'sanitized_answer_html' => $prepared['html'],
                'plain_search_text' => $prepared['text'],
            ];
        }

        if (array_key_exists('display_order', $data)) {
            $attributes['display_order'] = (int) $data['display_order'];
        }

        FaqVersionContent::query()->updateOrCreate(
            ['content_version_id' => $version->id],
            $attributes + ($partial ? [] : ['display_order' => $attributes['display_order'] ?? 0]),
        );
    }

    /** @param array<string, mixed> $data */
    private function writeConsultation(ContentVersion $version, array $data, bool $partial): void
    {
        $fields = [
            'service_name', 'description', 'email', 'phone_display', 'whatsapp_display',
            'office_address', 'operating_hours', 'emergency_available', 'appointment_url',
            'action_label', 'icon_code', 'sort_order', 'is_active', 'verification_date', 'verified_owner',
        ];
        $attributes = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (! $partial) {
            $attributes['service_name'] ??= $version->title;
            $attributes['emergency_available'] ??= false;
            $attributes['sort_order'] ??= 0;
            $attributes['is_active'] ??= true;
        }

        if (array_key_exists('phone_display', $data)) {
            $attributes['phone_normalized'] = $this->contacts->normalize($data['phone_display'], 'phone_display');
        }
        if (array_key_exists('whatsapp_display', $data)) {
            $attributes['whatsapp_normalized'] = $this->contacts->normalize($data['whatsapp_display'], 'whatsapp_display');
        }
        if (! empty($attributes['appointment_url']) && ! $this->validHttpsUrl((string) $attributes['appointment_url'])) {
            throw ValidationException::withMessages(['appointment_url' => ['The appointment URL must be a valid HTTPS URL without sensitive query data.']]);
        }

        ConsultationVersionContent::query()->updateOrCreate(
            ['content_version_id' => $version->id],
            $attributes,
        );
    }

    /**
     * @param array{
     *     public_ids: array<string, string>,
     *     database_ids: array<int, int>,
     *     paths: list<string>
     * } $attachmentMap
     */
    private function copyTypedContent(
        ContentVersion $source,
        ContentVersion $target,
        ContentType $type,
        array $attachmentMap,
    ): void {
        match ($type) {
            ContentType::Article => $this->copyArticleContent($source, $target, $attachmentMap),
            ContentType::Faq => FaqVersionContent::query()->create([
                'content_version_id' => $target->id,
                ...$source->faqContent()->firstOrFail()->only([
                    'question', 'answer_document_json', 'sanitized_answer_html', 'plain_search_text', 'display_order',
                ]),
            ]),
            ContentType::Consultation => ConsultationVersionContent::query()->create([
                'content_version_id' => $target->id,
                ...$source->consultationContent()->firstOrFail()->only([
                    'service_name', 'description', 'email', 'phone_display', 'phone_normalized',
                    'whatsapp_display', 'whatsapp_normalized', 'office_address', 'operating_hours',
                    'emergency_available', 'appointment_url', 'action_label', 'icon_code', 'sort_order',
                    'is_active', 'verification_date', 'verified_owner',
                ]),
            ]),
        };
    }

    /**
     * @param array{
     *     public_ids: array<string, string>,
     *     database_ids: array<int, int>,
     *     paths: list<string>
     * } $attachmentMap
     */
    private function copyArticleContent(ContentVersion $source, ContentVersion $target, array $attachmentMap): void
    {
        $article = $source->articleContent()->firstOrFail();
        $document = $article->document_json === null
            ? null
            : $this->rewriteAttachmentReferences($article->document_json, $attachmentMap['public_ids']);
        $prepared = $document === null ? null : $this->documents->prepareArticle($document);
        $coverAttachmentId = null;

        if ($article->cover_attachment_id !== null) {
            $coverAttachmentId = $attachmentMap['database_ids'][$article->cover_attachment_id] ?? null;
            if ($coverAttachmentId === null) {
                throw ValidationException::withMessages([
                    'attachments' => ['The published cover attachment could not be cloned into the revision.'],
                ]);
            }
        }

        ArticleVersionContent::query()->create([
            'content_version_id' => $target->id,
            'document_json' => $prepared['document'] ?? null,
            'sanitized_html' => $prepared['html'] ?? null,
            'search_text' => $prepared['text'] ?? null,
            'estimated_reading_minutes' => $prepared['reading_minutes'] ?? 0,
            'cover_attachment_id' => $coverAttachmentId,
            'cover_alt_text' => $article->cover_alt_text,
            'consultation_cta_item_id' => $article->consultation_cta_item_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $publicIdMap
     * @return array<string, mixed>
     */
    private function rewriteAttachmentReferences(array $node, array $publicIdMap): array
    {
        if (($node['type'] ?? null) === 'imageReference') {
            $sourcePublicId = $node['attrs']['attachment_public_id'] ?? null;
            if (! is_string($sourcePublicId) || ! isset($publicIdMap[$sourcePublicId])) {
                throw ValidationException::withMessages([
                    'document' => ['A published image reference cannot be resolved for the new revision.'],
                ]);
            }
            $node['attrs']['attachment_public_id'] = $publicIdMap[$sourcePublicId];
        }

        if (is_array($node['content'] ?? null)) {
            $node['content'] = array_map(
                fn (mixed $child): mixed => is_array($child)
                    ? $this->rewriteAttachmentReferences($child, $publicIdMap)
                    : $child,
                $node['content'],
            );
        }

        return $node;
    }

    /** @param array<string, mixed> $document */
    private function assertArticleImageReferencesOwned(ContentVersion $version, array $document): void
    {
        $publicIds = [];
        $walk = function (array $node) use (&$walk, &$publicIds): void {
            if (($node['type'] ?? null) === 'imageReference') {
                $publicIds[] = $node['attrs']['attachment_public_id'];
            }
            foreach ($node['content'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($document);
        $publicIds = array_values(array_unique($publicIds));

        if ($publicIds === []) {
            return;
        }

        $ownedCount = ContentAttachment::query()
            ->where('content_version_id', $version->id)
            ->where('purpose', ContentAttachmentPurpose::InlineImage->value)
            ->whereIn('public_id', $publicIds)
            ->count();
        if ($ownedCount !== count($publicIds)) {
            throw ValidationException::withMessages([
                'document' => ['Every image reference must identify an image attachment owned by this editable version.'],
            ]);
        }
    }

    private function ensurePublishablePayload(ContentVersion $version, bool $publication): void
    {
        $version->loadMissing(['item', 'articleContent', 'faqContent', 'consultationContent']);

        if ($publication && $version->requires_editorial_review) {
            throw ValidationException::withMessages(['requires_editorial_review' => ['Editorial verification must be completed before publication.']]);
        }

        $this->assertVersionAttachmentsAvailable($version);

        match ($version->item->content_type) {
            ContentType::Article => $this->ensureArticlePublishable($version, $publication),
            ContentType::Faq => $version->faqContent?->answer_document_json !== null
                ?: throw ValidationException::withMessages(['answer_document' => ['FAQ answer content is required before this action.']]),
            ContentType::Consultation => $this->ensureConsultationPublishable($version->consultationContent, $publication),
        };
    }

    private function ensureArticlePublishable(ContentVersion $version, bool $publication): bool
    {
        $content = $version->articleContent;
        if ($content?->document_json === null) {
            throw ValidationException::withMessages(['document' => ['Article content is required before this action.']]);
        }

        $this->assertArticleAttachmentsAvailable($version, $content);

        if ($content->consultation_cta_item_id !== null) {
            $consultation = $content->consultationCta()->first();
            if ($consultation === null) {
                throw ValidationException::withMessages([
                    'consultation_cta_public_id' => ['The Consultation CTA is no longer available for publication.'],
                ]);
            }
            $this->resolveConsultationCta($version, $consultation->public_id);
        }

        return true;
    }

    private function assertArticleAttachmentsAvailable(
        ContentVersion $version,
        ArticleVersionContent $content,
    ): void {
        $attachments = $version->attachments()->get();
        if ($content->cover_attachment_id !== null) {
            $cover = $attachments->firstWhere('id', $content->cover_attachment_id);
            if ($cover === null || $cover->purpose !== ContentAttachmentPurpose::Cover) {
                throw ValidationException::withMessages([
                    'cover' => ['The cover must belong to this version and use the cover purpose.'],
                ]);
            }
        }

        if ($content->document_json !== null) {
            $this->assertArticleImageReferencesOwned($version, $content->document_json);
        }
    }

    private function assertVersionAttachmentsAvailable(ContentVersion $version): void
    {
        foreach ($version->attachments()->get() as $attachment) {
            if ($attachment->storage_disk !== 'content'
                || ! Storage::disk('content')->exists($attachment->storage_path)) {
                throw ValidationException::withMessages([
                    'attachments' => ['Every attachment must be available on private storage before submission or publication.'],
                ]);
            }
        }
    }

    private function ensureConsultationPublishable(?ConsultationVersionContent $content, bool $publication): bool
    {
        if ($content === null || blank($content->service_name)) {
            throw ValidationException::withMessages(['service_name' => ['Consultation service name is required.']]);
        }

        if ($publication && ($content->verification_date === null || blank($content->verified_owner))) {
            throw ValidationException::withMessages(['verification_date' => ['Verified owner and verification date are required before publication.']]);
        }

        return true;
    }

    private function resolveCategory(mixed $publicId, ContentSection $section, ContentScope $scope, ?int $universityId): ?ContentCategory
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        $category = ContentCategory::query()
            ->where('public_id', $publicId)
            ->where('section_id', $section->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail();

        $scopeAllowed = $category->scope === ContentScope::Global
            || ($scope === ContentScope::Campus
                && $category->scope === ContentScope::Campus
                && (int) $category->university_id === (int) $universityId);

        if (! $scopeAllowed) {
            throw ValidationException::withMessages(['category_public_id' => ['The category is outside the content scope.']]);
        }

        return $category;
    }

    private function resolveConsultationCta(ContentVersion $version, mixed $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        $item = $version->item()->firstOrFail();
        $consultation = ContentItem::query()
            ->where('public_id', $publicId)
            ->where('content_type', ContentType::Consultation->value)
            ->whereNull('archived_at')
            ->whereNotNull('published_version_id')
            ->whereHas('publishedVersion', fn ($query) => $query
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereHas('consultationContent', fn ($content) => $content->where('is_active', true)))
            ->lockForUpdate()
            ->first();

        $scopeAllowed = $consultation !== null && (
            ($item->scope === ContentScope::Global && $consultation->scope === ContentScope::Global)
            || ($item->scope === ContentScope::Campus && (
                $consultation->scope === ContentScope::Global
                || ($consultation->scope === ContentScope::Campus
                    && (int) $consultation->university_id === (int) $item->university_id)
            ))
        );
        if (! $scopeAllowed) {
            throw ValidationException::withMessages([
                'consultation_cta_public_id' => ['The Consultation CTA must be published and visible within this content scope.'],
            ]);
        }

        return $consultation->id;
    }

    private function validateTypePlacement(ContentType $type, ContentSection $section, ?ContentCategory $category): void
    {
        $valid = match ($type) {
            ContentType::Article => in_array($section->code, ['education', 'policy'], true) && $category !== null,
            ContentType::Faq => $section->code === 'faq',
            ContentType::Consultation => $section->code === 'consultation' && $category === null,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['section_code' => ['The section and category do not match the content type.']]);
        }
    }

    private function authorizeCreation(User $actor, ContentScope $scope, ?int $universityId): void
    {
        $allowed = $scope === ContentScope::Global
            ? $this->policy->createGlobal($actor)
            : $universityId !== null && $this->policy->createCampus($actor, $universityId);

        if (! $allowed) {
            throw $this->forbidden();
        }
    }

    private function availableSlug(ContentType $type, ContentScope $scope, ?int $universityId, string $value): string
    {
        $base = Str::slug($value);
        $base = $base !== '' ? mb_substr($base, 0, 180) : $type->value.'-content';
        $scopeKey = $scope === ContentScope::Global ? 'global' : 'campus:'.$universityId;
        $slug = $base;
        $suffix = 2;

        while (ContentItem::withTrashed()->where([
            'content_type' => $type->value,
            'scope_key' => $scopeKey,
            'slug' => $slug,
        ])->exists()) {
            $slug = mb_substr($base, 0, 180 - strlen((string) $suffix) - 1).'-'.$suffix++;
        }

        return $slug;
    }

    private function plainExcerpt(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $excerpt = trim((string) $value);

        if ($excerpt !== strip_tags($excerpt)) {
            throw ValidationException::withMessages(['excerpt' => ['Article excerpts must be plain text.']]);
        }

        return $excerpt;
    }

    private function validHttpsUrl(string $url): bool
    {
        if (! $this->documents->safeUrl($url, false) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $query = mb_strtolower((string) parse_url($url, PHP_URL_QUERY));

        return preg_match('/(?:report|case|registration|tracking|identity|incident|nim|email|phone)/', $query) !== 1;
    }

    private function lockedActor(User $actor): User
    {
        return User::query()->with('role.permissions')->whereKey($actor->id)->lockForUpdate()->firstOrFail();
    }

    private function loadManagement(ContentItem $item): ContentItem
    {
        return $item->load([
            'section', 'category', 'university',
            'currentDraftVersion.articleContent',
            'currentDraftVersion.faqContent',
            'currentDraftVersion.consultationContent',
            'publishedVersion',
        ]);
    }

    private function record(
        AuditAction $action,
        User $actor,
        ContentItem $item,
        ContentVersion $version,
        ?string $fromStatus = null,
    ): void {
        $item->loadMissing(['section', 'category', 'university']);
        $this->auditLogs->record(
            action: $action,
            category: AuditCategory::Content,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $item,
            metadata: [
                'content_public_id' => $item->public_id,
                'version_number' => $version->version_number,
                'content_type' => $item->content_type->value,
                'section_code' => $item->section?->code,
                'category_code' => $item->category?->code,
                'scope' => $item->scope->value,
                'university_code' => $item->university?->code,
                'from_status' => $fromStatus,
                'to_status' => $version->lifecycle_status->value,
            ],
            afterChanges: ['lifecycle_status' => $version->lifecycle_status->value],
        );
    }

    private function isIntegrityViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true);
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action',
            'errors' => null,
        ], 403));
    }

    private function ensureNotArchived(ContentItem $item): void
    {
        if ($item->archived_at !== null) {
            throw $this->conflict(
                'Archived content is read-only and cannot be changed.',
                ApiErrorCode::ContentArchived,
            );
        }
    }

    private function conflict(string $message, ?string $errorCode = null): HttpResponseException
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => null,
        ];
        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        return new HttpResponseException(response()->json($payload, 409)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]));
    }

    private function assertLockVersion(ContentItem $item, int $expectedLockVersion, string $errorCode): void
    {
        if ((int) $item->lock_version !== $expectedLockVersion) {
            throw $this->conflict(
                'Content changed after it was loaded. Reload the authoritative state before continuing.',
                $errorCode,
            );
        }
    }

    private function invalidLifecycle(
        ?ContentLifecycleStatus $required,
        ?ContentLifecycleStatus $actual,
    ): HttpResponseException {
        $expected = $required?->value ?? 'an editable state';
        $current = $actual?->value ?? 'unknown';

        return $this->conflict(
            "Content must be in {$expected} before this action; current state is {$current}.",
            ApiErrorCode::ContentInvalidLifecycleTransition,
        );
    }
}
