<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class StoreReportEvidenceSubmissionRequest extends FormRequest
{
    /** @var array<string, list<string>> */
    private const EXTENSIONS_BY_MIME = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->is_active
            && $user->hasRole('reporter')
            && $user->hasPermission('reporter_evidence.upload.own');
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
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $fail('The supporting file is invalid.');

                        return;
                    }

                    $size = $value->getSize();
                    $name = basename(str_replace('\\', '/', $value->getClientOriginalName()));
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $stem = pathinfo($name, PATHINFO_FILENAME);
                    $mimeType = $value->getMimeType();

                    if ($size === false || $size < 1) {
                        $fail('The supporting file must not be empty.');

                        return;
                    }

                    if ($stem === '' || substr_count($name, '.') !== 1) {
                        $fail('The supporting file name must contain only one extension.');

                        return;
                    }

                    if (! is_string($mimeType) || ! in_array($extension, self::EXTENSIONS_BY_MIME[$mimeType] ?? [], true)) {
                        $fail('The supporting file content and extension do not match an allowed format.');
                    }
                },
            ],
        ];
    }
}
