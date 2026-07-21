<?php

namespace App\Http\Requests;

use App\Enums\ContentScope;
use App\Enums\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'content_type' => ['required', Rule::enum(ContentType::class)],
            'section_code' => ['required', 'string', Rule::in(['education', 'policy', 'faq', 'consultation'])],
            'category_public_id' => ['nullable', 'uuid'],
            'scope' => ['required', Rule::enum(ContentScope::class)],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'requires_editorial_review' => ['sometimes', 'boolean'],
            'document' => ['nullable', 'array'],
            'consultation_cta_public_id' => ['nullable', 'uuid'],
            'answer_document' => ['nullable', 'array'],
            'question' => ['nullable', 'string', 'max:500'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            ...$this->consultationRules(),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function consultationRules(): array
    {
        return [
            'service_name' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone_display' => ['nullable', 'string', 'max:40'],
            'whatsapp_display' => ['nullable', 'string', 'max:40'],
            'office_address' => ['nullable', 'string', 'max:2000'],
            'operating_hours' => ['nullable', 'string', 'max:2000'],
            'emergency_available' => ['sometimes', 'boolean'],
            'appointment_url' => ['nullable', 'string', 'max:2048'],
            'action_label' => ['nullable', 'string', 'max:100'],
            'icon_code' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/i'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'verification_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'verified_owner' => ['nullable', 'string', 'max:200'],
        ];
    }
}
