<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCampusSelection;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReporterRegistrationStoreRequest extends FormRequest
{
    use ValidatesCampusSelection;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'nim' => trim((string) $this->input('nim')),
            'name' => trim((string) $this->input('name')),
            'faculty_id' => $this->filled('faculty_id') ? $this->integer('faculty_id') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'nim' => ['required', 'string', 'max:50'],
            'phone_number' => ['required', 'string', new PhoneNumber],
            'university_id' => ['required', 'integer', Rule::exists('universities', 'id')->where('is_active', true)],
            'faculty_id' => ['nullable', 'integer', Rule::exists('faculties', 'id')->where('is_active', true)],
            'study_program_id' => ['required', 'integer', Rule::exists('study_programs', 'id')->where('is_active', true)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateCampusSelection($validator),
        ];
    }
}
