<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceCustodyEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_id',
        'actor_id',
        'event_type',
        'event_at',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'encrypted:array',
            'event_at' => 'datetime',
        ];
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
