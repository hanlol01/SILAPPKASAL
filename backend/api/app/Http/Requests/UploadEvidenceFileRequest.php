<?php

namespace App\Http\Requests;

use App\Models\Evidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class UploadEvidenceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $evidence = $this->route('evidence');

        return $evidence instanceof Evidence
            && $this->user()?->can('uploadFile', $evidence) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10240),
                'extensions:pdf,jpg,jpeg,png',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile || $value->getSize() === false || $value->getSize() < 1) {
                        $fail('The evidence file must not be empty.');
                    }
                },
            ],
        ];
    }
}
