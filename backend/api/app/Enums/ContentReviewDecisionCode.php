<?php

namespace App\Enums;

enum ContentReviewDecisionCode: string
{
    case ReviewStarted = 'review_started';
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';
    case Rejected = 'rejected';
    case DirectGlobalPublished = 'direct_global_published';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::RevisionRequested, self::Rejected, self::Archived], true);
    }
}
