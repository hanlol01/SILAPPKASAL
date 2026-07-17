<?php

namespace App\Http\Requests;

use App\Models\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudyProgramIndexRequest extends FormRequest
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
            'university_id' => [
                'required',
                'integer',
                Rule::exists('universities', 'id')->where('is_active', true),
            ],
            'faculty_id' => [
                'nullable',
                'integer',
                Rule::exists('faculties', 'id')->where('is_active', true),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('faculty_id')) {
                    return;
                }

                $exists = Faculty::query()
                    ->whereKey($this->integer('faculty_id'))
                    ->where('university_id', $this->integer('university_id'))
                    ->where('is_active', true)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('faculty_id', __('validation.custom.faculty_id.campus_selection'));
                }
            },
        ];
    }
}
