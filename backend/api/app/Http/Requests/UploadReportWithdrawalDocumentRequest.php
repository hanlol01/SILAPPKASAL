<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class UploadReportWithdrawalDocumentRequest extends FormRequest
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
            && $user->hasPermission('reports.withdraw.own');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'file' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10240),
                'extensions:pdf,jpg,jpeg,png',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $fail(__('api.validation.withdrawal_document_invalid'));

                        return;
                    }

                    $size = $value->getSize();
                    $name = basename(str_replace('\\', '/', $value->getClientOriginalName()));
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $stem = pathinfo($name, PATHINFO_FILENAME);
                    $serverMime = $value->getMimeType();
                    $declaredMime = strtolower(trim($value->getClientMimeType()));

                    if ($size === false || $size < 1) {
                        $fail(__('api.validation.withdrawal_document_empty'));

                        return;
                    }

                    if ($stem === '' || substr_count($name, '.') !== 1) {
                        $fail(__('api.validation.withdrawal_document_filename'));

                        return;
                    }

                    if (! is_string($serverMime)
                        || ! isset(self::EXTENSIONS_BY_MIME[$serverMime])
                        || ! in_array($extension, self::EXTENSIONS_BY_MIME[$serverMime], true)
                        || $declaredMime !== $serverMime) {
                        $fail(__('api.validation.withdrawal_document_mismatch'));
                    }
                },
            ],
        ];
    }
}
