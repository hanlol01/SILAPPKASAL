<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cases';

    protected $fillable = [
        'report_id',
        'registration_number',
        'case_number',
        'status_code',
        'risk_level_code',
        'priority_code',
        'current_stage',
        'forwarded_at',
        'assessment_at',
        'investigation_started_at',
        'recommendation_at',
        'decision_at',
        'closed_at',
        'escalated_at',
        'escalation_type',
    ];

    protected function casts(): array
    {
        return [
            'current_stage' => 'integer',
            'forwarded_at' => 'datetime',
            'assessment_at' => 'datetime',
            'investigation_started_at' => 'datetime',
            'recommendation_at' => 'datetime',
            'decision_at' => 'datetime',
            'closed_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function reportSensitive(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CaseStatus::class, 'status_code', 'code');
    }

    public function riskLevel(): BelongsTo
    {
        return $this->belongsTo(RiskLevel::class, 'risk_level_code', 'code');
    }

    public function priorityLevel(): BelongsTo
    {
        return $this->belongsTo(PriorityLevel::class, 'priority_code', 'code');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'case_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('is_active', true);
    }

    public function investigation(): HasOne
    {
        return $this->hasOne(Investigation::class, 'case_id');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->activeAssignments()
            ->where('satgas_id', $user->id)
            ->exists();
    }
}
