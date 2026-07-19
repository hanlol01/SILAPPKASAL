<?php

namespace App\Enums;

enum RecommendationStatus: string
{
    case Drafting = 'drafting';
    case InternalReview = 'internal_review';
    case SubmittedForReview = 'submitted_for_review';
    case SubmittedToLeader = 'submitted_to_leader';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Revised = 'revised';

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

    /**
     * @return list<string>
     */
    public static function decisionOnlyValues(): array
    {
        return [
            self::Accepted->value,
            self::PartiallyAccepted->value,
            self::Rejected->value,
        ];
    }

    /** @return list<string> */
    public static function submittedReviewValues(): array
    {
        return [self::SubmittedForReview->value, self::SubmittedToLeader->value];
    }
}
