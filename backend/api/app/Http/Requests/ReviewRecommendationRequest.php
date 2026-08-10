<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRecommendationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('revision_note') && is_string($this->input('revision_note'))) {
            $this->merge(['revision_note' => trim($this->input('revision_note'))]);
        }
    }

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
            'action' => ['required', 'string', Rule::in(['approve', 'return_for_revision'])],
            'revision_note' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn (): bool => $this->input('action') === 'return_for_revision'),
                Rule::prohibitedIf(fn (): bool => $this->input('action') === 'approve'),
            ],
        ];
    }
}
