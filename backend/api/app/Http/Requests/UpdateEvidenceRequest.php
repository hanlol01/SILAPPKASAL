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
            'original_filename' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'file_size' => ['prohibited'],
            'checksum_sha256' => ['prohibited'],
            'file' => ['prohibited'],
            'storage_disk' => ['prohibited'],
            'storage_path' => ['prohibited'],
            'file_uploaded_by' => ['prohibited'],
            'file_uploaded_at' => ['prohibited'],
        ];
    }
}
