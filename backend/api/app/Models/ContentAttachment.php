<?php

namespace App\Models;

use App\Enums\ContentAttachmentPurpose;
use App\Models\Concerns\GuardsContentVersionImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContentAttachment extends Model
{
    use GuardsContentVersionImmutability, HasFactory;

    protected $fillable = [
        'public_id',
        'content_version_id',
        'purpose',
        'storage_disk',
        'storage_path',
        'safe_filename',
        'original_filename',
        'detected_mime',
        'extension',
        'file_size',
        'checksum_sha256',
        'width',
        'height',
        'alt_text',
        'display_order',
        'uploader_id',
    ];

    protected $hidden = [
        'id',
        'content_version_id',
        'storage_disk',
        'storage_path',
        'original_filename',
        'checksum_sha256',
        'uploader_id',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContentAttachment $attachment): void {
            if (blank($attachment->public_id)) {
                $attachment->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purpose' => ContentAttachmentPurpose::class,
            'original_filename' => 'encrypted',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
