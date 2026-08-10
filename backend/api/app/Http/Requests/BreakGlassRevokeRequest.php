<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BreakGlassRevokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('revocation_reason');

        if (is_string($reason)) {
            $this->merge(['revocation_reason' => trim(strip_tags($reason))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'revocation_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
