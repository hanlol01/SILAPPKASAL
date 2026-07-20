<?php

namespace App\Models;

use App\Enums\CaseFinalOutcome;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseFinalSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'outcome_code',
        'completion_date',
        'official_statement',
        'investigation_summary',
        'recommendation_result',
        'decision_result',
        'recovery_result',
        'actions_completed',
        'actions_uncompleted',
        'follow_up_or_referral',
        'closing_explanation',
        'created_by',
        'updated_by',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome_code' => CaseFinalOutcome::class,
            'completion_date' => 'date',
            'official_statement' => 'encrypted',
            'investigation_summary' => 'encrypted',
            'recommendation_result' => 'encrypted',
            'decision_result' => 'encrypted',
            'recovery_result' => 'encrypted',
            'actions_completed' => 'encrypted',
            'actions_uncompleted' => 'encrypted',
            'follow_up_or_referral' => 'encrypted',
            'closing_explanation' => 'encrypted',
            'published_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
