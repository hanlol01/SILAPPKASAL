<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedContentGovernanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();
        $state = ! $this->is_active
            ? ($this->active_until?->isBefore($now) ? 'expired' : 'inactive')
            : ($this->active_until?->isBefore($now)
                ? 'expired'
                : ($this->active_from?->isAfter($now) ? 'future' : 'current'));
        $version = $this->item?->publishedVersion;

        return [
            'public_id' => $this->public_id,
            'scope' => $this->scope?->value,
            'university' => $this->university ? [
                'code' => $this->university->code,
                'name' => $this->university->name,
            ] : null,
            'rank' => $this->rank,
            'is_active' => $this->is_active,
            'active_from' => $this->active_from?->toJSON(),
            'active_until' => $this->active_until?->toJSON(),
            'state' => $state,
            'updated_at' => $this->updated_at?->toJSON(),
            'concurrency_token' => $this->concurrencyToken(),
            'content' => $this->item ? [
                'public_id' => $this->item->public_id,
                'content_type' => $this->item->content_type?->value,
                'title' => $version?->title,
                'excerpt' => $version?->excerpt,
                'published_at' => $version?->published_at?->toJSON(),
                'section' => $this->item->section?->code,
                'category' => $this->item->category?->name,
            ] : null,
        ];
    }
}
