<?php

namespace App\Enums;

enum CaseMinuteStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Superseded = 'superseded';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
