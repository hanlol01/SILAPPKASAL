<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BreakGlassDenyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $denialReason = $this->input('denial_reason');

        if (is_string($denialReason)) {
            $this->merge([
                'denial_reason' => trim(strip_tags($denialReason)),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'denial_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
