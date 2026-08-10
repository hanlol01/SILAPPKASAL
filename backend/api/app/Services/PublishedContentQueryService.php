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
use Illuminate\Support\Facades\DB;

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
            ->orderBy('public_id')
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
            ->orderBy('content_items.public_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /** @return Collection<int, string> */
    public function articleCategories(User $actor, string $sectionCode): Collection
    {
        return $this->publishedItems($actor, ContentType::Article)
            ->whereHas('section', fn (Builder $section) => $section->where('code', $sectionCode))
            ->whereRaw('COALESCE(content_versions.category_name, published_categories.name) IS NOT NULL')
            ->select(DB::raw('COALESCE(content_versions.category_name, published_categories.name) as category_label'))
            ->distinct()
            ->orderBy('category_label')
            ->pluck('category_label')
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->values();
    }

    public function article(User $actor, string $publicId): ContentItem
    {
        return $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations())
            ->where('content_items.public_id', $publicId)
            ->firstOrFail();
    }

    public function articleBySlug(User $actor, string $section, string $slug): ContentItem
    {
        return $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations())
            ->where('content_items.slug', $slug)
            ->whereHas('section', fn (Builder $query) => $query->where('code', $section))
            ->orderByRaw("CASE WHEN content_items.scope = 'campus' THEN 0 ELSE 1 END")
            ->orderBy('content_items.public_id')
            ->firstOrFail();
    }

    /** @return Collection<int, ContentItem> */
    public function relatedArticles(User $actor, ContentItem $article, int $limit = 4): Collection
    {
        $publishedVersion = $article->publishedVersion;
        $categoryName = trim((string) ($publishedVersion?->category_name ?? $publishedVersion?->category?->name));
        if ($categoryName === '' && $publishedVersion?->category_id === null) {
            return new Collection;
        }

        $query = $this->publishedItems($actor, ContentType::Article)
            ->with($this->articleRelations())
            ->whereKeyNot($article->id);

        if ($categoryName !== '') {
            $query->whereRaw(
                'LOWER(COALESCE(content_versions.category_name, published_categories.name)) = ?',
                [mb_strtolower($categoryName)]
            );
        } else {
            $query->where('content_versions.category_id', $publishedVersion?->category_id);
        }

        return $query
            ->orderByDesc('content_versions.published_at')
            ->orderBy('content_items.public_id')
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
            ->orderBy('content_items.public_id')
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
            ->orderBy('content_items.public_id')
            ->get();
    }

    /** @return Collection<int, ContentItem> */
    /** @param array<string, mixed> $filters @return Collection<int, ContentItem> */
    public function featured(User $actor, array $filters = []): Collection
    {
        $this->authorizeReader($actor);
        $now = now();
        $scopeKeys = $this->canReadAllCampuses($actor) ? null : ['global'];
        if ($scopeKeys !== null && $actor->university_id !== null) {
            array_unshift($scopeKeys, 'campus:'.$actor->university_id);
        }

        $placements = FeaturedContent::query()
            ->with(['item' => fn ($query) => $query->with($this->articleRelations())])
            ->when($scopeKeys !== null, fn (Builder $query) => $query->whereIn('scope_key', $scopeKeys))
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $now))
            ->whereHas('item', fn (Builder $query) => $this->constrainPublishedItem($query, $actor, ContentType::Article))
            ->whereHas('item.section', fn (Builder $section) => $section->where('code', 'education'))
            ->when((bool) ($filters['require_cover'] ?? false), fn (Builder $query) => $query->whereHas(
                'item.publishedVersion.articleContent.coverAttachment'
            ))
            ->orderByRaw("CASE WHEN scope = 'campus' THEN 0 ELSE 1 END")
            ->orderBy('rank')
            ->orderBy('public_id')
            ->limit((int) ($filters['limit'] ?? 5))
            ->get()
            ->pluck('item')
            ->filter()
            ->unique('id')
            ->values();

        return $placements;
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
            ->leftJoin('content_categories as published_categories', 'published_categories.id', '=', 'content_versions.category_id')
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
        if ($this->canReadAllCampuses($actor)) {
            $query->whereIn($prefix.'scope', [
                ContentScope::Global->value,
                ContentScope::Campus->value,
            ]);

            return;
        }

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
            if ($faq) {
                $query->whereHas('category', fn (Builder $category) => $category
                    ->where('public_id', $filters['category']));
            } else {
                $query->whereHas('publishedVersion.category', fn (Builder $category) => $category
                    ->where('public_id', $filters['category']));
            }
        }

        if (! empty($filters['article_category'])) {
            $categoryName = mb_strtolower(trim((string) $filters['article_category']));
            $query->whereRaw(
                'LOWER(COALESCE(content_versions.category_name, published_categories.name)) = ?',
                [$categoryName]
            );
        }

        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->where(function (Builder $search) use ($needle, $faq): void {
                $search->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("LOWER(COALESCE(content_versions.excerpt, '')) LIKE ? ESCAPE '!'", [$needle]);
                if ($faq) {
                    $search->orWhereRaw("LOWER(COALESCE(faq_version_contents.plain_search_text, '')) LIKE ? ESCAPE '!'", [$needle]);
                }
            });
        }
    }

    /** @return list<string> */
    private function articleRelations(): array
    {
        return [
            'section', 'publishedVersion.category',
            'publishedVersion.articleContent.coverAttachment',
            'publishedVersion.attachments',
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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

    private function canReadAllCampuses(User $actor): bool
    {
        return $actor->hasRole('super_admin')
            && $actor->hasPermission('content.read.management.all');
    }
}
