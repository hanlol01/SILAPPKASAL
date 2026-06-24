<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserManagementResource extends JsonResource
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
            'role' => [
                'code' => $this->role?->code,
                'name' => $this->role?->name,
            ],
            'university_id' => $this->university_id,
            'faculty_id' => $this->faculty_id,
            'study_program_id' => $this->study_program_id,
            'university' => $this->whenLoaded('university', fn () => $this->university ? [
                'id' => $this->university->id,
                'code' => $this->university->code,
                'name' => $this->university->name,
            ] : null),
            'faculty' => $this->whenLoaded('faculty', fn () => $this->faculty ? [
                'id' => $this->faculty->id,
                'code' => $this->faculty->code,
                'name' => $this->faculty->name,
            ] : null),
            'study_program' => $this->whenLoaded('studyProgram', fn () => $this->studyProgram ? [
                'id' => $this->studyProgram->id,
                'code' => $this->studyProgram->code,
                'name' => $this->studyProgram->name,
                'degree_level' => $this->studyProgram->degree_level,
            ] : null),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
