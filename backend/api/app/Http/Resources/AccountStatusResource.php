<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['user']->id,
            'role' => [
                'code' => $this->resource['user']->role?->code,
                'name' => $this->resource['user']->role?->name,
            ],
            'is_active' => $this->resource['user']->is_active,
            'email_verified_at' => $this->resource['user']->email_verified_at?->toJSON(),
            'created_at' => $this->resource['user']->created_at?->toJSON(),
            'registration_number' => $this->resource['registration_number'],
        ];
    }
}
