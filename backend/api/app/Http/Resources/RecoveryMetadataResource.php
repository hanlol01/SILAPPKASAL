<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecoveryMetadataResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decision_id' => $this->decision_id,
            'case_id' => $this->whenLoaded('decision', fn () => $this->decision?->recommendation?->case_id),
            'case_number' => $this->whenLoaded('decision', fn () => $this->decision?->recommendation?->case?->case_number),
            'registration_number' => $this->whenLoaded('decision', fn () => $this->decision?->recommendation?->case?->registration_number),
            'recovery_type' => $this->whenLoaded('recoveryType', fn (): array => [
                'code' => $this->recoveryType->code,
                'name' => $this->recoveryType->name,
                'description' => $this->recoveryType->description,
            ]),
            'status' => $this->whenLoaded('status', fn () => $this->status?->name),
            'status_code' => $this->status_code,
            'started_at' => $this->started_at?->toJSON(),
            'completed_at' => $this->completed_at?->toJSON(),
            'discontinued_at' => $this->discontinued_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'sensitive_details_available' => false,
        ];
    }
}
