<?php

namespace App\Services;

use App\Models\ContentAttachment;
use App\Models\ContentVersion;
use App\Models\User;
use App\Support\ContentAttachmentFilename;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ContentRevisionAttachmentService
{
    /**
     * @return array{
     *     public_ids: array<string, string>,
     *     database_ids: array<int, int>,
     *     paths: list<string>
     * }
     */
    public function clone(ContentVersion $source, ContentVersion $target, User $actor): array
    {
        $publicIds = [];
        $databaseIds = [];
        $createdPaths = [];

        try {
            foreach ($source->attachments()->orderBy('id')->get() as $attachment) {
                if ($attachment->storage_disk !== 'content') {
                    throw $this->invalidAttachment('An attachment uses an unsupported private storage disk.');
                }

                $disk = Storage::disk('content');
                if (! $disk->exists($attachment->storage_path)) {
                    throw $this->invalidAttachment('A source attachment is unavailable.');
                }

                $publicId = (string) Str::uuid();
                $path = $target->public_id.'/'.$publicId.'.'.$attachment->extension;
                if (! $disk->copy($attachment->storage_path, $path)) {
                    throw $this->invalidAttachment('A source attachment could not be cloned safely.');
                }
                $createdPaths[] = $path;

                if (! $disk->exists($path)
                    || $disk->size($path) !== $attachment->file_size
                    || $this->checksum($path) !== $attachment->checksum_sha256) {
                    throw $this->invalidAttachment('A cloned attachment failed its integrity check.');
                }

                $clone = ContentAttachment::query()->create([
                    'public_id' => $publicId,
                    'content_version_id' => $target->id,
                    'purpose' => $attachment->purpose,
                    'storage_disk' => 'content',
                    'storage_path' => $path,
                    'safe_filename' => ContentAttachmentFilename::make(
                        $publicId,
                        $attachment->purpose,
                        $attachment->detected_mime,
                    ),
                    'original_filename' => null,
                    'detected_mime' => $attachment->detected_mime,
                    'extension' => $attachment->extension,
                    'file_size' => $attachment->file_size,
                    'checksum_sha256' => $attachment->checksum_sha256,
                    'width' => $attachment->width,
                    'height' => $attachment->height,
                    'alt_text' => $attachment->alt_text,
                    'display_order' => $attachment->display_order,
                    'uploader_id' => $actor->id,
                ]);

                $publicIds[$attachment->public_id] = $clone->public_id;
                $databaseIds[$attachment->id] = $clone->id;
            }

            return [
                'public_ids' => $publicIds,
                'database_ids' => $databaseIds,
                'paths' => $createdPaths,
            ];
        } catch (Throwable $exception) {
            if ($createdPaths !== []) {
                Storage::disk('content')->delete($createdPaths);
            }

            throw $exception;
        }
    }

    /** @param list<string> $paths */
    public function cleanup(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('content')->delete($paths);
        }
    }

    private function checksum(string $path): string
    {
        $stream = Storage::disk('content')->readStream($path);
        if (! is_resource($stream)) {
            throw $this->invalidAttachment('A cloned attachment could not be read for integrity verification.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function invalidAttachment(string $message): ValidationException
    {
        return ValidationException::withMessages(['attachments' => [$message]]);
    }
}
