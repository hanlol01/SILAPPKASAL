<?php

namespace App\Enums;

enum RecoveryStatus: string
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Discontinued = 'discontinued';

    /**
     * @return list<string>
     */
    public static function terminalValues(): array
    {
        return [
            self::Completed->value,
            self::Discontinued->value,
        ];
    }
}
