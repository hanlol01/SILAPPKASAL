<?php

namespace App\Services;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ContentAttachment;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

class ContentManagementQueryService
{
    public function __construct(private readonly ContentItemPolicy $policy) {}

    /** @param array<string, mixed> $filters */
    public function items(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->manageableItems($actor)->with($this->summaryRelations());

        if (! empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('public_id', $filters['category']));
        }
        if (! empty($filters['article_category'])) {
            $categoryName = mb_strtolower(trim((string) $filters['article_category']));
            $query->where('content_type', ContentType::Article->value)
                ->where(function (Builder $projected) use ($categoryName): void {
                    $projected->whereHas('currentDraftVersion', fn (Builder $version) => $this->versionCategory(
                        $version,
                        $categoryName
                    ))->orWhere(function (Builder $fallback) use ($categoryName): void {
                        $fallback->whereNull('current_draft_version_id')
                            ->whereHas('latestVersion', fn (Builder $version) => $this->versionCategory(
                                $version,
                                $categoryName
                            ));
                    });
                });
        }
        if (! empty($filters['lifecycle_status'])) {
            $status = (string) $filters['lifecycle_status'];
            $query->where(function (Builder $statusQuery) use ($status): void {
                if ($status === ContentLifecycleStatus::Archived->value) {
                    $statusQuery->whereNotNull('archived_at');

                    return;
                }

                $statusQuery->whereNull('archived_at')->where(function (Builder $active) use ($status): void {
                    $active->whereHas('currentDraftVersion', fn (Builder $version) => $version->where('lifecycle_status', $status))
                        ->orWhere(function (Builder $fallback) use ($status): void {
                            $fallback->whereNull('current_draft_version_id')
                                ->whereHas('latestVersion', fn (Builder $version) => $version->where('lifecycle_status', $status));
                        });
                });
            });
        }
        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->whereHas('versions', fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '!'", [$needle]));
        }

        return $query->orderByDesc('updated_at')
            ->orderBy('public_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /** @return array<string, int> */
    public function summary(User $actor): array
    {
        $counts = array_fill_keys(ContentLifecycleStatus::values(), 0);
        $this->manageableItems($actor)->with(['currentDraftVersion', 'latestVersion'])->get()
            ->each(function (ContentItem $item) use (&$counts): void {
                $status = $item->archived_at !== null
                    ? ContentLifecycleStatus::Archived->value
                    : ($item->currentDraftVersion?->lifecycle_status?->value
                        ?? $item->latestVersion?->lifecycle_status?->value);
                if ($status !== null) {
                    $counts[$status]++;
                }
            });

        return $counts;
    }

    public function item(User $actor, string $publicId): ContentItem
    {
        return $this->itemModel($actor, $publicId)->load($this->detailRelations());
    }

    public function itemModel(User $actor, string $publicId): ContentItem
    {
        $item = $this->manageableItems($actor)
            ->where('public_id', $publicId)
            ->firstOrFail();

        if (! $this->policy->viewManagement($actor, $item)) {
            throw $this->forbidden();
        }

        return $item;
    }

    public function version(User $actor, string $publicId): ContentVersion
    {
        $this->authorizeManager($actor);

        return ContentVersion::query()
            ->where('public_id', $publicId)
            ->whereHas('item', fn (Builder $item) => $this->constrainManageableItems($item, $actor))
            ->firstOrFail();
    }

    public function attachment(User $actor, string $publicId): ContentAttachment
    {
        $this->authorizeManager($actor);

        return ContentAttachment::query()
            ->where('public_id', $publicId)
            ->whereHas('version.item', fn (Builder $item) => $this->constrainManageableItems($item, $actor))
            ->firstOrFail();
    }

    /** @return Builder<ContentItem> */
    private function manageableItems(User $actor): Builder
    {
        $this->authorizeManager($actor);

        return $this->constrainManageableItems(ContentItem::query(), $actor);
    }

    /** @param Builder<ContentItem> $query @return Builder<ContentItem> */
    private function constrainManageableItems(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('super_admin')) {
            return $query->where('scope', ContentScope::Global->value);
        }

        return $query
            ->where('scope', ContentScope::Campus->value)
            ->where('university_id', $actor->university_id);
    }

    private function authorizeManager(User $actor): void
    {
        $actor->loadMissing('role.permissions');
        $campusManager = $actor->hasRole('admin')
            && $actor->university_id !== null
            && $actor->hasPermission('content.read.management.own_campus');
        $globalManager = $actor->hasRole('super_admin')
            && $actor->hasPermission('content.read.management.all')
            && $actor->hasPermission('content.publish.global');
        if (! $actor->is_active || (! $campusManager && ! $globalManager)) {
            throw $this->forbidden();
        }
    }

    /** @return list<string> */
    private function summaryRelations(): array
    {
        return [
            'section', 'category',
            'currentDraftVersion.category', 'publishedVersion.category', 'latestVersion.category',
        ];
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'section', 'category', 'university',
            'currentDraftVersion.category',
            'currentDraftVersion.articleContent.coverAttachment',
            'currentDraftVersion.faqContent', 'currentDraftVersion.consultationContent',
            'currentDraftVersion.attachments', 'currentDraftVersion.reviewDecisions',
            'publishedVersion.category',
            'publishedVersion.articleContent.coverAttachment',
            'publishedVersion.faqContent', 'publishedVersion.consultationContent',
            'publishedVersion.attachments', 'publishedVersion.reviewDecisions',
            'latestVersion.category',
            'latestVersion.articleContent.coverAttachment',
            'latestVersion.faqContent', 'latestVersion.consultationContent',
            'latestVersion.attachments', 'latestVersion.reviewDecisions',
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function versionCategory(Builder $version, string $categoryName): void
    {
        $version->whereRaw('LOWER(content_versions.category_name) = ?', [$categoryName])
            ->orWhere(function (Builder $legacy) use ($categoryName): void {
                $legacy->whereNull('content_versions.category_name')
                    ->whereHas('category', fn (Builder $category) => $category
                        ->whereRaw('LOWER(content_categories.name) = ?', [$categoryName]));
            });
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to manage this content scope',
            'errors' => null,
        ], 403));
    }
}
