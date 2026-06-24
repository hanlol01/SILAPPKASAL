<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReporterRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'name' => $this->name,
            'email' => $this->email,
            'nim' => $this->nim,
            'phone_number' => $this->phone_number,
            'university_id' => $this->university_id,
            'faculty_id' => $this->faculty_id,
            'study_program_id' => $this->study_program_id,
            'university' => $this->whenLoaded('university', fn () => [
                'id' => $this->university?->id,
                'code' => $this->university?->code,
                'name' => $this->university?->name,
            ]),
            'faculty' => $this->whenLoaded('faculty', fn () => $this->faculty ? [
                'id' => $this->faculty->id,
                'code' => $this->faculty->code,
                'name' => $this->faculty->name,
            ] : null),
            'study_program' => $this->whenLoaded('studyProgram', fn () => [
                'id' => $this->studyProgram?->id,
                'code' => $this->studyProgram?->code,
                'name' => $this->studyProgram?->name,
                'degree_level' => $this->studyProgram?->degree_level,
            ]),
            'status' => $this->status?->value ?? $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'rejection_reason' => $this->rejection_reason,
            'approved_user_id' => $this->approved_user_id,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
