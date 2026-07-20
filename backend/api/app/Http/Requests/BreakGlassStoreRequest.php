<?php

namespace App\Http\Requests;

use App\Models\BreakGlassRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BreakGlassStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['reason_category', 'reason'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim(strip_tags($value));
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'case_id' => ['required', 'integer', Rule::exists('cases', 'id')],
            'reason_category' => ['required', 'string', Rule::in(BreakGlassRequest::REASON_CATEGORIES)],
            'reason' => ['required', 'string', 'min:50', 'max:2000'],
            'requested_duration_minutes' => ['required', 'integer', Rule::in(BreakGlassRequest::ALLOWED_DURATIONS)],
            'acknowledgment' => ['required', 'accepted'],
        ];
    }
}
