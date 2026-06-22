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

        return [
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
            'submitted_at' => $this->submitted_at?->toJSON(),
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'forwarded_at' => $this->forwarded_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
