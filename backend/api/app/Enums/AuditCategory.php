<?php

namespace App\Enums;

enum AuditCategory: string
{
    case Auth = 'auth';
    case Report = 'report';
    case Case = 'case';
    case Investigation = 'investigation';
    case Recommendation = 'recommendation';
    case Decision = 'decision';
    case Recovery = 'recovery';
    case Evidence = 'evidence';
    case Content = 'content';
    case Privacy = 'privacy';
    case Security = 'security';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
