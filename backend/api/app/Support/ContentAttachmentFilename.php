<?php

namespace App\Support;

use App\Enums\ContentAttachmentPurpose;
use App\Models\ContentAttachment;
use Illuminate\Validation\ValidationException;

final class ContentAttachmentFilename
{
    /** @var array<string, string> */
    private const EXTENSION_BY_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function for(ContentAttachment $attachment): string
    {
        return self::make(
            (string) $attachment->public_id,
            $attachment->purpose,
            (string) $attachment->detected_mime,
        );
    }

    public static function make(
        string $publicId,
        ContentAttachmentPurpose|string $purpose,
        string $mime,
    ): string {
        $purpose = $purpose instanceof ContentAttachmentPurpose
            ? $purpose
            : ContentAttachmentPurpose::from($purpose);
        $extension = self::EXTENSION_BY_MIME[$mime] ?? null;

        if ($extension === null || preg_match('/^[0-9a-f-]{36}$/i', $publicId) !== 1) {
            throw ValidationException::withMessages([
                'file' => ['The attachment metadata cannot produce a safe download filename.'],
            ]);
        }

        $prefix = match ($purpose) {
            ContentAttachmentPurpose::Cover => 'cover',
            ContentAttachmentPurpose::InlineImage => 'gambar',
            ContentAttachmentPurpose::Attachment => 'lampiran',
        };

        return $prefix.'-'.mb_strtolower($publicId).'.'.$extension;
    }
}
