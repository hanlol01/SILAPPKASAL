<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeCaseMinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'string', 'max:128'],
            'occurred_at' => ['prohibited'],
            'internal_summary' => ['prohibited'],
            'anonymized_summary' => ['prohibited'],
            'outcome' => ['prohibited'],
            'follow_up' => ['prohibited'],
            'id' => ['prohibited'],
            'public_id' => ['prohibited'],
            'case_id' => ['prohibited'],
            'version' => ['prohibited'],
            'status' => ['prohibited'],
            'supersedes_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'finalized_by' => ['prohibited'],
            'finalized_at' => ['prohibited'],
        ];
    }
}
