<?php

namespace App\Http\Resources;

use App\Models\CaseRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $case = $this->whenLoaded('case');

        return [
            'registration_number' => $this->registration_number,
            'report_type' => $this->report_type,
            'category' => new MasterDataResource($this->whenLoaded('category')),
            'portal_status' => $this->portal_status,
            'submitted_at' => $this->submitted_at?->toJSON(),
            'forwarded_at' => $this->forwarded_at?->toJSON(),
            'case' => $case instanceof CaseRecord ? [
                'has_case' => true,
                'portal_status' => $this->portal_status,
            ] : [
                'has_case' => false,
                'portal_status' => $this->portal_status,
            ],
        ];
    }
}
