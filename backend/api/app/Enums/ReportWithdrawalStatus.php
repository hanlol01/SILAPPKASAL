<?php

namespace App\Enums;

enum ReportWithdrawalStatus: string
{
    case Completed = 'completed';
    case Draft = 'draft';
    case WaitingDocument = 'waiting_document';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::Draft->value,
            self::WaitingDocument->value,
            self::PendingReview->value,
        ];
    }
}
