<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'label' => ['id' => $this->label_id, 'en' => $this->label_en],
            'description' => $this->description,
            'display_order' => $this->display_order,
        ];
    }
}
