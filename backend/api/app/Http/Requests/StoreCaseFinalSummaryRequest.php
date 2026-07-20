<?php

namespace App\Http\Requests;

use App\Enums\CaseFinalOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseFinalSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'outcome_code' => ['required', 'string', Rule::enum(CaseFinalOutcome::class)],
            'completion_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'official_statement' => ['required', 'string', 'max:10000'],
            'investigation_summary' => ['nullable', 'string', 'max:10000'],
            'recommendation_result' => ['nullable', 'string', 'max:10000'],
            'decision_result' => ['nullable', 'string', 'max:10000'],
            'recovery_result' => ['nullable', 'string', 'max:10000'],
            'actions_completed' => ['nullable', 'string', 'max:10000'],
            'actions_uncompleted' => ['nullable', 'string', 'max:10000'],
            'follow_up_or_referral' => ['nullable', 'string', 'max:10000'],
            'closing_explanation' => ['required', 'string', 'max:10000'],
        ];
    }
}
