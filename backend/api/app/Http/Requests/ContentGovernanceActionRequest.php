<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentGovernanceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
