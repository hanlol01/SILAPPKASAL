<?php

namespace App\Http\Requests;

use App\Enums\AuditActorKind;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Http\Requests\Concerns\ValidatesAuditDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    use ValidatesAuditDateRange;

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
            'category' => ['nullable', 'string', Rule::in(AuditCategory::values())],
            'severity' => ['nullable', 'string', Rule::in(AuditSeverity::values())],
            'action' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', 'string', Rule::in(AuditResult::values())],
            'actor_kind' => ['nullable', 'string', Rule::in(AuditActorKind::values())],
            'actor_role_code' => ['nullable', 'string', Rule::in(['super_admin', 'admin', 'satgas_ppks', 'reporter'])],
            'is_elevated_access' => ['nullable', 'boolean'],
            'request_id' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
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
