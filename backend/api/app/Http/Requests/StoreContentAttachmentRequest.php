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
        $purpose = ContentAttachmentPurpose::tryFrom((string) $this->input('purpose'));
        $maxBytes = match ($purpose) {
            ContentAttachmentPurpose::Cover => (int) config('content.attachments.cover_max_bytes'),
            ContentAttachmentPurpose::InlineImage => (int) config('content.attachments.inline_image_max_bytes'),
            ContentAttachmentPurpose::Attachment => (int) config('content.attachments.attachment_max_bytes'),
            default => max(
                (int) config('content.attachments.cover_max_bytes'),
                (int) config('content.attachments.inline_image_max_bytes'),
                (int) config('content.attachments.attachment_max_bytes'),
            ),
        };
        $maxKilobytes = max(1, (int) ceil($maxBytes / 1024));

        return [
            'purpose' => ['required', Rule::enum(ContentAttachmentPurpose::class)],
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
            'alt_text' => [
                'nullable',
                'string',
                'max:'.(int) config('content.attachments.alt_text_max_length', 500),
            ],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
