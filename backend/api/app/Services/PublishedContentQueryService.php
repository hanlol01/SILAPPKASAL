<?php

namespace App\Services;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentSection;
use App\Models\FeaturedContent;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

class PublishedContentQueryService
{
    public function __construct(private readonly ContentItemPolicy $policy) {}

    /** @return Collection<int, ContentSection> */
    public function sections(User $actor): Collection
    {
        $this->authorizeReader($actor);

        return ContentSection::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get();
    }

    /** @return Collection<int, ContentCategory> */
    public function categories(User $actor, ?string $sectionCode = null): Collection
    {
        $this->authorizeReader($actor);

        return ContentCategory::query()
            ->with('section')
            ->where('is_active', true)
            ->whereHas('section', fn (Builder $query) => $query->where('is_active', true))
            ->when($sectionCode, fn (Builder $query, string $code) => $query->whereHas(
                'section', fn (Builder $section) => $section->where('code', $code)
            ))
            ->where(fn (Builder $query) => $this->scopeQuery($query, $actor))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function articles(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations());

        $this->applyCategoryAndSearch($query, $filters);

        if (! empty($filters['section'])) {
            $query->whereHas('section', fn (Builder $section) => $section->where('code', $filters['section']));
        }

        return $query->orderByDesc('content_versions.published_at')
            ->orderByDesc('content_items.id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function article(User $actor, string $identifier): ContentItem
    {
        return $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations())
            ->where(fn (Builder $query) => $query
                ->where('content_items.public_id', $identifier)
                ->orWhere('content_items.slug', $identifier))
            ->firstOrFail();
    }

    /** @return Collection<int, ContentItem> */
    public function relatedArticles(User $actor, ContentItem $article, int $limit = 4): Collection
    {
        if ($article->category_id === null) {
            return new Collection;
        }

        return $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations())
            ->where('content_items.category_id', $article->category_id)
            ->whereKeyNot($article->id)
            ->orderByDesc('content_versions.published_at')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function faqs(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->publishedItems($actor, ContentType::Faq)
            ->with(['section', 'category', 'publishedVersion.faqContent']);
        $this->applyCategoryAndSearch($query, $filters, true);

        return $query
            ->orderBy('faq_version_contents.display_order')
            ->orderBy('content_versions.title')
            ->paginate((int) ($filters['per_page'] ?? 50));
    }

    /** @return Collection<int, ContentItem> */
    public function consultation(User $actor): Collection
    {
        return $this->publishedItems($actor, ContentType::Consultation)
            ->with(['section', 'publishedVersion.consultationContent'])
            ->whereHas('publishedVersion.consultationContent', fn (Builder $query) => $query->where('is_active', true))
            ->orderByRaw("CASE WHEN content_items.scope = 'campus' THEN 0 ELSE 1 END")
            ->orderBy('consultation_version_contents.sort_order')
            ->orderBy('content_versions.title')
            ->get();
    }

    /** @return Collection<int, ContentItem> */
    public function featured(User $actor): Collection
    {
        $this->authorizeReader($actor);
        $now = now();
        $scopeKeys = ['global'];
        if ($actor->university_id !== null) {
            array_unshift($scopeKeys, 'campus:'.$actor->university_id);
        }

        $placements = FeaturedContent::query()
            ->with(['item' => fn ($query) => $query->with($this->articleRelations())])
            ->whereIn('scope_key', $scopeKeys)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $now))
            ->whereHas('item', fn (Builder $query) => $this->constrainPublishedItem($query, $actor, ContentType::Article))
            ->orderByRaw("CASE WHEN scope = 'campus' THEN 0 ELSE 1 END")
            ->orderBy('rank')
            ->limit(5)
            ->get()
            ->pluck('item')
            ->filter()
            ->unique('id')
            ->values();

        if ($placements->count() >= 5) {
            return $placements;
        }

        $fallback = $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations())
            ->whereNotIn('content_items.id', $placements->pluck('id'))
            ->orderByDesc('content_versions.published_at')
            ->limit(5 - $placements->count())
            ->get();

        return $placements->concat($fallback)->values();
    }

    /** @return Builder<ContentItem> */
    private function publishedItems(User $actor, ContentType $type): Builder
    {
        $this->authorizeReader($actor);

        return $this->constrainPublishedItem(ContentItem::query(), $actor, $type)
            ->join('content_versions', function ($join): void {
                $join->on('content_versions.id', '=', 'content_items.published_version_id')
                    ->on('content_versions.content_item_id', '=', 'content_items.id');
            })
            ->leftJoin('faq_version_contents', 'faq_version_contents.content_version_id', '=', 'content_versions.id')
            ->leftJoin('consultation_version_contents', 'consultation_version_contents.content_version_id', '=', 'content_versions.id')
            ->select('content_items.*');
    }

    /** @param Builder<ContentItem> $query @return Builder<ContentItem> */
    private function constrainPublishedItem(Builder $query, User $actor, ContentType $type): Builder
    {
        return $query
            ->where('content_items.content_type', $type->value)
            ->whereNull('content_items.archived_at')
            ->whereNotNull('content_items.published_version_id')
            ->where(fn (Builder $scope) => $this->scopeQuery($scope, $actor, 'content_items.'))
            ->whereHas('publishedVersion', fn (Builder $version) => $version
                ->whereColumn('content_versions.content_item_id', 'content_items.id')
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()));
    }

    private function scopeQuery(Builder $query, User $actor, string $prefix = ''): void
    {
        $query->where($prefix.'scope', ContentScope::Global->value);
        if ($actor->university_id !== null) {
            $query->orWhere(fn (Builder $campus) => $campus
                ->where($prefix.'scope', ContentScope::Campus->value)
                ->where($prefix.'university_id', $actor->university_id));
        }
    }

    /** @param Builder<ContentItem> $query @param array<string, mixed> $filters */
    private function applyCategoryAndSearch(Builder $query, array $filters, bool $faq = false): void
    {
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category
                ->where('public_id', $filters['category']));
        }

        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->where(function (Builder $search) use ($needle, $faq): void {
                $search->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '\\'", [$needle])
                    ->orWhereRaw("LOWER(COALESCE(content_versions.excerpt, '')) LIKE ? ESCAPE '\\'", [$needle]);
                if ($faq) {
                    $search->orWhereRaw("LOWER(COALESCE(faq_version_contents.plain_search_text, '')) LIKE ? ESCAPE '\\'", [$needle]);
                }
            });
        }
    }

    /** @return list<string> */
    private function articleRelations(): array
    {
        return [
            'section', 'category',
            'publishedVersion.articleContent.coverAttachment',
            'publishedVersion.articleContent.consultationCta.publishedVersion',
            'publishedVersion.attachments',
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function authorizeReader(User $actor): void
    {
        $actor->loadMissing('role.permissions');
        if (! $this->policy->viewPublished($actor)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'You do not have permission to read published content',
                'errors' => null,
            ], 403));
        }
    }
}
