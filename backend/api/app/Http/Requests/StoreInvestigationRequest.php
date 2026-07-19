<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestigationRequest extends FormRequest
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
            'lead_investigator_id' => ['prohibited'],
            'plan_summary' => ['required', 'string', 'min:50', 'max:5000'],
        ];
    }
}
