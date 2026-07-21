<?php

namespace App\Http\Requests;

class ContentGovernanceApprovalRequest extends ContentGovernanceActionRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
