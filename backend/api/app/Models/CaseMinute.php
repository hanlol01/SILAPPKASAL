<?php

namespace App\Models;

use App\Enums\CaseMinuteStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CaseMinute extends Model
{
    use HasFactory;

    protected $fillable = [
        'occurred_at',
        'internal_summary',
        'anonymized_summary',
        'outcome',
        'follow_up',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseMinuteStatus::class,
            'occurred_at' => 'datetime',
            'internal_summary' => 'encrypted',
            'anonymized_summary' => 'encrypted',
            'outcome' => 'encrypted',
            'follow_up' => 'encrypted',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $minute): void {
            $minute->public_id ??= (string) Str::uuid();
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

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function lockVersion(): string
    {
        return hash('sha256', json_encode([
            'public_id' => (string) $this->public_id,
            'version' => (int) $this->version,
            'status' => $this->status?->value,
            'updated_at' => $this->tokenTimestamp($this->updated_at),
            'finalized_at' => $this->tokenTimestamp($this->finalized_at),
            'supersedes_id' => $this->supersedes_id === null ? null : (int) $this->supersedes_id,
        ], JSON_THROW_ON_ERROR));
    }

    private function tokenTimestamp(?CarbonInterface $timestamp): ?string
    {
        return $timestamp?->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
