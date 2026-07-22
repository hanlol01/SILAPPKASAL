<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentPublishedIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['sometimes', 'string', Rule::in(['education', 'policy', 'faq', 'consultation'])],
            'category' => ['sometimes', 'uuid'],
            'article_category' => ['sometimes', 'string', 'max:100'],
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'require_cover' => ['sometimes', 'boolean'],
        ];
    }
}
