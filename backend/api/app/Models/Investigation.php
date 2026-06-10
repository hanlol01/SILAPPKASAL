<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Investigation extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'lead_investigator_id',
        'status_code',
        'plan_summary',
        'findings',
        'conclusion',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_summary' => 'encrypted',
            'findings' => 'encrypted',
            'conclusion' => 'encrypted',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function leadInvestigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_investigator_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(InvestigationStatus::class, 'status_code', 'code');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InvestigationActivity::class);
    }

    public function recommendation(): HasOne
    {
        return $this->hasOne(Recommendation::class);
    }
}
