<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecommendationRequest extends FormRequest
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
            'investigation_id' => ['required', 'integer', 'exists:investigations,id'],
            'conclusion' => ['required', 'string', 'max:10000'],
            'recommended_actions' => ['required', 'string', 'max:10000'],
            'sanction_recommendation' => ['nullable', 'string', 'max:10000'],
            'recovery_recommendation' => ['nullable', 'string', 'max:10000'],
            'prevention_recommendation' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
