<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(RecommendationStatus::class, 'status_code', 'code');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RecommendationStatusHistory::class);
    }
}
