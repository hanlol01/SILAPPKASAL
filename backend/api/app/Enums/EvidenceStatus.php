<?php

namespace App\Enums;

enum EvidenceStatus: string
{
    case Registered = 'registered';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Archived = 'archived';

    /**
     * @return array<string, list<string>>
     */
    public static function transitions(): array
    {
        return [
            self::Registered->value => [
                self::UnderReview->value,
                self::Archived->value,
            ],
            self::UnderReview->value => [
                self::Verified->value,
                self::Rejected->value,
                self::Archived->value,
            ],
            self::Verified->value => [
                self::Archived->value,
            ],
            self::Rejected->value => [
                self::Archived->value,
            ],
            self::Archived->value => [],
        ];
    }

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
