<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $case = $this->whenLoaded('case');
        $caseStatus = $case instanceof \App\Models\CaseRecord && $case->relationLoaded('status')
            ? $case->status?->name
            : null;

        return [
            'registration_number' => $this->registration_number,
            'tracking_code' => $this->tracking_code,
            'status' => $caseStatus ?? $this->status,
            'report_type' => $this->report_type,
            'category' => new MasterDataResource($this->whenLoaded('category')),
            'submitted_at' => $this->submitted_at?->toJSON(),
        ];
    }
}
