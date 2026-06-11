<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReporterPortalService
{
    /**
     * @return array{total_reports: int, active_reports: int, completed_reports: int, unread_notifications: int}
     */
    public function summary(User $user): array
    {
        $base = $this->ownedReportsQuery($user);
        $completed = (clone $base)
            ->whereHas('case.status', fn ($query) => $query->where('name', CaseStatusEnum::Closed->value))
            ->count();
        $total = (clone $base)->count();

        return [
            'total_reports' => $total,
            'active_reports' => $total - $completed,
            'completed_reports' => $completed,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Report>
     */
    public function reports(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->ownedReportsQuery($user)
            ->with(['category', 'case.status'])
            ->latest('submitted_at')
            ->paginate($perPage)
            ->through(fn (Report $report): Report => $this->withPortalStatus($report));
    }

    public function findReport(User $user, string $registrationNumber): ?Report
    {
        $report = $this->ownedReportsQuery($user)
            ->with(['category', 'case.status'])
            ->where('registration_number', $registrationNumber)
            ->first();

        return $report ? $this->withPortalStatus($report) : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Report>
     */
    private function ownedReportsQuery(User $user)
    {
        return Report::query()
            ->where('reporter_id', $user->id)
            ->whereNotNull('reporter_id')
            ->where('report_type', '!=', 'anonymous');
    }

    private function withPortalStatus(Report $report): Report
    {
        $report->setAttribute('portal_status', $this->portalStatus($report));

        return $report;
    }

    private function portalStatus(Report $report): string
    {
        if ($report->relationLoaded('case') && $report->case?->relationLoaded('status')) {
            if ($report->case->status?->name === CaseStatusEnum::Closed->value) {
                return 'Completed';
            }

            return 'In Process';
        }

        return match ($report->status) {
            ReportStatus::Submitted->value => 'Submitted',
            ReportStatus::Forwarded->value => 'In Process',
            default => 'Under Review',
        };
    }
}
