<?php

namespace App\Http\Requests;

use App\Enums\ContentAttachmentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(ContentAttachmentPurpose::class)],
            'file' => ['required', 'file', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
