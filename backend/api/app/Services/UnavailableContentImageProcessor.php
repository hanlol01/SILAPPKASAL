<?php

namespace App\Services;

use App\Contracts\ContentImageProcessor;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UnavailableContentImageProcessor implements ContentImageProcessor
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function supportedMimeTypes(): array
    {
        return [];
    }

    public function reencode(UploadedFile $file): UploadedFile
    {
        throw new LogicException('No verified content image processor is available.');
    }

    public function release(UploadedFile $processed): void
    {
        // The unavailable adapter never creates processor-owned temporary files.
    }
}
