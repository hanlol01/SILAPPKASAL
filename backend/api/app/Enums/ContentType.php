<?php

namespace App\Enums;

enum ContentType: string
{
    case Article = 'article';
    case Faq = 'faq';
    case Consultation = 'consultation';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
