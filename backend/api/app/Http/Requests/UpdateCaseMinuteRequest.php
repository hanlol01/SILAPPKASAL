<?php

namespace App\Http\Requests;

class UpdateCaseMinuteRequest extends StoreCaseMinuteRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'string', 'max:128'],
            'occurred_at' => ['sometimes', 'required', 'date'],
            'internal_summary' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'anonymized_summary' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'outcome' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'follow_up' => ['sometimes', 'nullable', 'string', 'max:10000'],
            ...array_diff_key($this->serverManagedFields(), ['lock_version' => true]),
        ];
    }
}
