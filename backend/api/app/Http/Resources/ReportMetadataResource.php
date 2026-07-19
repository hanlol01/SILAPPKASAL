<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportMetadataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAnonymous = $this->report_type === 'anonymous';

        $data = [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'report_type' => $this->report_type,
            'is_anonymous' => $isAnonymous,
            'reporter' => $isAnonymous ? ['masked' => true] : $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
            ]),
            'category' => new MasterDataResource($this->whenLoaded('category')),
            'status' => $this->status,
            'priority' => new MasterDataResource($this->whenLoaded('priorityLevel')),
            'case' => $this->whenLoaded('case', function () {
                if ($this->case === null) {
                    return null;
                }

                return [
                    'id' => $this->case->id,
                    'case_number' => $this->case->case_number,
                    'active_assignments' => $this->case->relationLoaded('activeAssignments')
                        ? $this->case->activeAssignments->map(fn ($assignment) => [
                            'satgas_id' => $assignment->satgas_id,
                            'satgas_name' => $assignment->satgas?->name,
                            'is_lead' => $assignment->is_lead,
                            'is_active' => $assignment->is_active,
                        ])->values()
                        : [],
                ];
            }),
            'submitted_at' => $this->submitted_at?->toJSON(),
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'forwarded_at' => $this->forwarded_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];

        if ($this->resource->getAttribute('sensitive_oversight') === true) {
            $data['sensitive_details'] = [
                'chronology' => $this->chronology,
                'incident_date' => $this->incident_date?->toDateString(),
                'incident_time' => $this->incident_time,
                'incident_location' => $this->incident_location,
                'respondent_name' => $this->respondent_name,
                'respondent_campus_status' => $this->respondent_campus_status,
                'respondent_relation' => $this->respondent_relation,
                'respondent_details' => $this->respondent_details,
                'witness_info' => $this->witness_info,
            ];
        }

        return $data;
    }
}
