<?php

namespace App\Http\Requests;

use App\Enums\ContentScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeaturedContentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::enum(ContentScope::class)],
            'university_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'state' => ['nullable', Rule::in(['current', 'future', 'expired', 'inactive'])],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }
}
