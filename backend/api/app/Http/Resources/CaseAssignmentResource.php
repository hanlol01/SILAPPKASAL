<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'satgas_id' => $this->satgas_id,
            'satgas_name' => $this->whenLoaded('satgas', fn () => $this->satgas?->name),
            'assigned_by' => $this->assigned_by,
            'is_lead' => $this->is_lead,
            'is_active' => $this->is_active,
            'assigned_at' => $this->assigned_at?->toJSON(),
            'unassigned_at' => $this->unassigned_at?->toJSON(),
        ];
    }
}
