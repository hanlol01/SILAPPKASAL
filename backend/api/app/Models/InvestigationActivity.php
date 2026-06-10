<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'investigation_id',
        'investigator_id',
        'activity_type',
        'activity_date',
        'description',
        'findings',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'description' => 'encrypted',
            'findings' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }
}
