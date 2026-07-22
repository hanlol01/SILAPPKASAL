<?php

namespace App\Services;

use App\Contracts\ContentImageProcessor;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ContentItem;
use App\Models\FeaturedContent;
use App\Models\University;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use App\Support\ApiErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class FeaturedContentGovernanceService
{
    public function __construct(
        private readonly ContentItemPolicy $policy,
        private readonly AuditLogService $auditLogs,
        private readonly ContentImageProcessor $imageProcessor,
    ) {}

    /** @param array<string, mixed> $filters @return Collection<int, FeaturedContent> */
    public function placements(User $actor, array $filters): Collection
    {
        $this->authorize($actor);
        $query = FeaturedContent::query()->with($this->relations());
        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }
        if (! empty($filters['university_code'])) {
            $query->whereHas('university', fn (Builder $university) => $university->where('code', $filters['university_code']));
        }
        if (! empty($filters['search'])) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $filters['search']))).'%';
            $query->whereHas('item.publishedVersion', fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '!'", [$needle]));
        }

        $placements = $query
            ->orderByRaw("CASE WHEN scope = 'global' THEN 0 ELSE 1 END")
            ->orderBy('university_id')
            ->orderBy('rank')
            ->orderBy('public_id')
            ->get();

        if (! empty($filters['state'])) {
            $placements = $placements->filter(fn (FeaturedContent $placement) => $this->state($placement) === $filters['state'])->values();
        }

        return $placements;
    }

    /** @return Collection<int, ContentItem> */
    public function eligible(User $actor, ContentScope $scope, ?string $universityCode, ?string $search): Collection
    {
        $this->authorize($actor);
        $university = $this->resolveUniversity($scope, $universityCode);
        $query = ContentItem::query()
            ->where('content_type', ContentType::Article->value)
            ->whereHas('section', fn (Builder $section) => $section->where('code', 'education'))
            ->where('scope', $scope->value)
            ->where('university_id', $university?->id)
            ->whereNull('archived_at')
            ->whereNotNull('published_version_id')
            ->whereHas('publishedVersion', fn (Builder $version) => $version
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->when($this->coverRequired(), fn (Builder $item) => $item
                ->whereHas('publishedVersion.articleContent.coverAttachment'))
            ->with(['section', 'category', 'university', 'publishedVersion']);
        if (filled($search)) {
            $needle = '%'.$this->escapeLike(mb_strtolower(trim((string) $search))).'%';
            $query->whereHas('publishedVersion', fn (Builder $version) => $version
                ->whereRaw("LOWER(content_versions.title) LIKE ? ESCAPE '!'", [$needle]));
        }

        return $query->orderByDesc('updated_at')->orderBy('public_id')->limit(50)->get();
    }

    /** @return Collection<int, University> */
    public function campuses(User $actor): Collection
    {
        $this->authorize($actor);

        return University::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): FeaturedContent
    {
        return DB::transaction(function () use ($actor, $data): FeaturedContent {
            $actor = $this->lockedActor($actor);
            $scope = ContentScope::from((string) $data['scope']);
            $university = $this->resolveUniversity($scope, $data['university_code'] ?? null, true);
            $item = $this->resolveItem((string) $data['content_public_id'], $scope, $university?->id);

            try {
                $placement = FeaturedContent::query()->create([
                    'scope' => $scope,
                    'university_id' => $university?->id,
                    'content_item_id' => $item->id,
                    'rank' => (int) $data['rank'],
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'active_from' => $data['active_from'] ?? null,
                    'active_until' => $data['active_until'] ?? null,
                    'creator_id' => $actor->id,
                ]);
            } catch (Throwable $exception) {
                $this->throwFeaturedFailure($exception);
            }

            $this->record($actor, $placement, $item, 'created');

            return $placement->load($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, FeaturedContent $placement, array $data): FeaturedContent
    {
        return DB::transaction(function () use ($actor, $placement, $data): FeaturedContent {
            $actor = $this->lockedActor($actor);
            $placement = FeaturedContent::query()->whereKey($placement->id)->lockForUpdate()->firstOrFail();
            $this->assertFresh($placement, (string) $data['concurrency_token']);
            $previousItemId = (int) $placement->content_item_id;
            $scope = array_key_exists('scope', $data) ? ContentScope::from((string) $data['scope']) : $placement->scope;
            $universityCode = array_key_exists('university_code', $data)
                ? $data['university_code']
                : $placement->university?->code;
            $university = $this->resolveUniversity($scope, $universityCode, true);
            $itemPublicId = (string) ($data['content_public_id'] ?? $placement->item()->value('public_id'));
            $item = $this->resolveItem($itemPublicId, $scope, $university?->id);

            try {
                $updates = [
                    'scope' => $scope,
                    'university_id' => $university?->id,
                    'content_item_id' => $item->id,
                ];
                foreach (['rank', 'is_active', 'active_from', 'active_until'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $updates[$field] = match ($field) {
                            'rank' => (int) $data[$field],
                            'is_active' => (bool) $data[$field],
                            default => $data[$field],
                        };
                    }
                }
                $placement->fill($updates)->save();
            } catch (Throwable $exception) {
                $this->throwFeaturedFailure($exception);
            }

            $this->record(
                $actor,
                $placement,
                $item,
                $previousItemId === (int) $item->id ? 'updated' : 'replaced',
            );

            return $placement->load($this->relations());
        });
    }

    public function remove(User $actor, FeaturedContent $placement, string $concurrencyToken): void
    {
        DB::transaction(function () use ($actor, $placement, $concurrencyToken): void {
            $actor = $this->lockedActor($actor);
            $placement = FeaturedContent::query()->whereKey($placement->id)->lockForUpdate()->firstOrFail();
            $this->assertFresh($placement, $concurrencyToken);
            $item = ContentItem::query()->whereKey($placement->content_item_id)->firstOrFail();
            $this->record($actor, $placement, $item, 'removed');
            $placement->delete();
        });
    }

    public function placement(User $actor, string $publicId): FeaturedContent
    {
        $this->authorize($actor);

        return FeaturedContent::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function resolveItem(string $publicId, ContentScope $scope, ?int $universityId): ContentItem
    {
        return ContentItem::query()
            ->where('public_id', $publicId)
            ->where('content_type', ContentType::Article->value)
            ->whereHas('section', fn (Builder $section) => $section->where('code', 'education'))
            ->where('scope', $scope->value)
            ->where('university_id', $universityId)
            ->whereNull('archived_at')
            ->whereNotNull('published_version_id')
            ->whereHas('publishedVersion', fn (Builder $version) => $version
                ->where('lifecycle_status', ContentLifecycleStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->when($this->coverRequired(), fn (Builder $item) => $item
                ->whereHas('publishedVersion.articleContent.coverAttachment'))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function coverRequired(): bool
    {
        return (bool) config('content.attachments.image_uploads_enabled', false)
            && $this->imageProcessor->isAvailable();
    }

    private function resolveUniversity(ContentScope $scope, mixed $code, bool $lock = false): ?University
    {
        if ($scope === ContentScope::Global) {
            if (filled($code)) {
                throw ValidationException::withMessages(['university_code' => ['Global featured placement cannot have a campus.']]);
            }

            return null;
        }
        if (blank($code)) {
            throw ValidationException::withMessages(['university_code' => ['Campus featured placement requires a campus.']]);
        }

        return University::query()->where('code', $code)->where('is_active', true)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->firstOrFail();
    }

    private function assertFresh(FeaturedContent $placement, string $concurrencyToken): void
    {
        if (! hash_equals($placement->concurrencyToken(), $concurrencyToken)) {
            throw $this->conflict('Featured placement changed after it was loaded.', ApiErrorCode::ContentFeaturedStale);
        }
    }

    private function authorize(User $actor): void
    {
        $actor->loadMissing('role.permissions');
        if (! $this->policy->manageFeatured($actor)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage featured content',
                'errors' => null,
            ], 403));
        }
    }

    private function lockedActor(User $actor): User
    {
        $actor = User::query()->with('role.permissions')->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $this->authorize($actor);

        return $actor;
    }

    private function record(User $actor, FeaturedContent $placement, ContentItem $item, string $result): void
    {
        $item->loadMissing(['section', 'category', 'university', 'publishedVersion']);
        $this->auditLogs->record(
            action: AuditAction::ContentFeaturedPlacementChanged,
            category: AuditCategory::Content,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $item,
            metadata: [
                'content_public_id' => $item->public_id,
                'version_number' => $item->publishedVersion?->version_number,
                'content_type' => $item->content_type->value,
                'section_code' => $item->section?->code,
                'category_code' => $item->category?->code,
                'scope' => $placement->scope->value,
                'university_code' => $item->university?->code,
                'rank' => $placement->rank,
                'result' => $result,
            ],
        );
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['university', 'item.section', 'item.category', 'item.publishedVersion'];
    }

    private function state(FeaturedContent $placement): string
    {
        if ($placement->active_until?->isBefore(now())) {
            return 'expired';
        }
        if (! $placement->is_active) {
            return 'inactive';
        }

        return $placement->active_from?->isAfter(now()) ? 'future' : 'current';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function throwFeaturedFailure(Throwable $exception): never
    {
        if ($exception instanceof QueryException || $exception instanceof \InvalidArgumentException) {
            throw $this->conflict('The featured placement conflicts with current rank, scope, or eligibility rules.', ApiErrorCode::ContentFeaturedConflict);
        }

        throw $exception;
    }

    private function conflict(string $message, string $code): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'errors' => null,
        ], 409)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]));
    }
}
