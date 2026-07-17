<?php

namespace App\Http\Resources;

use App\Enums\ReporterSafeStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'registration_number' => $this->registration_number,
            'tracking_code' => $this->tracking_code,
            'status' => ReporterSafeStatus::forReport($this->resource)->value,
            'report_type' => $this->report_type,
            'category' => new MasterDataResource($this->whenLoaded('category')),
            'submitted_at' => $this->submitted_at?->toJSON(),
        ];
    }
}
