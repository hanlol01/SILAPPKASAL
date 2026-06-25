<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampusStudyProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'university_id' => $this->university_id,
            'faculty_id' => $this->faculty_id,
            'code' => $this->code,
            'name' => $this->name,
            'degree_level' => $this->degree_level,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'university' => $this->whenLoaded('university', fn () => [
                'id' => $this->university->id,
                'code' => $this->university->code,
                'name' => $this->university->name,
            ]),
            'faculty' => $this->whenLoaded('faculty', fn () => $this->faculty ? [
                'id' => $this->faculty->id,
                'code' => $this->faculty->code,
                'name' => $this->faculty->name,
            ] : null),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
