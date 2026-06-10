<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForwardReportToCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'report' => [
                'id' => $this->report?->id,
                'status' => $this->report?->status,
                'forwarded_at' => $this->report?->forwarded_at?->toJSON(),
            ],
            'case' => new CaseResource($this->resource),
        ];
    }
}
