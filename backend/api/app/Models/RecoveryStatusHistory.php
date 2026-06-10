<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'recovery_id',
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

    public function recovery(): BelongsTo
    {
        return $this->belongsTo(Recovery::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(RecoveryStatus::class, 'from_status_code', 'code');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(RecoveryStatus::class, 'to_status_code', 'code');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
