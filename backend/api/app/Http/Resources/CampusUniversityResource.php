<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampusUniversityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'address' => $this->address,
            'website' => $this->website,
            'email' => $this->email,
            'hotline' => $this->hotline,
            'type' => $this->type,
            'has_faculties' => $this->has_faculties,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'faculties_count' => $this->whenCounted('faculties'),
            'study_programs_count' => $this->whenCounted('studyPrograms'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
