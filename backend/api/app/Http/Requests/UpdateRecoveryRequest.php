<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecoveryRequest extends FormRequest
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
            'recovery_type_code' => ['sometimes', 'required', 'string', 'max:20'],
            'recovery_plan' => ['sometimes', 'required', 'string', 'max:10000'],
            'support_needs' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
