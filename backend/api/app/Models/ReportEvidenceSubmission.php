<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEvidenceSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'report_id',
        'uploaded_by',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'uploaded_at',
    ];

    protected $hidden = [
        'id',
        'report_id',
        'uploaded_by',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
