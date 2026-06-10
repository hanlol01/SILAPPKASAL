<?php

namespace App\Models;

class InvestigationStatus extends MasterData
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'valid_transitions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'valid_transitions' => 'array',
        ];
    }
}
