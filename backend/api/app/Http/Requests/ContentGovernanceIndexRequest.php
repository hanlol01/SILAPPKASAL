<?php

namespace App\Http\Requests;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentGovernanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'lifecycle_status' => ['nullable', Rule::in([
                ContentLifecycleStatus::Submitted->value,
                ContentLifecycleStatus::InReview->value,
                ContentLifecycleStatus::Approved->value,
            ])],
            'scope' => ['nullable', Rule::enum(ContentScope::class)],
            'content_type' => ['nullable', Rule::enum(ContentType::class)],
            'section' => ['nullable', 'string', Rule::in(['education', 'policy', 'faq', 'consultation'])],
            'category' => ['nullable', 'uuid'],
            'university_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'submitted_from' => ['nullable', 'date_format:Y-m-d'],
            'submitted_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:submitted_from'],
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
