<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'role' => $this->actor->role?->code,
            ] : null),
            'actor_id' => $this->actor_id,
            'request_id' => $this->request_id,
            'action' => $this->action,
            'category' => $this->category,
            'severity' => $this->severity,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'metadata' => $this->metadata ?? ['is_elevated_access' => false],
            'before_changes' => $this->before_changes ?? [],
            'after_changes' => $this->after_changes ?? [],
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
