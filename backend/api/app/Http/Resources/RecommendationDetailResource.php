<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'case_number' => $this->whenLoaded('case', fn () => $this->case?->case_number),
            'registration_number' => $this->whenLoaded('case', fn () => $this->case?->registration_number),
            'investigation_id' => $this->investigation_id,
            'status' => $this->whenLoaded('status', fn () => $this->status?->name),
            'status_code' => $this->status_code,
            'status_label' => $this->whenLoaded('status', fn () => $this->status?->description),
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'conclusion' => $this->conclusion,
            'recommended_actions' => $this->recommended_actions,
            'sanction_recommendation' => $this->sanction_recommendation,
            'recovery_recommendation' => $this->recovery_recommendation,
            'prevention_recommendation' => $this->prevention_recommendation,
            'status_history' => RecommendationStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'submitted_at' => $this->submitted_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
