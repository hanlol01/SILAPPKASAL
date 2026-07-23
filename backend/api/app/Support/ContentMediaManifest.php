<?php

namespace App\Support;

use App\Enums\ContentAttachmentPurpose;
use App\Models\ContentAttachment;
use App\Models\ContentVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ContentMediaManifest
{
    /** @return Collection<int, ContentAttachment> */
    public static function attachments(ContentVersion $version): Collection
    {
        $version->loadMissing(['attachments', 'articleContent.coverAttachment']);

        return $version->attachments
            ->filter(fn (ContentAttachment $attachment): bool => self::isActiveReference(
                $attachment,
                $version,
            ))
            ->sortBy(fn (ContentAttachment $attachment): string => implode(':', [
                str_pad((string) self::purposeRank($attachment->purpose), 2, '0', STR_PAD_LEFT),
                str_pad((string) $attachment->display_order, 5, '0', STR_PAD_LEFT),
                $attachment->public_id,
            ]))
            ->values();
    }

    public static function cover(ContentVersion $version): ?ContentAttachment
    {
        return self::attachments($version)
            ->first(fn (ContentAttachment $attachment): bool => $attachment->purpose === ContentAttachmentPurpose::Cover);
    }

    /** @return Collection<int, ContentAttachment> */
    public static function forPurpose(ContentVersion $version, ContentAttachmentPurpose $purpose): Collection
    {
        return self::attachments($version)
            ->filter(fn (ContentAttachment $attachment): bool => $attachment->purpose === $purpose)
            ->values();
    }

    public static function isActiveReference(
        ContentAttachment $attachment,
        ?ContentVersion $version = null,
    ): bool {
        $version ??= $attachment->relationLoaded('version')
            ? $attachment->version
            : $attachment->version()->first();
        if ($version === null || (int) $attachment->content_version_id !== (int) $version->id) {
            return false;
        }

        if (! self::isReady($attachment)) {
            return false;
        }

        if ($attachment->purpose === ContentAttachmentPurpose::Attachment) {
            return $attachment->detected_mime === 'application/pdf'
                && $attachment->extension === 'pdf';
        }

        $version->loadMissing('articleContent.coverAttachment');
        $article = $version->articleContent;
        if ($article === null) {
            return false;
        }

        if ($attachment->purpose === ContentAttachmentPurpose::Cover) {
            return self::isSupportedImage($attachment)
                && (int) $article->cover_attachment_id === (int) $attachment->id;
        }

        return $attachment->purpose === ContentAttachmentPurpose::InlineImage
            && self::isSupportedImage($attachment)
            && self::documentReferencesAttachment($article->document_json, $attachment->public_id);
    }

    private static function isReady(ContentAttachment $attachment): bool
    {
        if ($attachment->storage_disk !== 'content'
            || $attachment->storage_path === ''
            || $attachment->file_size < 1) {
            return false;
        }

        try {
            return Storage::disk('content')->exists($attachment->storage_path);
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed>|null $document */
    private static function documentReferencesAttachment(?array $document, string $publicId): bool
    {
        if ($document === null) {
            return false;
        }

        $stack = [$document];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'imageReference'
                && ($node['attrs']['attachment_public_id'] ?? null) === $publicId) {
                return true;
            }
            foreach ($node['content'] ?? [] as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }

        return false;
    }

    private static function purposeRank(ContentAttachmentPurpose $purpose): int
    {
        return match ($purpose) {
            ContentAttachmentPurpose::Cover => 1,
            ContentAttachmentPurpose::InlineImage => 2,
            ContentAttachmentPurpose::Attachment => 3,
        };
    }

    private static function isSupportedImage(ContentAttachment $attachment): bool
    {
        return match ($attachment->extension) {
            'jpg', 'jpeg' => $attachment->detected_mime === 'image/jpeg',
            'png' => $attachment->detected_mime === 'image/png',
            'webp' => $attachment->detected_mime === 'image/webp',
            default => false,
        };
    }
}
