<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveReportWithdrawalRequest extends FormRequest
{
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
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
