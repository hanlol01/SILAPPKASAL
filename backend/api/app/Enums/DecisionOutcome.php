<?php

namespace App\Enums;

enum DecisionOutcome: string
{
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Deferred = 'deferred';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $outcome): string => $outcome->value,
            self::cases()
        );
    }
}
