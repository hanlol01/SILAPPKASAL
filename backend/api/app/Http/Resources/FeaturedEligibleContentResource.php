<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedEligibleContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'scope' => $this->scope?->value,
            'university' => $this->university ? [
                'code' => $this->university->code,
                'name' => $this->university->name,
            ] : null,
            'title' => $this->publishedVersion?->title,
            'excerpt' => $this->publishedVersion?->excerpt,
            'published_at' => $this->publishedVersion?->published_at?->toJSON(),
            'section' => $this->section?->code,
            'category' => $this->category?->name,
        ];
    }
}
