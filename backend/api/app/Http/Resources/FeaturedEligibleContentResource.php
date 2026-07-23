<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedEligibleContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->publishedVersion;

        return [
            'public_id' => $this->public_id,
            'scope' => $this->scope?->value,
            'university' => $this->university ? [
                'code' => $this->university->code,
                'name' => $this->university->name,
            ] : null,
            'title' => $version?->title,
            'excerpt' => $version?->excerpt,
            'published_at' => $version?->published_at?->toJSON(),
            'section' => $this->section?->code,
            'category' => $version?->category_name ?? $version?->category?->name,
        ];
    }
}
