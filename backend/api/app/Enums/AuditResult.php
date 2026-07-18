<?php

namespace App\Enums;

enum AuditResult: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Denied = 'denied';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
