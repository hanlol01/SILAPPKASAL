<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseMinuteAnonymizedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'version' => $this->version,
            'status' => $this->status?->value,
            'occurred_at' => $this->occurred_at?->toJSON(),
            'anonymized_summary' => $this->anonymized_summary,
            'outcome' => $this->outcome,
            'follow_up' => $this->follow_up,
            'finalized_at' => $this->finalized_at?->toJSON(),
        ];
    }
}
