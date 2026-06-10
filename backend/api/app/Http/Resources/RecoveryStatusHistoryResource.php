<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecoveryStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->whenLoaded('fromStatus', fn (): ?array => $this->fromStatus ? [
                'code' => $this->fromStatus->code,
                'name' => $this->fromStatus->name,
            ] : null),
            'to_status' => $this->whenLoaded('toStatus', fn (): array => [
                'code' => $this->toStatus->code,
                'name' => $this->toStatus->name,
            ]),
            'changed_by' => $this->whenLoaded('changedBy', fn (): array => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'changed_at' => $this->changed_at?->toJSON(),
        ];
    }
}
