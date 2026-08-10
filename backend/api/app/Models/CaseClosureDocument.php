<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CaseClosureDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'final_summary_id',
        'document_number',
        'storage_disk',
        'storage_path',
        'checksum_sha256',
        'file_size',
        'issued_by',
        'issued_at',
    ];

    protected $hidden = ['storage_disk', 'storage_path', 'checksum_sha256'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function finalSummary(): BelongsTo
    {
        return $this->belongsTo(CaseFinalSummary::class, 'final_summary_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
