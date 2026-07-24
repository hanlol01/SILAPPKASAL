<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedInfo = 'need_info';
    case Rejected = 'rejected';
    case Forwarded = 'forwarded';
    case Cancelled = 'cancelled';
    case Withdrawn = 'withdrawn';

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
