<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseMinuteMetadataResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'projection' => 'metadata',
            'public_id' => $this->public_id,
            'version' => $this->version,
            'status' => $this->status?->value,
            'occurred_at' => $this->occurred_at?->toJSON(),
            'finalized_at' => $this->finalized_at?->toJSON(),
            'case' => [
                'case_number' => $this->case?->case_number,
            ],
            'campus' => [
                'code' => $this->case?->report?->reporter?->university?->code,
            ],
        ];
    }
}
