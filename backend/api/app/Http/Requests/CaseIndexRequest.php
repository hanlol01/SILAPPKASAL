<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaseIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'max:20'],
            'quick_filter' => ['sometimes', 'string', Rule::in(['active', 'pending_decision', 'with_evidence'])],
            'risk_level' => ['sometimes', 'string', 'max:20'],
            'priority' => ['sometimes', 'string', 'max:20'],
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
                    fn (): bool => ! ($actor?->hasRole('admin') || $actor?->hasRole('satgas_ppks'))
                        || $actor->university_id === null
                ),
                Rule::prohibitedIf(fn (): bool => $this->filled('satgas_id')),
                'string',
                Rule::in(['unassigned']),
            ],
            'university_id' => ['prohibited'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
