<?php

namespace App\Models;

class ReportCategory extends MasterData
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'examples',
        'legal_basis',
        'is_active',
        'sort_order',
    ];
}
