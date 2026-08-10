<?php

namespace App\Http\Requests;

class ContentGovernanceReasonRequest extends ContentGovernanceActionRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
