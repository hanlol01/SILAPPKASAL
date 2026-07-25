<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCaseMinuteRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_fill_keys([
            'occurred_at',
            'internal_summary',
            'anonymized_summary',
            'outcome',
            'follow_up',
            'id',
            'public_id',
            'case_id',
            'version',
            'status',
            'supersedes_id',
            'created_by',
            'updated_by',
            'finalized_by',
            'finalized_at',
            'lock_version',
        ], ['prohibited']);
    }
}
