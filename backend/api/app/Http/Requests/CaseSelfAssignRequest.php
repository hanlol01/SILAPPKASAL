<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaseSelfAssignRequest extends FormRequest
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
            'lock_version' => ['required', 'string', 'regex:/\\A[a-f0-9]{64}\\z/'],
            'satgas_id' => ['prohibited'],
            'satgas_ids' => ['prohibited'],
            'assignee_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'lead_satgas_id' => ['prohibited'],
        ];
    }
}
