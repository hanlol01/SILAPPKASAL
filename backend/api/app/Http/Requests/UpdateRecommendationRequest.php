<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecommendationRequest extends FormRequest
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
            'conclusion' => ['sometimes', 'required', 'string', 'max:10000'],
            'recommended_actions' => ['sometimes', 'required', 'string', 'max:10000'],
            'sanction_recommendation' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'recovery_recommendation' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prevention_recommendation' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
