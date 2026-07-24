<?php

namespace App\Http\Requests;

use App\Enums\ReportStatus;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $satgasRoleId = Role::query()->where('code', 'satgas_ppks')->value('id');

        return [
            'status' => ['sometimes', 'string', Rule::in(ReportStatus::values())],
            'category_code' => ['sometimes', 'string', 'exists:report_categories,code'],
            'report_type' => ['sometimes', 'string', Rule::in(['open', 'confidential', 'anonymous'])],
            'satgas_id' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(
                    fn (): bool => ! $actor?->hasRole('admin') || $actor->university_id === null
                ),
                Rule::prohibitedIf(fn (): bool => $this->filled('assignment_status')),
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role_id', $satgasRoleId ?? 0)
                    ->where('university_id', $actor?->university_id)
                    ->where('is_active', true)),
            ],
            'assignment_status' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(
                    fn (): bool => ! $actor?->hasRole('admin') || $actor->university_id === null
                ),
                Rule::prohibitedIf(fn (): bool => $this->filled('satgas_id')),
                'string',
                Rule::in(['unassigned']),
            ],
            'university_id' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(fn (): bool => ! $actor?->hasRole('super_admin')),
                'integer',
                Rule::exists('universities', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
