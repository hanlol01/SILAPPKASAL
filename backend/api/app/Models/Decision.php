<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Decision extends Model
{
    use HasFactory;

    protected $fillable = [
        'recommendation_id',
        'recorder_id',
        'status_code',
        'outcome_code',
        'decision_number',
        'decision_date',
        'decision_summary',
        'decision_content',
        'recorded_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'decision_summary' => 'encrypted',
            'decision_content' => 'encrypted',
            'recorded_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorder_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DecisionStatus::class, 'status_code', 'code');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DecisionStatusHistory::class);
    }
}
