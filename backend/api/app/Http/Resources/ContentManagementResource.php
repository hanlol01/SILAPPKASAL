<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentManagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $draft = $this->currentDraftVersion;

        return [
            'public_id' => $this->public_id,
            'content_type' => $this->content_type?->value,
            'slug' => $this->slug,
            'scope' => $this->scope?->value,
            'section' => new ContentSectionResource($this->whenLoaded('section')),
            'category' => $this->category ? new ContentCategoryResource($this->category) : null,
            'lock_version' => $this->lock_version,
            'draft' => $draft ? [
                'public_id' => $draft->public_id,
                'version_number' => $draft->version_number,
                'status' => $draft->lifecycle_status?->value,
                'title' => $draft->title,
                'excerpt' => $draft->excerpt,
                'requires_editorial_review' => $draft->requires_editorial_review,
            ] : null,
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
