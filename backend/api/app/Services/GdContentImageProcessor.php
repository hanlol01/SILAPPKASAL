<?php

namespace App\Services;

use App\Contracts\ContentImageProcessor;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class GdContentImageProcessor implements ContentImageProcessor
{
    /** @var array<string, string> */
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<string, true> */
    private array $temporaryPaths = [];

    public function isAvailable(): bool
    {
        return $this->supportedMimeTypes() !== [];
    }

    public function supportedMimeTypes(): array
    {
        if (! extension_loaded('gd')
            || ! extension_loaded('fileinfo')
            || ! $this->functionsAvailable([
                'finfo_open',
                'finfo_file',
                'finfo_close',
                'getimagesize',
                'imagedestroy',
            ])) {
            return [];
        }

        $formats = [];
        if ($this->functionsAvailable([
            'imagecreatefromjpeg',
            'imagejpeg',
            'imagerotate',
            'imageflip',
        ])) {
            $formats[] = 'image/jpeg';
        }
        if ($this->functionsAvailable([
            'imagecreatefrompng',
            'imagepng',
            'imagealphablending',
            'imagesavealpha',
        ])) {
            $formats[] = 'image/png';
        }
        if ($this->functionsAvailable([
            'imagecreatefromwebp',
            'imagewebp',
            'imagealphablending',
            'imagesavealpha',
        ])) {
            $formats[] = 'image/webp';
        }

        return $formats;
    }

    public function reencode(UploadedFile $file): UploadedFile
    {
        if (! $this->isAvailable()) {
            throw new LogicException('The verified GD content image processor is unavailable.');
        }

        $sourcePath = $file->getRealPath();
        if (! is_string($sourcePath) || $sourcePath === '' || ! is_file($sourcePath)) {
            throw $this->invalidImage();
        }

        $sourceSize = filesize($sourcePath);
        if (! is_int($sourceSize)
            || $sourceSize < 1
            || $sourceSize > (int) config('content.attachments.max_image_source_bytes', 10 * 1024 * 1024)) {
            throw ValidationException::withMessages([
                'file' => ['The source image size is outside the safe processing limit.'],
            ]);
        }

        $mime = $this->detectedMime($sourcePath);
        $extension = self::EXTENSION_BY_MIME[$mime] ?? null;
        if ($extension === null || ! in_array($mime, $this->supportedMimeTypes(), true)) {
            throw ValidationException::withMessages([
                'file' => ['This image format is unavailable for verified processing.'],
            ]);
        }

        $dimensions = @getimagesize($sourcePath);
        if ($dimensions === false
            || ($dimensions['mime'] ?? null) !== $mime
            || ! isset($dimensions[0], $dimensions[1])) {
            throw $this->invalidImage();
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        $this->assertSafeDimensions($width, $height);
        $this->assertMemoryBudget($width, $height, $sourceSize);

        $image = null;
        $outputPath = null;

        try {
            $image = $this->decode($sourcePath, $mime);
            if (! $image instanceof GdImage) {
                throw $this->invalidImage();
            }

            if ($mime === 'image/jpeg') {
                $image = $this->normalizeOrientation($image, $this->jpegOrientation($sourcePath));
            }
            $image = $this->optimizeDimensions($image, $sourceSize);

            $outputPath = tempnam(sys_get_temp_dir(), 'silappkasal-content-image-');
            if (! is_string($outputPath) || $outputPath === '') {
                throw new LogicException('A secure temporary image path could not be allocated.');
            }

            if (! $this->encode($image, $outputPath, $mime)) {
                throw $this->invalidImage();
            }

            $processedMime = $this->detectedMime($outputPath);
            $processedDimensions = @getimagesize($outputPath);
            if ($processedMime !== $mime
                || $processedDimensions === false
                || ($processedDimensions['mime'] ?? null) !== $mime) {
                throw $this->invalidImage();
            }

            $this->assertSafeDimensions(
                (int) $processedDimensions[0],
                (int) $processedDimensions[1],
            );

            $this->temporaryPaths[$outputPath] = true;

            return new UploadedFile(
                $outputPath,
                'processed.'.$extension,
                $mime,
                UPLOAD_ERR_OK,
                true,
            );
        } catch (ValidationException|LogicException $exception) {
            if (is_string($outputPath) && is_file($outputPath)) {
                @unlink($outputPath);
            }

            throw $exception;
        } catch (Throwable) {
            if (is_string($outputPath) && is_file($outputPath)) {
                @unlink($outputPath);
            }

            throw $this->invalidImage();
        } finally {
            if ($image instanceof GdImage) {
                imagedestroy($image);
            }
        }
    }

    public function release(UploadedFile $processed): void
    {
        $path = $processed->getRealPath();
        if (! is_string($path) || ! isset($this->temporaryPaths[$path])) {
            return;
        }

        unset($this->temporaryPaths[$path]);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function detectedMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new LogicException('The fileinfo extension is required for content image processing.');
        }

        try {
            $mime = finfo_file($finfo, $path);
        } finally {
            finfo_close($finfo);
        }

        return is_string($mime) ? mb_strtolower(trim($mime)) : '';
    }

    private function assertSafeDimensions(int $width, int $height): void
    {
        $maxDimension = (int) config('content.attachments.max_image_dimension', 6000);
        $maxPixels = (int) config('content.attachments.max_image_pixels', 24_000_000);

        if ($width < 1
            || $height < 1
            || $width > $maxDimension
            || $height > $maxDimension
            || ($width * $height) > $maxPixels) {
            throw ValidationException::withMessages([
                'file' => ['The image dimensions exceed the safe processing limit.'],
            ]);
        }
    }

    private function assertMemoryBudget(int $width, int $height, int $sourceSize): void
    {
        $memoryLimit = $this->iniBytes((string) ini_get('memory_limit'));
        if ($memoryLimit === null) {
            return;
        }

        $estimatedBytes = ($width * $height * 10) + ($sourceSize * 2) + (16 * 1024 * 1024);
        $reservedBytes = 8 * 1024 * 1024;
        if (memory_get_usage(true) + $estimatedBytes + $reservedBytes > $memoryLimit) {
            throw ValidationException::withMessages([
                'file' => ['The image requires more memory than the safe processing budget allows.'],
            ]);
        }
    }

    private function iniBytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return null;
        }

        $unit = mb_strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function decode(string $path, string $mime): GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
    }

    private function encode(GdImage $image, string $path, string $mime): bool
    {
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, (int) config('content.attachments.jpeg_quality', 82)),
            'image/png' => imagepng($image, $path, (int) config('content.attachments.png_compression', 8)),
            'image/webp' => imagewebp($image, $path, (int) config('content.attachments.webp_quality', 80)),
            default => false,
        };
    }

    private function optimizeDimensions(GdImage $image, int $sourceSize): GdImage
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $triggerBytes = (int) config('content.attachments.image_optimization_trigger_bytes', 2 * 1024 * 1024);
        $maxDimension = (int) config('content.attachments.optimized_image_max_dimension', 2560);

        if ($sourceSize <= $triggerBytes
            && $sourceWidth <= $maxDimension
            && $sourceHeight <= $maxDimension) {
            return $image;
        }

        $scale = min(1, $maxDimension / $sourceWidth, $maxDimension / $sourceHeight);
        if ($scale >= 1) {
            return $image;
        }

        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! ($resized instanceof GdImage)) {
            throw $this->invalidImage();
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
        if (! imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight)) {
            imagedestroy($resized);
            throw $this->invalidImage();
        }

        imagedestroy($image);

        return $resized;
    }

    private function normalizeOrientation(GdImage $image, int $orientation): GdImage
    {
        $flip = null;
        $angle = 0;

        switch ($orientation) {
            case 2:
                $flip = IMG_FLIP_HORIZONTAL;
                break;
            case 3:
                $angle = 180;
                break;
            case 4:
                $flip = IMG_FLIP_VERTICAL;
                break;
            case 5:
                $flip = IMG_FLIP_HORIZONTAL;
                $angle = 90;
                break;
            case 6:
                $angle = -90;
                break;
            case 7:
                $flip = IMG_FLIP_HORIZONTAL;
                $angle = -90;
                break;
            case 8:
                $angle = 90;
                break;
        }

        if ($flip !== null && ! imageflip($image, $flip)) {
            throw $this->invalidImage();
        }

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated instanceof GdImage) {
            throw $this->invalidImage();
        }

        imagedestroy($image);

        return $rotated;
    }

    private function jpegOrientation(string $path): int
    {
        $size = filesize($path);
        if (! is_int($size) || $size < 4) {
            return 1;
        }

        $bytes = file_get_contents($path, false, null, 0, min($size, 262_144));
        if (! is_string($bytes) || ! str_starts_with($bytes, "\xFF\xD8")) {
            return 1;
        }

        $length = strlen($bytes);
        $offset = 2;

        while ($offset + 4 <= $length) {
            if (ord($bytes[$offset]) !== 0xFF) {
                break;
            }
            while ($offset < $length && ord($bytes[$offset]) === 0xFF) {
                $offset++;
            }
            if ($offset >= $length) {
                break;
            }

            $marker = ord($bytes[$offset++]);
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                continue;
            }
            if ($offset + 2 > $length) {
                break;
            }

            $segmentLength = unpack('n', substr($bytes, $offset, 2))[1] ?? 0;
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                break;
            }

            if ($marker === 0xE1) {
                $payload = substr($bytes, $offset + 2, $segmentLength - 2);
                $orientation = $this->orientationFromExif($payload);
                if ($orientation !== null) {
                    return $orientation;
                }
            }

            $offset += $segmentLength;
        }

        return 1;
    }

    private function orientationFromExif(string $payload): ?int
    {
        if (! str_starts_with($payload, "Exif\x00\x00")) {
            return null;
        }

        $tiff = substr($payload, 6);
        if (strlen($tiff) < 8) {
            return null;
        }

        $byteOrder = substr($tiff, 0, 2);
        if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
            return null;
        }

        $littleEndian = $byteOrder === 'II';
        $uint16 = static function (string $data, int $offset) use ($littleEndian): ?int {
            if ($offset < 0 || $offset + 2 > strlen($data)) {
                return null;
            }

            $value = unpack($littleEndian ? 'v' : 'n', substr($data, $offset, 2));

            return is_array($value) ? (int) ($value[1] ?? 0) : null;
        };
        $uint32 = static function (string $data, int $offset) use ($littleEndian): ?int {
            if ($offset < 0 || $offset + 4 > strlen($data)) {
                return null;
            }

            $value = unpack($littleEndian ? 'V' : 'N', substr($data, $offset, 4));

            return is_array($value) ? (int) ($value[1] ?? 0) : null;
        };

        if ($uint16($tiff, 2) !== 42) {
            return null;
        }

        $ifdOffset = $uint32($tiff, 4);
        if ($ifdOffset === null || $ifdOffset < 8) {
            return null;
        }

        $entryCount = $uint16($tiff, $ifdOffset);
        if ($entryCount === null || $entryCount > 1024) {
            return null;
        }

        for ($index = 0; $index < $entryCount; $index++) {
            $entryOffset = $ifdOffset + 2 + ($index * 12);
            if ($entryOffset + 12 > strlen($tiff)) {
                return null;
            }
            if ($uint16($tiff, $entryOffset) !== 0x0112
                || $uint16($tiff, $entryOffset + 2) !== 3
                || $uint32($tiff, $entryOffset + 4) !== 1) {
                continue;
            }

            $orientation = $uint16($tiff, $entryOffset + 8);

            return $orientation !== null && $orientation >= 1 && $orientation <= 8
                ? $orientation
                : null;
        }

        return null;
    }

    private function invalidImage(): ValidationException
    {
        return ValidationException::withMessages([
            'file' => ['The uploaded image could not be decoded and safely re-encoded.'],
        ]);
    }

    /** @param list<string> $functions */
    private function functionsAvailable(array $functions): bool
    {
        foreach ($functions as $function) {
            if (! function_exists($function)) {
                return false;
            }
        }

        return true;
    }
}
