<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaseAssignRequest extends FormRequest
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
            'satgas_ids' => ['required', 'array', 'min:1'],
            'satgas_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'lock_version' => ['required', 'string', 'regex:/\\A[a-f0-9]{64}\\z/'],
            'lead_satgas_id' => ['prohibited'],
        ];
    }
}
