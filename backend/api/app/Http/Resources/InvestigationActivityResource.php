<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestigationActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_type' => $this->activity_type,
            'activity_date' => $this->activity_date?->toDateString(),
            'description' => $this->description,
            'findings' => $this->findings,
            'notes' => $this->notes,
            'investigator' => $this->whenLoaded('investigator', fn (): array => [
                'id' => $this->investigator?->id,
                'name' => $this->investigator?->name,
            ]),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
