<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ContentCategory;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use App\Support\ApiErrorCode;
use App\Support\ContentCategoryName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContentCategoryRegistryService
{
    public function __construct(
        private readonly ContentItemPolicy $policy,
        private readonly AuditLogService $audit,
    ) {}

    /** @return Collection<int, array<string, bool|int|string|null>> */
    public function categories(User $actor, string $sectionCode): Collection
    {
        $context = $this->context($actor, false);
        $section = $this->section($sectionCode);
        $usage = $this->usageByName($actor, (int) $section->id, true);
        $visibleUsage = $actor->hasRole('super_admin')
            ? $this->usageByName($actor, (int) $section->id, false)
            : $usage;

        $query = ContentCategory::query()
            ->where('section_id', $section->id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN scope = 'global' THEN 0 ELSE 1 END")
            ->orderBy('display_order')
            ->orderBy('name');

        if ($context['scope'] === ContentScope::Global) {
            $query->where('scope', ContentScope::Global->value)->whereNull('university_id');
        } else {
            $query->where(function (Builder $builder) use ($context): void {
                $builder->where(function (Builder $global): void {
                    $global->where('scope', ContentScope::Global->value)->whereNull('university_id');
                })->orWhere(function (Builder $campus) use ($context): void {
                    $campus->where('scope', ContentScope::Campus->value)
                        ->where('university_id', $context['university_id']);
                });
            });
        }

        $result = collect();
        foreach ($query->get() as $category) {
            $key = $category->normalized_name ?: $this->key($category->name);
            $count = (int) ($usage->get($key)['count'] ?? 0);
            $manageable = $category->scope === $context['scope']
                && (int) ($category->university_id ?? 0) === (int) ($context['university_id'] ?? 0)
                && $context['can_manage'];

            $result->put($key, $this->payload($category, $sectionCode, $count, $manageable));
        }

        foreach ($visibleUsage as $key => $usedCategory) {
            if ($result->has($key)) {
                continue;
            }

            $result->put($key, [
                'public_id' => null,
                'name' => $usedCategory['label'],
                'section_code' => $sectionCode,
                'scope' => $context['scope']->value,
                'usage_count' => $usedCategory['count'],
                'can_manage' => false,
                'can_deactivate' => false,
            ]);
        }

        return $result
            ->values()
            ->sortBy(fn (array $category): string => Str::lower($category['name']))
            ->values();
    }

    /**
     * @return array{category: ContentCategory, result: string, usage_count: int, can_deactivate: bool}
     */
    public function create(User $actor, string $sectionCode, string $name): array
    {
        $context = $this->context($actor, true);
        $section = $this->section($sectionCode);
        $name = ContentCategoryName::display($name);
        $normalizedName = ContentCategoryName::normalize($name);

        try {
            $outcome = DB::transaction(function () use (
                $actor,
                $context,
                $section,
                $sectionCode,
                $name,
                $normalizedName
            ): array {
                $existing = ContentCategory::query()
                    ->where('section_id', $section->id)
                    ->where('scope', $context['scope']->value)
                    ->where('university_id', $context['university_id'])
                    ->where('normalized_name', $normalizedName)
                    ->lockForUpdate()
                    ->first();

                if ($existing?->is_active) {
                    return ['category' => $existing, 'result' => 'existing'];
                }

                if ($existing) {
                    $existing->forceFill([
                        'name' => $name,
                        'is_active' => true,
                        'creator_id' => $actor->id,
                    ])->save();
                    $category = $existing;
                    $result = 'reactivated';
                } else {
                    $slug = Str::slug($name) ?: 'kategori-'.Str::lower(Str::random(8));
                    $slug = $this->availableSlug((int) $section->id, $context['scope'], $context['university_id'], $slug);
                    $code = Str::limit("{$sectionCode}-{$slug}", 91, '').'-'.Str::lower(Str::random(8));
                    $displayOrder = ((int) ContentCategory::query()
                        ->where('section_id', $section->id)
                        ->where('scope', $context['scope']->value)
                        ->where('university_id', $context['university_id'])
                        ->max('display_order')) + 1;

                    $category = ContentCategory::query()->create([
                        'section_id' => $section->id,
                        'code' => $code,
                        'name' => $name,
                        'slug' => $slug,
                        'display_order' => min($displayOrder, 65535),
                        'scope' => $context['scope'],
                        'university_id' => $context['university_id'],
                        'is_active' => true,
                        'creator_id' => $actor->id,
                    ]);
                    $result = 'created';
                }

                $category->load('section');
                $this->audit->record(
                    action: AuditAction::ContentCategoryCreated,
                    category: AuditCategory::Content,
                    actor: $actor,
                    subject: $category,
                    metadata: [
                        'category_public_id' => $category->public_id,
                        'category_name' => $category->name,
                        'section_code' => $sectionCode,
                        'scope' => $context['scope']->value,
                        'result' => $result,
                    ],
                );

                return ['category' => $category, 'result' => $result];
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $category = ContentCategory::query()
                ->where('section_id', $section->id)
                ->where('scope', $context['scope']->value)
                ->where('university_id', $context['university_id'])
                ->where('normalized_name', $normalizedName)
                ->where('is_active', true)
                ->first();

            if (! $category) {
                throw $exception;
            }

            $outcome = ['category' => $category, 'result' => 'existing'];
        }

        $category = $outcome['category']->loadMissing('section');
        $usage = $this->usageByName($actor, (int) $section->id, true);
        $usageCount = (int) ($usage->get($normalizedName)['count'] ?? 0);

        return [
            'category' => $category,
            'result' => $outcome['result'],
            'usage_count' => $usageCount,
            'can_deactivate' => $usageCount === 0,
        ];
    }

    public function deactivate(User $actor, string $publicId): ContentCategory
    {
        $context = $this->context($actor, true);

        return DB::transaction(function () use ($actor, $context, $publicId): ContentCategory {
            $category = ContentCategory::query()
                ->with('section')
                ->where('public_id', $publicId)
                ->whereIn('section_id', ContentSection::query()->whereIn('code', ['education', 'policy'])->select('id'))
                ->where('scope', $context['scope']->value)
                ->where('university_id', $context['university_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $usage = $this->usageByName($actor, (int) $category->section_id, true);
            $usageCount = (int) ($usage->get($this->key($category->name))['count'] ?? 0);

            if ($usageCount > 0) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Kategori masih digunakan dan tidak dapat dinonaktifkan.',
                    'error_code' => ApiErrorCode::ContentCategoryInUse,
                    'data' => ['usage_count' => $usageCount],
                ], 409));
            }

            $category->forceFill(['is_active' => false])->save();
            $this->audit->record(
                action: AuditAction::ContentCategoryDeactivated,
                category: AuditCategory::Content,
                actor: $actor,
                subject: $category,
                metadata: [
                    'category_public_id' => $category->public_id,
                    'category_name' => $category->name,
                    'section_code' => $category->section?->code,
                    'scope' => $context['scope']->value,
                    'usage_count' => 0,
                    'result' => 'deactivated',
                ],
            );

            return $category;
        });
    }

    /** @return array{scope: ContentScope, university_id: int|null, can_manage: bool} */
    private function context(User $actor, bool $mutation): array
    {
        if ($actor->hasRole('super_admin')) {
            abort_unless(! $mutation || $this->policy->governCategory($actor), 403);

            return ['scope' => ContentScope::Global, 'university_id' => null, 'can_manage' => $this->policy->governCategory($actor)];
        }

        if ($actor->hasRole('admin')) {
            abort_unless(! $mutation || $this->policy->manageCampusCategory($actor), 403);
            abort_if($actor->university_id === null, 403);

            return [
                'scope' => ContentScope::Campus,
                'university_id' => (int) $actor->university_id,
                'can_manage' => $this->policy->manageCampusCategory($actor),
            ];
        }

        abort(403);
    }

    private function section(string $sectionCode): ContentSection
    {
        return ContentSection::query()
            ->where('code', $sectionCode)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /** @return Collection<string, array{count: int, label: string}> */
    private function usageByName(User $actor, int $sectionId, bool $allScopes): Collection
    {
        $usage = collect();
        $rows = $this->usageQuery($actor, $sectionId, $allScopes)
            ->leftJoin('content_categories as version_categories', 'version_categories.id', '=', 'content_versions.category_id')
            ->selectRaw('content_items.id as item_id, COALESCE(content_versions.category_name, version_categories.name) as category_label')
            ->get();
        $seen = [];

        foreach ($rows as $row) {
            $label = trim((string) $row->category_label);
            if ($label === '') {
                continue;
            }
            $key = $this->key($label);
            $identity = $key.':'.(int) $row->item_id;
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $current = $usage->get($key, ['count' => 0, 'label' => $label]);
            $usage->put($key, ['count' => $current['count'] + 1, 'label' => $current['label']]);
        }

        return $usage;
    }

    private function usageQuery(User $actor, int $sectionId, bool $allScopes = true): Builder
    {
        $query = ContentVersion::query()
            ->join('content_items', 'content_items.id', '=', 'content_versions.content_item_id')
            ->where('content_items.content_type', ContentType::Article->value)
            ->where('content_items.section_id', $sectionId)
            ->whereNull('content_items.archived_at')
            ->whereNull('content_items.deleted_at')
            ->where(function (Builder $active): void {
                $active->whereColumn('content_versions.id', 'content_items.current_draft_version_id')
                    ->orWhereColumn('content_versions.id', 'content_items.published_version_id');
            });

        if ($actor->hasRole('super_admin')) {
            return $allScopes
                ? $query
                : $query->where('content_items.scope', ContentScope::Global->value)
                    ->whereNull('content_items.university_id');
        }

        return $query->where('content_items.scope', ContentScope::Campus->value)
            ->where('content_items.university_id', $actor->university_id);
    }

    /** @return array<string, bool|int|string|null> */
    private function payload(ContentCategory $category, string $sectionCode, int $usageCount, bool $manageable): array
    {
        return [
            'public_id' => $category->public_id,
            'name' => $category->name,
            'section_code' => $sectionCode,
            'scope' => $category->scope->value,
            'usage_count' => $usageCount,
            'can_manage' => $manageable,
            'can_deactivate' => $manageable && $usageCount === 0,
        ];
    }

    private function availableSlug(int $sectionId, ContentScope $scope, ?int $universityId, string $base): string
    {
        $candidate = Str::limit($base, 170, '');
        $attempt = 0;

        while (ContentCategory::query()
            ->where('section_id', $sectionId)
            ->where('scope', $scope->value)
            ->where('university_id', $universityId)
            ->where('slug', $candidate)
            ->exists()) {
            $attempt++;
            $candidate = Str::limit($base, 160, '').'-'.$attempt.'-'.Str::lower(Str::random(6));
        }

        return $candidate;
    }

    private function key(string $name): string
    {
        return ContentCategoryName::normalize($name);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23505'
            || ($sqlState === '23000' && in_array($driverCode, [19, 1062], true));
    }
}
