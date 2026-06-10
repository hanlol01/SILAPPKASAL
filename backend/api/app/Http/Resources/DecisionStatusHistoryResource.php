<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DecisionStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status_code' => $this->from_status_code,
            'from_status' => $this->whenLoaded('fromStatus', fn () => $this->fromStatus?->name),
            'to_status_code' => $this->to_status_code,
            'to_status' => $this->whenLoaded('toStatus', fn () => $this->toStatus?->name),
            'changed_by' => $this->whenLoaded('changedBy', fn (): array => [
                'id' => $this->changedBy?->id,
                'name' => $this->changedBy?->name,
            ]),
            'changed_at' => $this->changed_at?->toJSON(),
        ];
    }
}
