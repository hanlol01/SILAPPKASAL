<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'satgas_id',
        'assigned_by',
        'is_lead',
        'is_active',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'is_active' => 'boolean',
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function satgas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'satgas_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
