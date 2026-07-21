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
use App\Support\ContentAttachmentFilename;
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

    /** @param array<string, mixed> $data */
    public function upload(ContentVersion $version, User $actor, UploadedFile $file, array $data): ContentAttachment
    {
        $purpose = ContentAttachmentPurpose::from((string) $data['purpose']);
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
        $attachment->loadMissing('version.item');
        $item = $attachment->version->item;
        $actor->loadMissing('role.permissions');

        $management = $this->policy->viewManagement($actor, $item);
        $published = $this->policy->viewPublished($actor)
            && $item->archived_at === null
            && (int) $item->published_version_id === (int) $attachment->content_version_id
            && $attachment->version->lifecycle_status === ContentLifecycleStatus::Published
            && $this->inReaderScope($item, $actor);

        if (! $management && ! $published) {
            throw $this->forbidden();
        }

        $disk = Storage::disk($attachment->storage_disk);
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

        if ($imageAttempt) {
            if (! config('content.attachments.image_uploads_enabled', false)
                || ! $this->imageProcessor->isAvailable()) {
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
                ? ['pdf', ...($processed ? ['jpg', 'jpeg', 'png'] : [])]
                : ($processed ? ['jpg', 'jpeg', 'png', 'webp'] : []);
            $maxBytes = $purpose === ContentAttachmentPurpose::Cover ? 5 * 1024 * 1024 : 10 * 1024 * 1024;

            if ($size < 1 || $size > $maxBytes) {
                throw ValidationException::withMessages(['file' => ['The attachment size is outside the allowed limit.']]);
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
                if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || ($width * $height) > 36_000_000) {
                    throw ValidationException::withMessages(['file' => ['The image dimensions exceed the safe limit.']]);
                }
            } elseif (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
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
        return $item->scope === ContentScope::Global
            || ($item->scope === ContentScope::Campus
                && $actor->university_id !== null
                && (int) $item->university_id === (int) $actor->university_id);
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
