<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaseAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'risk_level_code' => [
                'required',
                'string',
                'max:10',
                Rule::exists('risk_levels', 'code')->where('is_active', true),
            ],
            'priority_level_code' => [
                'required',
                'string',
                'max:10',
                Rule::exists('priority_levels', 'code')->where('is_active', true),
            ],
        ];
    }
}
