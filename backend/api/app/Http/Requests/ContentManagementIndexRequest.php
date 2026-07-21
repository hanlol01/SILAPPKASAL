<?php

namespace App\Http\Requests;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentManagementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'content_type' => ['nullable', Rule::enum(ContentType::class)],
            'lifecycle_status' => ['nullable', Rule::enum(ContentLifecycleStatus::class)],
            'category' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
