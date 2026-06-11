<?php

namespace App\Http\Requests;

use App\Enums\EvidenceClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
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
            'evidence_type_code' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'source' => ['nullable', 'string', 'max:10000'],
            'collected_at' => ['nullable', 'date', 'before_or_equal:now'],
            'classification' => ['nullable', 'string', Rule::in(EvidenceClassification::values())],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'checksum_sha256' => ['nullable', 'string', 'size:64'],
            'file' => ['prohibited'],
            'storage_disk' => ['prohibited'],
            'storage_path' => ['prohibited'],
        ];
    }
}
