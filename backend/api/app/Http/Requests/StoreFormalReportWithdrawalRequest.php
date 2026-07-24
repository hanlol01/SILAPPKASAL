<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormalReportWithdrawalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (! is_string($reason)) {
            return;
        }

        $normalized = preg_replace('/\A[\p{Z}\s]+|[\p{Z}\s]+\z/u', '', $reason);

        if ($normalized !== null) {
            $this->merge(['reason' => $normalized]);
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('api.validation.withdrawal_reason_required'),
            'reason.string' => __('api.validation.withdrawal_reason_string'),
            'reason.min' => __('api.validation.withdrawal_reason_min'),
            'reason.max' => __('api.validation.withdrawal_reason_max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('api.validation.withdrawal_reason_attribute'),
        ];
    }
}
