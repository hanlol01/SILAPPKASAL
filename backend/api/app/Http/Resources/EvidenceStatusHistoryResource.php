<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'changed_by' => $this->whenLoaded('changedBy', fn (): array => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'changed_at' => $this->changed_at?->toJSON(),
        ];
    }
}
