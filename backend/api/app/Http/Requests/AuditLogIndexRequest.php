<?php

namespace App\Http\Requests;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
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
            'category' => ['nullable', 'string', Rule::in(AuditCategory::values())],
            'severity' => ['nullable', 'string', Rule::in(AuditSeverity::values())],
            'action' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
