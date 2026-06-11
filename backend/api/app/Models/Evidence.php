<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evidence extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'evidences';

    protected $fillable = [
        'investigation_id',
        'evidence_type_code',
        'submitted_by',
        'title',
        'description',
        'source',
        'collected_at',
        'classification',
        'status',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum_sha256',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'source' => 'encrypted',
            'collected_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function evidenceType(): BelongsTo
    {
        return $this->belongsTo(EvidenceType::class, 'evidence_type_code', 'code');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EvidenceStatusHistory::class);
    }

    public function custodyEvents(): HasMany
    {
        return $this->hasMany(EvidenceCustodyEvent::class);
    }
}
