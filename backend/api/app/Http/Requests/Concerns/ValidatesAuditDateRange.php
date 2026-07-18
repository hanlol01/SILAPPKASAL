<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

trait ValidatesAuditDateRange
{
    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$from, $to] = $this->resolvedAuditDateRange();

            if ($from->diffInDays($to) > 89) {
                $validator->errors()->add('date_to', __('audit.validation.date_range_max'));
            }
        }];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function resolvedAuditDateRange(): array
    {
        $timezone = (string) config('audit.oversight.timezone', 'Asia/Jakarta');
        $to = $this->filled('date_to')
            ? CarbonImmutable::parse((string) $this->input('date_to'), $timezone)
            : CarbonImmutable::now($timezone);
        $from = $this->filled('date_from')
            ? CarbonImmutable::parse((string) $this->input('date_from'), $timezone)
            : $to->subDays(29);

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
