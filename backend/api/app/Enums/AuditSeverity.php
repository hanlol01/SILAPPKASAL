<?php

namespace App\Enums;

enum AuditSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
