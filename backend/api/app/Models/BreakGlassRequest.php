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
        'status',
        'denial_reason',
        'requested_at',
        'approved_at',
        'denied_at',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
            'viewed_at' => 'datetime',
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
        if (! in_array($this->status, [self::STATUS_APPROVED, self::STATUS_VIEWED])) {
            return false;
        }

        return $this->viewed_at === null || now()->lt($this->viewed_at->copy()->addHours(8));
    }

    public function isExpired(): bool
    {
        return $this->viewed_at !== null && now()->gte($this->viewed_at->copy()->addHours(8));
    }
}
