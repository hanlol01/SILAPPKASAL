<?php

namespace App\Services;

use App\Contracts\ContentImageProcessor;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Models\ContentAttachment;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\User;
use App\Policies\ContentItemPolicy;
use App\Support\ApiErrorCode;
use App\Support\ContentAttachmentFilename;
use App\Support\ContentMediaManifest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ContentAttachmentService
{
    /** @var array<string, list<string>> */
    private const MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
    ];

    public function __construct(
        private readonly ContentItemPolicy $policy,
        private readonly AuditLogService $auditLogs,
        private readonly ContentImageProcessor $imageProcessor,
    ) {}

    public function imageUploadsAvailable(): bool
    {
        return (bool) config('content.attachments.image_uploads_enabled', false)
            && $this->imageProcessor->isAvailable()
            && $this->imageProcessor->supportedMimeTypes() !== [];
    }

    /** @return list<string> */
    public function supportedImageMimeTypes(): array
    {
        if (! $this->imageUploadsAvailable()) {
            return [];
        }

        return $this->imageProcessor->supportedMimeTypes();
    }

    /** @param array<string, mixed> $data */
    public function upload(ContentVersion $version, User $actor, UploadedFile $file, array $data): ContentAttachment
    {
        $purpose = ContentAttachmentPurpose::from((string) $data['purpose']);
        if (in_array($purpose, [ContentAttachmentPurpose::Cover, ContentAttachmentPurpose::InlineImage], true)) {
            $altText = trim((string) ($data['alt_text'] ?? ''));
            $maxAltLength = (int) config('content.attachments.alt_text_max_length', 500);
            if ($altText === '' || mb_strlen($altText) > $maxAltLength) {
                throw ValidationException::withMessages([
                    'alt_text' => ["Alternative text is required and may not exceed {$maxAltLength} characters."],
                ]);
            }
            $data['alt_text'] = $altText;
        }

        $originalFilename = mb_substr($file->getClientOriginalName(), 0, 255);
        $metadata = $this->validateFile($file, $purpose);
        $publicId = (string) Str::uuid();
        $safeFilename = ContentAttachmentFilename::make($publicId, $purpose, $metadata['mime']);
        $path = $version->public_id.'/'.$publicId.'.'.$metadata['extension'];
        $stored = false;

        try {
            return DB::transaction(function () use ($version, $actor, $data, $purpose, $metadata, $publicId, $safeFilename, $originalFilename, $path, &$stored): ContentAttachment {
                $actor = User::query()->with('role.permissions')->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $version = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
                $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->firstOrFail();

                $this->ensureNotArchived($item);

                if (! $this->policy->manageAttachment($actor, $item, $version)) {
                    throw $this->forbidden();
                }

                if (in_array($purpose, [ContentAttachmentPurpose::Cover, ContentAttachmentPurpose::InlineImage], true)
                    && $item->content_type->value !== 'article') {
                    throw ValidationException::withMessages([
                        'purpose' => ['Only Article versions may have cover or inline images.'],
                    ]);
                }

                $stored = Storage::disk('content')->putFileAs(
                    dirname($path),
                    $metadata['file'],
                    basename($path),
                    ['visibility' => 'private'],
                );
                if ($stored === false) {
                    throw ValidationException::withMessages(['file' => ['The private attachment could not be stored.']]);
                }
                $stored = true;

                $attachment = ContentAttachment::query()->create([
                    'public_id' => $publicId,
                    'content_version_id' => $version->id,
                    'purpose' => $purpose,
                    'storage_disk' => 'content',
                    'storage_path' => $path,
                    'safe_filename' => $safeFilename,
                    'original_filename' => $originalFilename,
                    'detected_mime' => $metadata['mime'],
                    'extension' => $metadata['extension'],
                    'file_size' => $metadata['size'],
                    'checksum_sha256' => $metadata['checksum'],
                    'width' => $metadata['width'],
                    'height' => $metadata['height'],
                    'alt_text' => $data['alt_text'] ?? null,
                    'display_order' => (int) ($data['display_order'] ?? 0),
                    'uploader_id' => $actor->id,
                ]);

                if ($purpose === ContentAttachmentPurpose::Cover) {
                    $article = $version->articleContent()->lockForUpdate()->firstOrFail();
                    $article->forceFill([
                        'cover_attachment_id' => $attachment->id,
                        'cover_alt_text' => $data['alt_text'] ?? $article->cover_alt_text,
                    ])->save();
                }

                $this->auditLogs->record(
                    action: AuditAction::ContentAttachmentUploaded,
                    category: AuditCategory::Content,
                    severity: AuditSeverity::Info,
                    actor: $actor,
                    subject: $item,
                    metadata: [
                        'content_public_id' => $item->public_id,
                        'version_number' => $version->version_number,
                        'content_type' => $item->content_type->value,
                        'scope' => $item->scope->value,
                        'university_code' => $item->university()->value('code'),
                        'attachment_public_id' => $attachment->public_id,
                        'purpose' => $purpose->value,
                    ],
                );

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk('content')->delete($path);
            }
            throw $exception;
        } finally {
            if ($metadata['processed']) {
                $this->imageProcessor->release($metadata['file']);
            }
        }
    }

    public function download(ContentAttachment $attachment, User $actor): StreamedResponse
    {
        $attachment->loadMissing('version.item', 'version.articleContent.coverAttachment');
        $item = $attachment->version->item;
        $actor->loadMissing('role.permissions');

        $activeReference = ContentMediaManifest::isActiveReference($attachment);
        $management = $activeReference
            && $this->policy->viewEditableAttachment($actor, $item, $attachment->version);
        $governance = $activeReference
            && $this->policy->viewGovernanceAttachment($actor, $item, $attachment->version);
        $published = $this->policy->viewPublished($actor)
            && $item->archived_at === null
            && (int) $item->published_version_id === (int) $attachment->content_version_id
            && $attachment->version->lifecycle_status === ContentLifecycleStatus::Published
            && $this->inReaderScope($item, $actor)
            && $activeReference;

        if (! $management && ! $governance && ! $published) {
            abort(404);
        }

        if ($attachment->storage_disk !== 'content') {
            abort(404);
        }

        $disk = Storage::disk('content');
        if (! $disk->exists($attachment->storage_path)) {
            abort(404);
        }

        $response = $disk->download($attachment->storage_path, ContentAttachmentFilename::for($attachment), [
            'Content-Type' => $attachment->detected_mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $this->auditLogs->record(
            action: AuditAction::ContentAttachmentDownloadAuthorized,
            category: AuditCategory::Content,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $item,
            metadata: [
                'content_public_id' => $item->public_id,
                'version_number' => $attachment->version->version_number,
                'content_type' => $item->content_type->value,
                'scope' => $item->scope->value,
                'university_code' => $item->university()->value('code'),
                'attachment_public_id' => $attachment->public_id,
                'purpose' => $attachment->purpose->value,
            ],
        );

        return $response;
    }

    public function remove(ContentAttachment $attachment, User $actor): void
    {
        $diskName = null;
        $path = null;
        $backup = null;
        $physicalFileRemoved = false;

        try {
            DB::transaction(function () use (
                $attachment,
                $actor,
                &$diskName,
                &$path,
                &$backup,
                &$physicalFileRemoved,
            ): void {
                $actor = User::query()->with('role.permissions')->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $attachment = ContentAttachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
                $version = ContentVersion::query()->whereKey($attachment->content_version_id)->lockForUpdate()->firstOrFail();
                $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->firstOrFail();

                $this->ensureNotArchived($item);
                if (! $this->policy->manageAttachment($actor, $item, $version)) {
                    throw $this->forbidden();
                }

                $article = null;
                if ($attachment->purpose !== ContentAttachmentPurpose::Attachment) {
                    $article = $version->articleContent()->lockForUpdate()->firstOrFail();
                }
                if ($attachment->purpose === ContentAttachmentPurpose::InlineImage
                    && $this->documentReferencesAttachment($article?->document_json, $attachment->public_id)) {
                    throw ValidationException::withMessages([
                        'attachment' => ['Remove the inline image from the Article body and save the draft before deleting its media.'],
                    ]);
                }
                if ($attachment->purpose === ContentAttachmentPurpose::Cover
                    && (int) $article?->cover_attachment_id === (int) $attachment->id) {
                    $article->forceFill([
                        'cover_attachment_id' => null,
                        'cover_alt_text' => null,
                    ])->save();
                }

                $diskName = $attachment->storage_disk;
                $path = $attachment->storage_path;
                try {
                    $disk = Storage::disk($diskName);
                    $exists = $disk->exists($path);
                    $backup = $exists ? $disk->get($path) : null;
                } catch (Throwable) {
                    throw $this->deletionFailed();
                }
                if (! $exists || ! is_string($backup)) {
                    throw $this->deletionFailed();
                }

                try {
                    $deleted = $disk->delete($path);
                    $fileStillExists = $disk->exists($path);
                } catch (Throwable) {
                    $physicalFileRemoved = true;
                    throw $this->deletionFailed();
                }
                $physicalFileRemoved = ! $fileStillExists;
                if (! $deleted || $fileStillExists) {
                    throw $this->deletionFailed();
                }

                $publicId = $attachment->public_id;
                $purpose = $attachment->purpose->value;
                $attachment->delete();

                $this->auditLogs->record(
                    action: AuditAction::ContentAttachmentRemoved,
                    category: AuditCategory::Content,
                    severity: AuditSeverity::Info,
                    actor: $actor,
                    subject: $item,
                    metadata: [
                        'content_public_id' => $item->public_id,
                        'version_number' => $version->version_number,
                        'content_type' => $item->content_type->value,
                        'scope' => $item->scope->value,
                        'university_code' => $item->university()->value('code'),
                        'attachment_public_id' => $publicId,
                        'purpose' => $purpose,
                    ],
                );
            });
        } catch (Throwable $exception) {
            if ($physicalFileRemoved && is_string($diskName) && is_string($path) && is_string($backup)) {
                try {
                    $disk = Storage::disk($diskName);
                    if (! $disk->exists($path)) {
                        $disk->put($path, $backup, ['visibility' => 'private']);
                    }
                } catch (Throwable) {
                    // The database transaction is still rolled back; storage recovery is best-effort.
                }
            }

            throw $exception;
        }
    }

    /**
     * @return array{
     *     file: UploadedFile,
     *     processed: bool,
     *     mime: string,
     *     extension: string,
     *     size: int,
     *     checksum: string,
     *     width: ?int,
     *     height: ?int
     * }
     */
    private function validateFile(UploadedFile $file, ContentAttachmentPurpose $purpose): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => ['The uploaded attachment is invalid.']]);
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $imageAttempt = str_starts_with($mime, 'image/')
            || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
        $processed = false;
        $maxBytes = match ($purpose) {
            ContentAttachmentPurpose::Cover => (int) config('content.attachments.cover_max_bytes', 5 * 1024 * 1024),
            ContentAttachmentPurpose::InlineImage => (int) config('content.attachments.inline_image_max_bytes', 10 * 1024 * 1024),
            ContentAttachmentPurpose::Attachment => (int) config('content.attachments.attachment_max_bytes', 10 * 1024 * 1024),
        };
        $sourceSize = (int) $file->getSize();

        $sourceMaxBytes = $imageAttempt
            ? (int) config('content.attachments.max_image_source_bytes', $maxBytes)
            : $maxBytes;
        if ($sourceSize < 1 || $sourceSize > $sourceMaxBytes) {
            throw ValidationException::withMessages(['file' => [
                $imageAttempt
                    ? 'The source image exceeds the processing size limit.'
                    : 'The attachment size is outside the allowed limit.',
            ]]);
        }
        if ($purpose === ContentAttachmentPurpose::Attachment && $imageAttempt) {
            throw ValidationException::withMessages([
                'file' => ['General Article attachments must be PDF documents. Use cover or inline image upload for images.'],
            ]);
        }

        if ($imageAttempt) {
            if (! $this->imageUploadsAvailable()) {
                throw ValidationException::withMessages([
                    'file' => ['Image uploads are disabled because a verified metadata-stripping re-encoder is unavailable.'],
                ]);
            }

            $file = $this->imageProcessor->reencode($file);
            $processed = true;
            if (! $file->isValid()) {
                throw ValidationException::withMessages(['file' => ['The safely processed image is invalid.']]);
            }
            $extension = mb_strtolower($file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();
        }

        try {
            $path = $file->getRealPath();
            $size = (int) $file->getSize();
            $allowedExtensions = $purpose === ContentAttachmentPurpose::Attachment
                ? ['pdf']
                : ($processed ? ['jpg', 'png', 'webp'] : []);

            if ($size < 1 || $size > $maxBytes) {
                throw ValidationException::withMessages(['file' => [
                    $imageAttempt
                        ? 'The optimized image exceeds the allowed storage limit.'
                        : 'The attachment size is outside the allowed limit.',
                ]]);
            }
            if (! in_array($extension, $allowedExtensions, true) || ! in_array($mime, self::MIME_BY_EXTENSION[$extension] ?? [], true)) {
                throw ValidationException::withMessages(['file' => ['The attachment extension and detected MIME type do not match an allowed format.']]);
            }

            $width = null;
            $height = null;
            if (str_starts_with($mime, 'image/')) {
                $dimensions = @getimagesize($path);
                if ($dimensions === false) {
                    throw ValidationException::withMessages(['file' => ['The uploaded image signature is invalid.']]);
                }
                [$width, $height] = [(int) $dimensions[0], (int) $dimensions[1]];
                $maxDimension = (int) config('content.attachments.max_image_dimension', 6000);
                $maxPixels = (int) config('content.attachments.max_image_pixels', 24_000_000);
                if ($width < 1
                    || $height < 1
                    || $width > $maxDimension
                    || $height > $maxDimension
                    || ($width * $height) > $maxPixels) {
                    throw ValidationException::withMessages(['file' => ['The image dimensions exceed the safe limit.']]);
                }
            } elseif (! $this->hasValidPdfEnvelope($path, $size)) {
                throw ValidationException::withMessages(['file' => ['The uploaded PDF signature is invalid.']]);
            }

            return [
                'file' => $file,
                'processed' => $processed,
                'mime' => $mime,
                'extension' => $extension,
                'size' => $size,
                'checksum' => hash_file('sha256', $path),
                'width' => $width,
                'height' => $height,
            ];
        } catch (Throwable $exception) {
            if ($processed) {
                $this->imageProcessor->release($file);
            }

            throw $exception;
        }
    }

    private function inReaderScope(ContentItem $item, User $actor): bool
    {
        if ($this->canReadAllPublishedContent($actor)) {
            return true;
        }

        return $item->scope === ContentScope::Global
            || ($item->scope === ContentScope::Campus
                && $actor->university_id !== null
                && (int) $item->university_id === (int) $actor->university_id);
    }

    private function canReadAllPublishedContent(User $actor): bool
    {
        return $actor->hasRole('super_admin')
            && $actor->hasPermission('content.read.management.all');
    }

    private function hasValidPdfEnvelope(string $path, int $size): bool
    {
        $header = file_get_contents($path, false, null, 0, min(16, $size));
        if (! is_string($header)
            || preg_match('/^%PDF-(?:1\.[0-7]|2\.0)(?:\r\n|\r|\n)/', $header) !== 1) {
            return false;
        }

        $tailLength = min(4096, $size);
        $tail = file_get_contents($path, false, null, max(0, $size - $tailLength), $tailLength);
        if (! is_string($tail)) {
            return false;
        }

        $eof = strrpos($tail, '%%EOF');
        if ($eof === false) {
            return false;
        }

        return trim(substr($tail, $eof + 5)) === '';
    }

    /** @param array<string, mixed>|null $document */
    private function documentReferencesAttachment(?array $document, string $publicId): bool
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

    private function ensureNotArchived(ContentItem $item): void
    {
        if ($item->archived_at !== null) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Archived content is read-only and cannot be changed.',
                'error_code' => ApiErrorCode::ContentArchived,
                'errors' => null,
            ], 409)->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
            ]));
        }
    }

    private function deletionFailed(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The private attachment could not be removed. Try again later.',
            'error_code' => ApiErrorCode::ContentAttachmentDeletionFailed,
            'errors' => null,
        ], 503)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]));
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this content attachment',
            'errors' => null,
        ], 403));
    }
}
