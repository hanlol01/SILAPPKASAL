<?php

namespace App\Http\Requests;

use App\Enums\DecisionOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDecisionRequest extends FormRequest
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
            'outcome_code' => ['sometimes', 'required', 'string', Rule::in(DecisionOutcome::values())],
            'decision_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'decision_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'decision_summary' => ['sometimes', 'required', 'string', 'max:10000'],
            'decision_content' => ['sometimes', 'required', 'string', 'max:20000'],
        ];
    }
}
