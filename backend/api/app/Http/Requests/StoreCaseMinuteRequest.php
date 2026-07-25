<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseMinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'internal_summary' => ['nullable', 'string', 'max:10000'],
            'anonymized_summary' => ['nullable', 'string', 'max:10000'],
            'outcome' => ['nullable', 'string', 'max:10000'],
            'follow_up' => ['nullable', 'string', 'max:10000'],
            ...$this->serverManagedFields(),
        ];
    }

    /** @return array<string, list<string>> */
    protected function serverManagedFields(): array
    {
        return array_fill_keys([
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
