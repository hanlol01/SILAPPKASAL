<?php

namespace App\Http\Requests;

use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'faculty_id' => $this->filled('faculty_id') ? $this->integer('faculty_id') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var StudyProgram|null $studyProgram */
        $studyProgram = $this->route('studyProgram');

        return [
            'university_id' => ['required', 'integer', Rule::exists('universities', 'id')->where('is_active', true)],
            'faculty_id' => ['nullable', 'integer', Rule::exists('faculties', 'id')->where('is_active', true)],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('study_programs', 'code')
                    ->where(fn ($query) => $query->where('university_id', $this->integer('university_id')))
                    ->ignore($studyProgram?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'degree_level' => ['required', 'string', Rule::in(['D3', 'D4', 'S1', 'S2', 'S3', 'profesi'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('faculty_id')) {
                    return;
                }

                $facultyMatchesUniversity = Faculty::query()
                    ->whereKey($this->integer('faculty_id'))
                    ->where('university_id', $this->integer('university_id'))
                    ->where('is_active', true)
                    ->exists();

                if (! $facultyMatchesUniversity) {
                    $validator->errors()->add('faculty_id', 'The selected faculty does not belong to the selected university.');
                }
            },
        ];
    }
}
