<?php

namespace App\Enums;

enum EvidenceClassification: string
{
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $classification): string => $classification->value,
            self::cases()
        );
    }
}
