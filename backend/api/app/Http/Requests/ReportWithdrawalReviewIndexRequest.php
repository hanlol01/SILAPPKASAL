<?php

namespace App\Http\Requests;

use App\Enums\ReportWithdrawalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportWithdrawalReviewIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', ReportWithdrawalStatus::PendingReview->value),
        ]);

        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->is_active && (
            ($user->hasRole('admin')
                && $user->university_id !== null
                && $user->hasPermission('reports.withdraw.review.own_campus'))
            || ($user->hasRole('super_admin') && $user->hasPermission('reports.read.all'))
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                ReportWithdrawalStatus::PendingReview->value,
                ReportWithdrawalStatus::Approved->value,
                ReportWithdrawalStatus::Rejected->value,
                ReportWithdrawalStatus::Cancelled->value,
                'all',
            ])],
            'search' => ['sometimes', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
