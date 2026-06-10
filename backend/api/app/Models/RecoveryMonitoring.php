<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryMonitoring extends Model
{
    use HasFactory;

    protected $fillable = [
        'recovery_id',
        'monitor_id',
        'monitoring_date',
        'status',
        'condition_summary',
        'follow_up_plan',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monitoring_date' => 'date',
            'condition_summary' => 'encrypted',
            'follow_up_plan' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function recovery(): BelongsTo
    {
        return $this->belongsTo(Recovery::class);
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitor_id');
    }
}
