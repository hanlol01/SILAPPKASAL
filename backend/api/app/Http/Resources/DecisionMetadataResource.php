<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DecisionMetadataResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recommendation_id' => $this->recommendation_id,
            'case_id' => $this->whenLoaded('recommendation', fn () => $this->recommendation?->case_id),
            'case_number' => $this->whenLoaded('recommendation', fn () => $this->recommendation?->case?->case_number),
            'registration_number' => $this->whenLoaded('recommendation', fn () => $this->recommendation?->case?->registration_number),
            'status' => $this->whenLoaded('status', fn () => $this->status?->name),
            'status_code' => $this->status_code,
            'decision_number' => $this->decision_number,
            'recorded_at' => $this->recorded_at?->toJSON(),
            'finalized_at' => $this->finalized_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'sensitive_details_available' => false,
        ];
    }
}
