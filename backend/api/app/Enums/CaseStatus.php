<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Forwarded = 'forwarded';
    case Assessment = 'assessment';
    case Investigation = 'investigation';
    case Mediation = 'mediation';
    case Recommendation = 'recommendation';
    case Decision = 'decision';
    case Decided = 'decided';
    case Recovery = 'recovery';
    case Monitoring = 'monitoring';
    case Closed = 'closed';
    case Escalated = 'escalated';

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
