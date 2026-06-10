<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'tracking_code' => $this->tracking_code,
            'report_type' => $this->report_type,
            'category' => new MasterDataResource($this->whenLoaded('category')),
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toJSON(),
        ];
    }
}
