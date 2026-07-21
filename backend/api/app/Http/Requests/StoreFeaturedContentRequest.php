<?php

namespace App\Http\Requests;

use App\Enums\ContentScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeaturedContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'content_public_id' => ['required', 'uuid'],
            'scope' => ['required', Rule::enum(ContentScope::class)],
            'university_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'required_if:scope,campus'],
            'rank' => ['required', 'integer', 'between:1,5'],
            'is_active' => ['sometimes', 'boolean'],
            'active_from' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
        ];
    }
}
