<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecoveryMonitoringResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recovery_id' => $this->recovery_id,
            'monitoring_date' => $this->monitoring_date?->toDateString(),
            'status' => $this->status,
            'condition_summary' => $this->condition_summary,
            'follow_up_plan' => $this->follow_up_plan,
            'notes' => $this->notes,
            'monitor' => $this->whenLoaded('monitor', fn (): array => [
                'id' => $this->monitor->id,
                'name' => $this->monitor->name,
            ]),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
