<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
            'status_label' => $this->whenLoaded('status', fn () => $this->status?->description),
            'outcome_code' => $this->outcome_code,
            'decision_number' => $this->decision_number,
            'decision_date' => $this->decision_date?->toDateString(),
            'decision_summary' => $this->decision_summary,
            'decision_content' => $this->decision_content,
            'recorder' => $this->whenLoaded('recorder', fn (): array => [
                'id' => $this->recorder?->id,
                'name' => $this->recorder?->name,
            ]),
            'status_history' => DecisionStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'recorded_at' => $this->recorded_at?->toJSON(),
            'finalized_at' => $this->finalized_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
