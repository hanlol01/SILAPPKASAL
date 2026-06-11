<?php

namespace App\Http\Requests;

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
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'granularity' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateTo = $this->dateTo();
            $dateFrom = $this->dateFrom($dateTo);

            if ($dateFrom->greaterThan($dateTo)) {
                $validator->errors()->add('date_from', 'The date_from must be before or equal to date_to.');

                return;
            }

            if ($dateFrom->diffInDays($dateTo) > 366) {
                $validator->errors()->add('date_from', 'The dashboard date range may not be greater than 366 days.');
            }
        });
    }

    /**
     * @return array{date_from: CarbonImmutable, date_to: CarbonImmutable, granularity: string}
     */
    public function dashboardFilters(): array
    {
        $dateTo = $this->dateTo();

        return [
            'date_from' => $this->dateFrom($dateTo)->startOfDay(),
            'date_to' => $dateTo->endOfDay(),
            'granularity' => $this->validated('granularity', 'day'),
        ];
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
