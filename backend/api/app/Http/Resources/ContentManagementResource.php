<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentManagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentDraftVersion ?? $this->latestVersion ?? $this->publishedVersion;
        $hasVersionCategory = $version !== null
            && ($version->category_name !== null || $version->category_id !== null);
        $category = $hasVersionCategory ? $version->category : $this->category;
        $categoryName = $hasVersionCategory
            ? ($version->category_name ?? $version->category?->name)
            : ($this->category_name ?? $this->category?->name);
        $status = $this->archived_at !== null ? 'archived' : $version?->lifecycle_status?->value;

        return [
            'public_id' => $this->public_id,
            'content_type' => $this->content_type?->value,
            'slug' => $this->slug,
            'scope' => $this->scope?->value,
            'section' => new ContentSectionResource($this->whenLoaded('section')),
            'category' => $category ? new ContentCategoryResource($category) : null,
            'category_name' => $categoryName,
            'lock_version' => $this->lock_version,
            'lifecycle_status' => $status,
            'version' => $version ? [
                'public_id' => $version->public_id,
                'version_number' => $version->version_number,
                'status' => $version->lifecycle_status?->value,
                'title' => $version->title,
                'excerpt' => $version->excerpt,
                'requires_editorial_review' => $version->requires_editorial_review,
            ] : null,
            'has_editable_version' => $this->archived_at === null
                && ($this->currentDraftVersion?->lifecycle_status?->editable() ?? false),
            'published_version' => $this->publishedVersion ? [
                'public_id' => $this->publishedVersion->public_id,
                'version_number' => $this->publishedVersion->version_number,
                'published_at' => $this->publishedVersion->published_at?->toJSON(),
            ] : null,
            'archived_at' => $this->archived_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
