<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceCustodyEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_type' => $this->event_type,
            'actor' => $this->whenLoaded('actor', fn (): array => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ]),
            'event_at' => $this->event_at?->toJSON(),
            'details' => $this->details,
        ];
    }
}
