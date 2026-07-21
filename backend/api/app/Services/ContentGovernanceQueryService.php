<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ContentLifecycleStatus;
use App\Models\AuditLog;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\University;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

class ContentGovernanceQueryService
{
    public function __construct(private readonly ContentItemPolicy $policy) {}

    /** @param array<string, mixed> $filters */
    public function reviewQueue(User $actor, array $filters): LengthAwarePaginator
    {
        $this->authorizeGovernanceReader($actor);
        $statuses = ! empty($filters['lifecycle_status'])
            ? [(string) $filters['lifecycle_status']]
            : [
                ContentLifecycleStatus::Submitted->value,
                ContentLifecycleStatus::InReview->value,
                ContentLifecycleStatus::Approved->value,
            ];

        $query = ContentItem::query()
            ->whereNull('archived_at')
            ->whereNotNull('current_draft_version_id')
            ->whereHas('currentDraftVersion', function (Builder $version) use ($statuses, $filters): void {
                $version->whereIn('lifecycle_status', $statuses);
                if (! empty($filters['submitted_from'])) {
                    $version->whereDate('submitted_at', '>=', $filters['submitted_from']);
                }
                if (! empty($filters['submitted_to'])) {
                    $version->whereDate('submitted_at', '<=', $filters['submitted_to']);
                }
            })
            ->with([
                'section', 'category', 'university', 'creator.role',
                'currentDraftVersion.author.role', 'publishedVersion',
            ]);

        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }
        if (! empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        if (! empty($filters['section'])) {
            $query->whereHas('section', fn (Builder $section) => $section->where('code', $filters['section']));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('public_id', $filters['category']));
        }
        if (! empty($filters['university_code'])) {
            $query->whereHas('university', fn (Builder $university) => $university->where('code', $filters['university_code']));
        }
        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->whereHas('currentDraftVersion', fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '\\'", [$needle]));
        }

        return $query
            ->orderByRaw('(SELECT submitted_at FROM content_versions WHERE content_versions.id = content_items.current_draft_version_id) ASC')
            ->orderBy('public_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /** @param array<string, mixed> $filters */
    public function publishedItems(User $actor, array $filters): LengthAwarePaginator
    {
        $this->authorizeGovernanceReader($actor);

        $query = ContentItem::query()
            ->whereNull('archived_at')
            ->whereNull('current_draft_version_id')
            ->whereNotNull('published_version_id')
            ->whereHas('publishedVersion', fn (Builder $version) => $version
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value))
            ->with([
                'section', 'category', 'university', 'creator.role',
                'publishedVersion.author.role', 'latestVersion',
            ]);

        $this->applyContentFilters($query, $filters, 'publishedVersion');

        return $query
            ->orderByRaw('(SELECT published_at FROM content_versions WHERE content_versions.id = content_items.published_version_id) DESC')
            ->orderBy('public_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function item(User $actor, string $publicId): ContentItem
    {
        $this->authorizeGovernanceReader($actor);

        $item = ContentItem::query()
            ->where('public_id', $publicId)
            ->with($this->detailRelations())
            ->firstOrFail();
        if (! $this->policy->viewManagement($actor, $item)) {
            abort(404);
        }

        $item->setRelation('governanceHistory', $this->history($item));

        return $item;
    }

    public function reviewVersion(User $actor, string $publicId): ContentVersion
    {
        $this->authorizeReviewer($actor);

        $version = ContentVersion::query()
            ->where('public_id', $publicId)
            ->with('item')
            ->firstOrFail();
        if (! $this->policy->viewManagement($actor, $version->item)) {
            abort(404);
        }

        return $version;
    }

    public function reviewItem(User $actor, string $publicId): ContentItem
    {
        $this->authorizeReviewer($actor);

        $item = ContentItem::query()->where('public_id', $publicId)->firstOrFail();
        if (! $this->policy->viewManagement($actor, $item)) {
            abort(404);
        }

        return $item;
    }

    /** @return Collection<int, University> */
    public function campuses(User $actor): Collection
    {
        $this->authorizeGovernanceReader($actor);

        return University::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
    }

    /** @return Collection<int, ContentCategory> */
    public function categories(User $actor, ?string $sectionCode = null): Collection
    {
        $this->authorizeGovernanceReader($actor);

        return ContentCategory::query()
            ->with(['section', 'university'])
            ->when($sectionCode, fn (Builder $query, string $code) => $query
                ->whereHas('section', fn (Builder $section) => $section->where('code', $code)))
            ->orderBy('display_order')
            ->orderBy('name')
            ->orderBy('public_id')
            ->get();
    }

    /** @param Builder<ContentItem> $query @param array<string, mixed> $filters */
    private function applyContentFilters(Builder $query, array $filters, string $versionRelation): void
    {
        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }
        if (! empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        if (! empty($filters['section'])) {
            $query->whereHas('section', fn (Builder $section) => $section->where('code', $filters['section']));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('public_id', $filters['category']));
        }
        if (! empty($filters['university_code'])) {
            $query->whereHas('university', fn (Builder $university) => $university->where('code', $filters['university_code']));
        }
        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->whereHas($versionRelation, fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '\\'", [$needle]));
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function history(ContentItem $item): Collection
    {
        $actionStates = [
            AuditAction::ContentSubmitted->value => 'submitted',
            AuditAction::ContentReviewStarted->value => 'review_started',
            AuditAction::ContentRevisionRequested->value => 'revision_requested',
            AuditAction::ContentRejected->value => 'rejected',
            AuditAction::ContentApproved->value => 'approved',
            AuditAction::ContentPublished->value => 'published',
            AuditAction::ContentDirectGlobalPublished->value => 'published',
            AuditAction::ContentArchived->value => 'archived',
        ];
        $decisionStates = [
            'review_started' => 'review_started',
            'revision_requested' => 'revision_requested',
            'rejected' => 'rejected',
            'approved' => 'approved',
            'direct_global_published' => 'published',
            'archived' => 'archived',
        ];
        $decisions = $item->versions
            ->flatMap(fn (ContentVersion $version) => $version->reviewDecisions->map(fn ($decision) => [
                'version_number' => $version->version_number,
                'state' => $decisionStates[$decision->decision_code?->value] ?? $decision->decision_code?->value,
                'note' => $decision->narrative_reason,
            ]));

        return AuditLog::query()
            ->where('subject_type', $item->getMorphClass())
            ->where('subject_id', $item->getKey())
            ->whereIn('action', array_keys($actionStates))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (AuditLog $audit) use ($actionStates, $decisions): array {
                $state = $actionStates[$audit->action];
                $versionNumber = (int) data_get($audit->metadata, 'version_number', 0);
                $note = $decisions->first(fn (array $decision) => $decision['version_number'] === $versionNumber
                    && $decision['state'] === $state)['note'] ?? null;

                return [
                    'public_id' => $audit->public_id,
                    'state' => $state,
                    'actor' => [
                        'name' => $audit->actor_display_name_safe,
                        'role' => $audit->actor_role_code,
                    ],
                    'timestamp' => $audit->created_at?->toJSON(),
                    'note' => $note,
                    'version_number' => $versionNumber ?: null,
                ];
            });
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'section', 'category', 'university', 'creator.role',
            'currentDraftVersion.author.role',
            'currentDraftVersion.articleContent.consultationCta.publishedVersion.consultationContent',
            'currentDraftVersion.faqContent', 'currentDraftVersion.consultationContent',
            'currentDraftVersion.attachments', 'currentDraftVersion.reviewDecisions.reviewer.role',
            'publishedVersion.author.role',
            'publishedVersion.articleContent.consultationCta.publishedVersion.consultationContent',
            'publishedVersion.faqContent', 'publishedVersion.consultationContent',
            'publishedVersion.attachments',
            'latestVersion.author.role', 'latestVersion.articleContent', 'latestVersion.faqContent',
            'latestVersion.consultationContent', 'latestVersion.attachments',
            'versions.reviewDecisions.reviewer.role',
        ];
    }

    private function authorizeGovernanceReader(User $actor): void
    {
        $actor->loadMissing('role.permissions');
        if (! $actor->is_active || ! $actor->hasRole('super_admin')
            || ! $actor->hasPermission('content.read.management.all')) {
            throw $this->forbidden();
        }
    }

    private function authorizeReviewer(User $actor): void
    {
        $this->authorizeGovernanceReader($actor);
        if (! $actor->hasPermission('content.review')) {
            throw $this->forbidden();
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to access content governance',
            'errors' => null,
        ], 403));
    }
}
