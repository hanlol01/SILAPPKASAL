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
     * Reporter-safe progress timeline for an owned report.
     *
     * Returns only privacy-safe stage codes, safe timestamps, and the
     * completion state. Internal workflow status codes, staff identities,
     * assignments, and narrative content must never be added to this payload.
     *
     * @return array{registration_number: string, portal_status: string, is_completed: bool, events: list<array{stage: string, occurred_at: string|null}>}|null
     */
    public function reportTimeline(User $user, string $registrationNumber): ?array
    {
        $report = $this->ownedReportsQuery($user)
            ->with('case.status')
            ->where('registration_number', $registrationNumber)
            ->first();

        if (! $report) {
            return null;
        }

        $report = $this->withPortalStatus($report);
        $case = $report->case;
        $isCompleted = $case?->status?->name === CaseStatusEnum::Closed->value;

        $events = [];

        if ($report->submitted_at) {
            $events[] = ['stage' => 'laporan_dikirim', 'occurred_at' => $report->submitted_at];
        }

        if ($report->reviewed_at) {
            $events[] = ['stage' => 'laporan_ditinjau', 'occurred_at' => $report->reviewed_at];
        }

        if ($report->forwarded_at) {
            $events[] = ['stage' => 'proses_penanganan', 'occurred_at' => $report->forwarded_at];
        }

        if ($isCompleted && $case?->closed_at) {
            $events[] = ['stage' => 'selesai', 'occurred_at' => $case->closed_at];
        }

        usort($events, static fn (array $a, array $b): int => $a['occurred_at'] <=> $b['occurred_at']);

        return [
            'registration_number' => $report->registration_number,
            'portal_status' => (string) $report->portal_status,
            'is_completed' => $isCompleted,
            'events' => array_map(static fn (array $event): array => [
                'stage' => $event['stage'],
                'occurred_at' => $event['occurred_at']?->toJSON(),
            ], $events),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Report>
     */
    private function ownedReportsQuery(User $user)
    {
        return Report::query()
            ->where('reporter_id', $user->id)
            ->whereNotNull('reporter_id');
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
