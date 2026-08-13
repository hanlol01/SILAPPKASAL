<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfProfileResource extends JsonResource
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
            'profile_status' => $this->profile_status,
            'profile_status_other' => $this->profile_status_other,
            'address' => $this->address,
            'role' => [
                'code' => $this->role?->code,
                'name' => $this->role?->name,
            ],
        ];
    }
}
