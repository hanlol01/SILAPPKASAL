<?php

namespace App\Http\Requests;

use App\Services\OversightProjection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditOversightRequest extends FormRequest
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
            'queue' => ['nullable', 'string', Rule::in(OversightProjection::QUEUES)],
            'urgency' => ['nullable', 'string', Rule::in(OversightProjection::URGENCIES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cutoff' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function cutoff(): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'UTC');

        return $this->filled('cutoff')
            ? CarbonImmutable::parse((string) $this->validated('cutoff'))->setTimezone($timezone)
            : CarbonImmutable::now($timezone);
    }
}
