<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReportWithdrawalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rejection_reason'))) {
            $normalized = preg_replace(
                '/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u',
                '',
                $this->input('rejection_reason'),
            );
            $this->merge(['rejection_reason' => is_string($normalized) ? $normalized : '']);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->is_active
            && $user->hasRole('admin')
            && $user->university_id !== null
            && $user->hasPermission('reports.withdraw.review.own_campus');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'rejection_reason' => ['required', 'string', 'min:20', 'max:2000'],
            'resubmission_allowed' => ['required', 'boolean'],
        ];
    }
}
