<?php

namespace App\Models;

use App\Enums\ReportWithdrawalDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportWithdrawalAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdrawal_id',
        'public_id',
        'document_type',
        'version',
        'disk',
        'path',
        'original_name',
        'server_mime',
        'size',
        'sha256',
        'uploaded_by',
    ];

    protected $hidden = [
        'id',
        'withdrawal_id',
        'disk',
        'path',
        'original_name',
        'sha256',
        'uploaded_by',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReportWithdrawalAttachment $attachment): void {
            if (blank($attachment->public_id)) {
                $attachment->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'document_type' => ReportWithdrawalDocumentType::class,
            'original_name' => 'encrypted',
            'version' => 'integer',
            'size' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(ReportWithdrawal::class, 'withdrawal_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isSignedWithdrawalStatement(): bool
    {
        return $this->document_type === ReportWithdrawalDocumentType::SignedWithdrawalStatement;
    }
}
