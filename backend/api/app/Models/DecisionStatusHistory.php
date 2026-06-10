<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'decision_id',
        'from_status_code',
        'to_status_code',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(DecisionStatus::class, 'from_status_code', 'code');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(DecisionStatus::class, 'to_status_code', 'code');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
