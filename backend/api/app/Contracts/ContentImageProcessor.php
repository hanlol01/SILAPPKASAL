<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ContentImageProcessor
{
    public function isAvailable(): bool;

    /**
     * Return a newly re-encoded image with orientation normalized and metadata removed.
     */
    public function reencode(UploadedFile $file): UploadedFile;

    /**
     * Release processor-owned temporary output after storage or failure.
     */
    public function release(UploadedFile $processed): void;
}
