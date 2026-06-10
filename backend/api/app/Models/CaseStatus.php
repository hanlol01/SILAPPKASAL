<?php

namespace App\Models;

class CaseStatus extends MasterData
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'workflow_stage',
        'stage_name',
        'is_terminal',
        'responsible_role',
        'valid_transitions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'workflow_stage' => 'integer',
            'is_terminal' => 'boolean',
            'valid_transitions' => 'array',
        ];
    }
}
