<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'request_id',
        'action',
        'category',
        'severity',
        'subject_type',
        'subject_id',
        'metadata',
        'before_changes',
        'after_changes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'before_changes' => 'array',
            'after_changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
