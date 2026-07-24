<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitFormalReportWithdrawalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $normalized = preg_replace(
                '/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u',
                '',
                $this->input('reason'),
            );

            if ($normalized !== null) {
                $this->merge(['reason' => $normalized]);
            }
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->is_active
            && $user->hasRole('reporter')
            && $user->hasPermission('reports.withdraw.own');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }
}
