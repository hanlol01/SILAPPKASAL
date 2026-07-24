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
            'decision_number' => ['prohibited'],
            'decision_code' => ['prohibited'],
            'formal_decision_code' => ['prohibited'],
            'decisionNumber' => ['prohibited'],
            'decisionCode' => ['prohibited'],
            'formalDecisionCode' => ['prohibited'],
            'sequence' => ['prohibited'],
            'decision_sequence' => ['prohibited'],
            'sequence_number' => ['prohibited'],
            'year' => ['prohibited'],
            'decision_year' => ['prohibited'],
            'nomor_keputusan' => ['prohibited'],
            'nomor_putusan' => ['prohibited'],
            'nomor_sk' => ['prohibited'],
            'kode_keputusan' => ['prohibited'],
            'kode_putusan' => ['prohibited'],
            'decision_no' => ['prohibited'],
            'decision_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'decision_summary' => ['sometimes', 'required', 'string', 'max:10000'],
            'decision_content' => ['sometimes', 'required', 'string', 'max:20000'],
        ];
    }
}
