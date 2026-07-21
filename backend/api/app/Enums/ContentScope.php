<?php

namespace App\Enums;

enum ContentScope: string
{
    case Global = 'global';
    case Campus = 'campus';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
