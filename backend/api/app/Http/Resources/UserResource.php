<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'nim' => $this->nim,
            'nip' => $this->nip,
            'phone_number' => $this->phone_number,
            'role' => new RoleResource($this->whenLoaded('role')),
            'permissions' => $this->permissions()->pluck('code')->values(),
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toJSON(),
        ];
    }
}
