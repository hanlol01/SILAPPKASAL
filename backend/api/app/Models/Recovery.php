<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recovery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'decision_id',
        'recovery_type_code',
        'status_code',
        'created_by',
        'recovery_plan',
        'support_needs',
        'notes',
        'started_at',
        'completed_at',
        'discontinued_at',
    ];

    protected function casts(): array
    {
        return [
            'recovery_plan' => 'encrypted',
            'support_needs' => 'encrypted',
            'notes' => 'encrypted',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'discontinued_at' => 'datetime',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function recoveryType(): BelongsTo
    {
        return $this->belongsTo(RecoveryType::class, 'recovery_type_code', 'code');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RecoveryStatus::class, 'status_code', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RecoveryStatusHistory::class);
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(RecoveryMonitoring::class);
    }
}
