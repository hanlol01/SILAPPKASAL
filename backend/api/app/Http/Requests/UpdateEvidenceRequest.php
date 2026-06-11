<?php

namespace App\Http\Requests;

use App\Enums\EvidenceClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvidenceRequest extends FormRequest
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
            'evidence_type_code' => ['sometimes', 'required', 'string', 'max:20'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'source' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'collected_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
            'classification' => ['sometimes', 'nullable', 'string', Rule::in(EvidenceClassification::values())],
            'original_filename' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mime_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file_size' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'checksum_sha256' => ['sometimes', 'nullable', 'string', 'size:64'],
            'file' => ['prohibited'],
            'storage_disk' => ['prohibited'],
            'storage_path' => ['prohibited'],
        ];
    }
}
