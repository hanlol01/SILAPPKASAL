<?php

namespace App\Enums;

enum ContentLifecycleStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case RevisionRequested = 'revision_requested';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::RevisionRequested], true);
    }

    public function immutable(): bool
    {
        return in_array($this, [self::Published, self::Archived], true);
    }
}
