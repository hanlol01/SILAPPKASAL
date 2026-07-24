<?php

namespace App\Models;

use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReportWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'report_id',
        'case_id',
        'requester_id',
        'request_type',
        'status',
        'reason',
        'previous_report_status',
        'previous_case_status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'resubmission_allowed',
        'supersedes_id',
        'submitted_at',
        'cancelled_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'lock_version',
    ];

    protected $hidden = [
        'id',
        'report_id',
        'case_id',
        'requester_id',
        'reason',
        'rejection_reason',
        'reviewed_by',
        'supersedes_id',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReportWithdrawal $withdrawal): void {
            if (blank($withdrawal->public_id)) {
                $withdrawal->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'request_type' => ReportWithdrawalRequestType::class,
            'status' => ReportWithdrawalStatus::class,
            'reason' => 'encrypted',
            'rejection_reason' => 'encrypted',
            'resubmission_allowed' => 'boolean',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportWithdrawalAttachment::class, 'withdrawal_id');
    }
}
