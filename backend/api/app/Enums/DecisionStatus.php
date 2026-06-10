<?php

namespace App\Enums;

enum DecisionStatus: string
{
    case Draft = 'draft';
    case Recorded = 'recorded';
    case Finalized = 'finalized';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
