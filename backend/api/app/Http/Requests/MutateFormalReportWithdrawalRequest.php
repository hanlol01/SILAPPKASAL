<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MutateFormalReportWithdrawalRequest extends FormRequest
{
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
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
