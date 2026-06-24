<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', Rule::exists('roles', 'code')],
            'is_active' => ['sometimes', 'boolean'],
            'university_id' => ['sometimes', 'integer', Rule::exists('universities', 'id')],
            'faculty_id' => ['sometimes', 'integer', Rule::exists('faculties', 'id')],
            'study_program_id' => ['sometimes', 'integer', Rule::exists('study_programs', 'id')],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
