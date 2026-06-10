<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'max:20'],
            'risk_level' => ['sometimes', 'string', 'max:20'],
            'priority' => ['sometimes', 'string', 'max:20'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
