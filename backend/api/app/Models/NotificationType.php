<?php

namespace App\Models;

class NotificationType extends MasterData
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'channel',
        'template_key',
        'recipient_role',
        'classification',
        'is_active',
        'sort_order',
    ];
}
