<?php

namespace App\Enums;

use App\Models\Report;

enum ReporterSafeStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case InProcess = 'in_process';
    case Completed = 'completed';
    case CancelledByReporter = 'cancelled_by_reporter';
    case Withdrawn = 'withdrawn';

    public static function forReport(Report $report): self
    {
        if ($report->status === ReportStatus::Cancelled->value) {
            return self::CancelledByReporter;
        }

        if ($report->status === ReportStatus::Withdrawn->value) {
            return self::Withdrawn;
        }

        if ($report->relationLoaded('case') && $report->case?->relationLoaded('status')) {
            if ($report->case->status?->name === CaseStatus::Closed->value) {
                return self::Completed;
            }

            return self::InProcess;
        }

        return match ($report->status) {
            ReportStatus::Submitted->value => self::Submitted,
            ReportStatus::Forwarded->value => self::InProcess,
            default => self::UnderReview,
        };
    }
}
