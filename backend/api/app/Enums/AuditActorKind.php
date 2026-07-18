<?php

namespace App\Enums;

enum AuditActorKind: string
{
    case System = 'system';
    case Reporter = 'reporter';
    case Staff = 'staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
