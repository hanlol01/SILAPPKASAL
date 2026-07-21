<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon_code' => $this->icon_code,
            'display_order' => $this->display_order,
            'section_code' => $this->whenLoaded('section', fn () => $this->section?->code),
            'scope' => $this->scope?->value,
        ];
    }
}
