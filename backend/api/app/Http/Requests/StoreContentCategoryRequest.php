<?php

namespace App\Http\Requests;

use App\Support\ContentCategoryName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge([
                'name' => ContentCategoryName::display($this->input('name')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'section' => ['required', 'string', Rule::in(['education', 'policy'])],
            'name' => ['required', 'string', 'max:100', 'regex:/[\pL\pN]/u'],
        ];
    }
}
