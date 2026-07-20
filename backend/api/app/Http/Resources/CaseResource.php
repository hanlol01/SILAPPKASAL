<?php

namespace App\Http\Resources;

use App\Support\ReportInputProjection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'registration_number' => $this->registration_number,
            'status' => $this->whenLoaded('status', fn () => $this->status?->name),
            'status_code' => $this->status_code,
            'status_label' => $this->whenLoaded('status', fn () => $this->status?->description),
            'risk_level' => $this->whenLoaded('riskLevel', fn () => $this->riskLevel?->name),
            'risk_level_code' => $this->risk_level_code,
            'priority' => $this->priority_code,
            'current_stage' => $this->current_stage,
            'current_stage_label' => $this->whenLoaded('status', fn () => $this->status?->stage_name),
            'report_submitted_at' => $this->reportSubmittedAt(),
            'forwarded_at' => $this->forwarded_at?->toJSON(),
            'assessment_at' => $this->assessment_at?->toJSON(),
            'investigation_started_at' => $this->investigation_started_at?->toJSON(),
            'recommendation_at' => $this->recommendation_at?->toJSON(),
            'decision_at' => $this->decision_at?->toJSON(),
            'closed_at' => $this->closed_at?->toJSON(),
            'escalated_at' => $this->escalated_at?->toJSON(),
            'assignments' => CaseAssignmentResource::collection($this->whenLoaded('activeAssignments')),
            'workflow_context' => $this->when(
                $this->resource->getAttribute('workflow_context') !== null,
                $this->resource->getAttribute('workflow_context'),
            ),
        ];

        if (
            $this->resource->relationLoaded('reportSensitive')
            && $this->resource->getAttribute('include_report_input_details') === true
            && $this->reportSensitive !== null
        ) {
            $data['report'] = ReportInputProjection::make(
                $this->reportSensitive,
                $this->reportSensitive->report_type !== 'anonymous',
            );
        }

        return $data;
    }

    /**
     * Safe metadata-only timestamp of the originating report submission.
     * Uses already-loaded relations only; never triggers extra queries and
     * never exposes report narrative content.
     */
    private function reportSubmittedAt(): ?string
    {
        if ($this->resource->relationLoaded('reportSensitive')) {
            return $this->reportSensitive?->submitted_at?->toJSON();
        }

        if ($this->resource->relationLoaded('report')) {
            return $this->report?->submitted_at?->toJSON();
        }

        return null;
    }
}
