<?php

namespace App\Services;

use App\Enums\ContentLifecycleStatus;
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
    public function __construct(
        private readonly ContentItemPolicy $policy,
        private readonly ContentEditorialTimelineService $timeline,
    ) {}

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
                'currentDraftVersion.author.role', 'currentDraftVersion.submitter.role',
                'currentDraftVersion.publisher.role',
                'currentDraftVersion.latestReviewAttributionDecision.reviewer.role',
                'currentDraftVersion.latestApprovalDecision.reviewer.role',
                'currentDraftVersion.category',
                'publishedVersion.category',
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
            $query->whereHas('currentDraftVersion.category', fn (Builder $category) => $category
                ->where('public_id', $filters['category']));
        }
        if (! empty($filters['university_code'])) {
            $query->whereHas('university', fn (Builder $university) => $university->where('code', $filters['university_code']));
        }
        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->whereHas('currentDraftVersion', fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '!'", [$needle]));
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
            ->whereNotNull('published_version_id')
            ->whereHas('publishedVersion', fn (Builder $version) => $version
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value))
            ->with([
                'section', 'category', 'university', 'creator.role',
                'publishedVersion.author.role', 'publishedVersion.submitter.role',
                'publishedVersion.publisher.role',
                'publishedVersion.latestReviewAttributionDecision.reviewer.role',
                'publishedVersion.latestApprovalDecision.reviewer.role',
                'publishedVersion.category',
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

        $history = $this->timeline->forGovernance($item);
        $item->setRelation('governanceHistory', $history['events']);
        $item->setRelation('governanceHistoryTruncated', $history['truncated']);

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
            $query->whereHas($versionRelation.'.category', fn (Builder $category) => $category
                ->where('public_id', $filters['category']));
        }
        if (! empty($filters['university_code'])) {
            $query->whereHas('university', fn (Builder $university) => $university->where('code', $filters['university_code']));
        }
        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->whereHas($versionRelation, fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '!'", [$needle]));
        }
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'section', 'category', 'university', 'creator.role',
            'currentDraftVersion.author.role', 'currentDraftVersion.submitter.role',
            'currentDraftVersion.publisher.role', 'currentDraftVersion.category',
            'currentDraftVersion.articleContent',
            'currentDraftVersion.faqContent', 'currentDraftVersion.consultationContent',
            'currentDraftVersion.attachments', 'currentDraftVersion.latestFeedbackDecision',
            'currentDraftVersion.latestReviewAttributionDecision.reviewer.role',
            'currentDraftVersion.latestApprovalDecision.reviewer.role',
            'publishedVersion.author.role', 'publishedVersion.submitter.role',
            'publishedVersion.publisher.role', 'publishedVersion.category',
            'publishedVersion.articleContent',
            'publishedVersion.faqContent', 'publishedVersion.consultationContent',
            'publishedVersion.attachments', 'publishedVersion.latestFeedbackDecision',
            'publishedVersion.latestReviewAttributionDecision.reviewer.role',
            'publishedVersion.latestApprovalDecision.reviewer.role',
            'latestVersion.author.role', 'latestVersion.submitter.role', 'latestVersion.publisher.role',
            'latestVersion.category', 'latestVersion.articleContent', 'latestVersion.faqContent',
            'latestVersion.consultationContent', 'latestVersion.attachments',
            'latestVersion.latestFeedbackDecision',
            'latestVersion.latestReviewAttributionDecision.reviewer.role',
            'latestVersion.latestApprovalDecision.reviewer.role',
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
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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
