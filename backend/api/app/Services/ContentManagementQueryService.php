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
use Illuminate\Support\Collection;

class ContentManagementQueryService
{
    public function __construct(private readonly ContentItemPolicy $policy) {}

    /** @param array<string, mixed> $filters */
    public function items(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->campusItems($actor)->with($this->summaryRelations());

        if (! empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('public_id', $filters['category']));
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
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '\\'", [$needle]));
        }

        return $query->orderByDesc('updated_at')
            ->orderBy('public_id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /** @return array<string, int> */
    public function summary(User $actor): array
    {
        $counts = array_fill_keys(ContentLifecycleStatus::values(), 0);
        $this->campusItems($actor)->with(['currentDraftVersion', 'latestVersion'])->get()
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
        $item = $this->campusItems($actor)
            ->where('public_id', $publicId)
            ->firstOrFail();

        if (! $this->policy->viewManagement($actor, $item)) {
            throw $this->forbidden();
        }

        return $item;
    }

    public function version(User $actor, string $publicId): ContentVersion
    {
        $this->authorizeCampusManager($actor);

        return ContentVersion::query()
            ->where('public_id', $publicId)
            ->whereHas('item', fn (Builder $item) => $item
                ->where('scope', ContentScope::Campus->value)
                ->where('university_id', $actor->university_id))
            ->firstOrFail();
    }

    public function attachment(User $actor, string $publicId): ContentAttachment
    {
        $this->authorizeCampusManager($actor);

        return ContentAttachment::query()
            ->where('public_id', $publicId)
            ->whereHas('version.item', fn (Builder $item) => $item
                ->where('scope', ContentScope::Campus->value)
                ->where('university_id', $actor->university_id))
            ->firstOrFail();
    }

    /** @return Collection<int, ContentItem> */
    public function eligibleConsultations(User $actor): Collection
    {
        $this->authorizeCampusManager($actor);

        return ContentItem::query()
            ->where('content_type', ContentType::Consultation->value)
            ->whereNull('archived_at')
            ->whereNotNull('published_version_id')
            ->where(function (Builder $scope) use ($actor): void {
                $scope->where('scope', ContentScope::Global->value)
                    ->orWhere(fn (Builder $campus) => $campus
                        ->where('scope', ContentScope::Campus->value)
                        ->where('university_id', $actor->university_id));
            })
            ->whereHas('publishedVersion', fn (Builder $version) => $version
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereHas('consultationContent', fn (Builder $content) => $content->where('is_active', true)))
            ->with('publishedVersion.consultationContent')
            ->orderByRaw("CASE WHEN scope = 'campus' THEN 0 ELSE 1 END")
            ->orderBy('public_id')
            ->get();
    }

    /** @return Builder<ContentItem> */
    private function campusItems(User $actor): Builder
    {
        $this->authorizeCampusManager($actor);

        return ContentItem::query()
            ->where('scope', ContentScope::Campus->value)
            ->where('university_id', $actor->university_id);
    }

    private function authorizeCampusManager(User $actor): void
    {
        $actor->loadMissing('role.permissions');
        if (! $actor->is_active || ! $actor->hasRole('admin')
            || $actor->university_id === null
            || ! $actor->hasPermission('content.read.management.own_campus')) {
            throw $this->forbidden();
        }
    }

    /** @return list<string> */
    private function summaryRelations(): array
    {
        return ['section', 'category', 'currentDraftVersion', 'publishedVersion', 'latestVersion'];
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'section', 'category', 'university',
            'currentDraftVersion.articleContent.consultationCta.publishedVersion.consultationContent',
            'currentDraftVersion.faqContent', 'currentDraftVersion.consultationContent',
            'currentDraftVersion.attachments', 'currentDraftVersion.reviewDecisions',
            'publishedVersion.articleContent.consultationCta.publishedVersion.consultationContent',
            'publishedVersion.faqContent', 'publishedVersion.consultationContent',
            'publishedVersion.attachments', 'publishedVersion.reviewDecisions',
            'latestVersion.articleContent.consultationCta.publishedVersion.consultationContent',
            'latestVersion.faqContent', 'latestVersion.consultationContent',
            'latestVersion.attachments', 'latestVersion.reviewDecisions',
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to manage campus content',
            'errors' => null,
        ], 403));
    }
}
