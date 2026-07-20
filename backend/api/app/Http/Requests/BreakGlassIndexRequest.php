<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BreakGlassIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'case_id' => ['sometimes', 'integer', 'exists:cases,id'],
        ];
    }
}
