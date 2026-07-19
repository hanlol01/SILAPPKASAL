<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestigationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activityCountsByStage = $this->resource->relationLoaded('activities')
            ? $this->activities
                ->filter(fn ($activity): bool => $activity->investigation_stage_code !== null)
                ->countBy('investigation_stage_code')
                ->all()
            : [];

        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'case_number' => $this->whenLoaded('case', fn () => $this->case?->case_number),
            'registration_number' => $this->whenLoaded('case', fn () => $this->case?->registration_number),
            'status' => $this->whenLoaded('status', fn () => $this->status?->name),
            'status_code' => $this->status_code,
            'status_label' => $this->whenLoaded('status', fn () => $this->status?->description),
            'lead_investigator' => $this->whenLoaded('leadInvestigator', fn (): array => [
                'id' => $this->leadInvestigator?->id,
                'name' => $this->leadInvestigator?->name,
            ]),
            'plan_summary' => $this->plan_summary,
            'findings' => $this->findings,
            'conclusion' => $this->conclusion,
            'activities' => InvestigationActivityResource::collection($this->whenLoaded('activities')),
            'activity_counts_by_stage' => $activityCountsByStage,
            'current_stage_activity_count' => (int) ($activityCountsByStage[$this->status_code] ?? 0),
            'started_at' => $this->started_at?->toJSON(),
            'completed_at' => $this->completed_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
