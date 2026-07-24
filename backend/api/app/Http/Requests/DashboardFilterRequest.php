<?php

namespace App\Http\Requests;

use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $satgasRoleId = Role::query()->where('code', 'satgas_ppks')->value('id');

        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'granularity' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
            'satgas_id' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(fn (): bool => ! $actor?->hasRole('admin')),
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($actor, $satgasRoleId) {
                    if ($actor?->university_id === null) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query
                        ->where('role_id', $satgasRoleId ?? 0)
                        ->where('university_id', $actor->university_id)
                        ->where('is_active', true);
                }),
            ],
            'assignment_status' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(
                    fn (): bool => ! $actor?->hasRole('admin') || $actor->university_id === null
                ),
                'string',
                Rule::in(['unassigned']),
            ],
            'university_id' => [
                'sometimes',
                'bail',
                Rule::prohibitedIf(fn (): bool => ! $actor?->hasRole('super_admin')),
                'integer',
                Rule::exists('universities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('satgas_id') && $this->filled('assignment_status')) {
                $validator->errors()->add('assignment_status', __('validation.prohibited'));
            }

            $dateTo = $this->dateTo();
            $dateFrom = $this->dateFrom($dateTo);

            if ($dateFrom->greaterThan($dateTo)) {
                $validator->errors()->add('date_from', __('validation.custom.date_from.before_or_equal_date_to'));

                return;
            }

            if ($dateFrom->diffInDays($dateTo) > 366) {
                $validator->errors()->add('date_from', __('validation.custom.date_from.max_dashboard_range'));
            }
        });
    }

    /**
     * @return array{
     *     date_from: CarbonImmutable,
     *     date_to: CarbonImmutable,
     *     granularity: string,
     *     satgas_id?: int,
     *     assignment_status?: string,
     *     university_id?: int
     * }
     */
    public function dashboardFilters(): array
    {
        $dateTo = $this->dateTo();

        $filters = [
            'date_from' => $this->dateFrom($dateTo)->startOfDay(),
            'date_to' => $dateTo->endOfDay(),
            'granularity' => $this->validated('granularity', 'day'),
        ];

        foreach (['satgas_id', 'assignment_status', 'university_id'] as $filter) {
            if ($this->filled($filter)) {
                $filters[$filter] = in_array($filter, ['satgas_id', 'university_id'], true)
                    ? (int) $this->validated($filter)
                    : $this->validated($filter);
            }
        }

        return $filters;
    }

    private function dateTo(): CarbonImmutable
    {
        return $this->filled('date_to')
            ? CarbonImmutable::parse($this->validated('date_to'))
            : CarbonImmutable::today();
    }

    private function dateFrom(CarbonImmutable $dateTo): CarbonImmutable
    {
        return $this->filled('date_from')
            ? CarbonImmutable::parse($this->validated('date_from'))
            : $dateTo->subDays(29);
    }
}
