<?php

namespace App\Models;

use App\Enums\ContentReviewDecisionCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ContentReviewDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'content_version_id',
        'reviewer_id',
        'decision_code',
        'narrative_reason',
        'decided_at',
    ];

    protected $hidden = [
        'id',
        'content_version_id',
        'reviewer_id',
        'narrative_reason',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContentReviewDecision $decision): void {
            if (blank($decision->public_id)) {
                $decision->public_id = (string) Str::uuid();
            }

            if ($decision->decision_code?->requiresReason() && blank($decision->narrative_reason)) {
                throw new LogicException('This content review decision requires a reason.');
            }
        });

        static::updating(fn () => throw new LogicException('Content review decisions are append-only.'));
        static::deleting(fn () => throw new LogicException('Content review decisions are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'decision_code' => ContentReviewDecisionCode::class,
            'narrative_reason' => 'encrypted',
            'decided_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
