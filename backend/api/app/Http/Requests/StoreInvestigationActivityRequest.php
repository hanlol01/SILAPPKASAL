<?php

namespace App\Http\Requests;

use App\Enums\InvestigationActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestigationActivityRequest extends FormRequest
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
            'activity_type' => ['required', 'string', 'max:50', Rule::in(InvestigationActivityType::values())],
            'activity_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:10000'],
            'findings' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'investigation_stage_code' => ['prohibited'],
        ];
    }
}
