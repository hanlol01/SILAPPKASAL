<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakGlassRequest extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    public const ALLOWED_DURATIONS = [30, 60, 240, 1440];

    public const REASON_CATEGORIES = [
        'legal_requirement',
        'safety_emergency',
        'investigation_necessity',
        'institutional_compliance',
        'victim_consent',
    ];

    protected $fillable = [
        'requestor_id',
        'approver_id',
        'report_id',
        'reason_category',
        'reason',
        'requested_duration_minutes',
        'status',
        'denial_reason',
        'requested_at',
        'approved_at',
        'grant_starts_at',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'denied_at',
        'viewed_at',
        'view_count',
        'last_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'grant_starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'denied_at' => 'datetime',
            'viewed_at' => 'datetime',
            'view_count' => 'integer',
            'last_viewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isViewable(): bool
    {
        return $this->isGrantActive();
    }

    public function isExpired(): bool
    {
        return $this->hasGrantExpired();
    }

    public function isGrantActive(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_VIEWED], true)
            && $this->revoked_at === null
            && $this->grant_starts_at !== null
            && $this->expires_at !== null
            && now()->gte($this->grant_starts_at)
            && now()->lt($this->expires_at);
    }

    public function hasGrantExpired(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_VIEWED, self::STATUS_EXPIRED], true)
            && ($this->status === self::STATUS_EXPIRED
                || ($this->expires_at !== null && now()->gte($this->expires_at)));
    }

    public function effectiveStatus(): string
    {
        if ($this->revoked_at !== null || $this->status === self::STATUS_REVOKED) {
            return self::STATUS_REVOKED;
        }

        return $this->hasGrantExpired() ? self::STATUS_EXPIRED : $this->status;
    }
}
