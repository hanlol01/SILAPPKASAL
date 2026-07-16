<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class Recommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'investigation_id',
        'author_id',
        'status_code',
        'conclusion',
        'recommended_actions',
        'sanction_recommendation',
        'recovery_recommendation',
        'prevention_recommendation',
        'submitted_at',
        'returned_by',
        'returned_at',
        'revision_note',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'conclusion' => 'encrypted',
            'recommended_actions' => 'encrypted',
            'sanction_recommendation' => 'encrypted',
            'recovery_recommendation' => 'encrypted',
            'prevention_recommendation' => 'encrypted',
            'submitted_at' => 'datetime',
            'returned_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected function revisionNote(): Attribute
    {
        return Attribute::make(
            get: static function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return null;
                }
            },
            set: static fn (?string $value): ?string => $value === null
                ? null
                : Crypt::encryptString($value),
        );
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RecommendationStatus::class, 'status_code', 'code');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RecommendationStatusHistory::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(Decision::class);
    }
}
