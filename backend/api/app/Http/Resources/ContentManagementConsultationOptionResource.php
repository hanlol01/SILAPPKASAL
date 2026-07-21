<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentManagementConsultationOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'scope' => $this->scope?->value,
            'service_name' => $this->publishedVersion?->consultationContent?->service_name,
        ];
    }
}
