<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFacultyRequest extends FormRequest
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
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'university_id' => ['required', 'integer', Rule::exists('universities', 'id')->where('is_active', true)],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('faculties', 'code')->where(fn ($query) => $query->where('university_id', $this->integer('university_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! \App\Models\University::query()->whereKey($this->integer('university_id'))->where('has_faculties', true)->exists()) {
                    $validator->errors()->add('university_id', 'The selected university does not use faculties.');
                }
            },
        ];
    }
}
