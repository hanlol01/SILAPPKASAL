<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'notification_type_code' => $this->data['notification_type_code'] ?? null,
            'event' => $this->data['event'] ?? null,
            'title' => $this->data['title'] ?? null,
            'body' => $this->data['body'] ?? null,
            'data' => collect($this->data)
                ->except(['title', 'body'])
                ->all(),
            'read_at' => $this->read_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
