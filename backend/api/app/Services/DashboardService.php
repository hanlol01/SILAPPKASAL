<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     * @return array<string, mixed>
     */
    public function summary(User $user, array $filters): array
    {
        return [
            'scope' => $this->scopeName($user),
            'filters' => $this->formatFilters($filters),
            'totals' => [
                'reports' => $this->count($this->reportsQuery($user, $filters)),
                'cases' => $this->count($this->casesQuery($user, $filters)),
                'investigations' => $this->count($this->investigationsQuery($user, $filters)),
                'recommendations' => $this->count($this->recommendationsQuery($user, $filters)),
                'decisions' => $this->count($this->decisionsQuery($user, $filters)),
                'recoveries' => $this->count($this->recoveriesQuery($user, $filters)),
                'evidences' => $this->count($this->evidencesQuery($user, $filters)),
            ],
            'active_workflow' => [
                'cases_open' => $this->count($this->casesQuery($user, $filters)->whereNull('cases.closed_at')),
                'investigations_open' => $this->count($this->investigationsQuery($user, $filters)->whereNull('investigations.completed_at')),
                'decisions_not_finalized' => $this->count($this->decisionsQuery($user, $filters)->whereNull('decisions.finalized_at')),
                'recoveries_open' => $this->count(
                    $this->recoveriesQuery($user, $filters)
                        ->whereNull('recoveries.completed_at')
                        ->whereNull('recoveries.discontinued_at')
                ),
            ],
            'time_series' => [
                'reports' => $this->timeSeries($this->reportsQuery($user, $filters), 'reports.submitted_at', $filters['granularity']),
                'cases' => $this->timeSeries($this->casesQuery($user, $filters), 'cases.forwarded_at', $filters['granularity']),
            ],
        ];
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     * @return array<string, mixed>
     */
    public function reports(User $user, array $filters): array
    {
        $query = $this->reportsQuery($user, $filters);

        return [
            'scope' => $this->scopeName($user),
            'filters' => $this->formatFilters($filters),
            'total' => $this->count($query),
            'by_status' => $this->groupedCount($query, 'reports.status'),
            'by_report_type' => $this->groupedCount($query, 'reports.report_type'),
            'by_category_code' => $this->groupedCount($query, 'reports.category_code'),
            'by_priority' => $this->groupedCount($query, 'reports.priority'),
            'by_identity_mode' => [
                'anonymous' => $this->count($this->reportsQuery($user, $filters)->where('reports.report_type', 'anonymous')),
                'identified' => $this->count($this->reportsQuery($user, $filters)->where('reports.report_type', '<>', 'anonymous')),
            ],
            'time_series' => $this->timeSeries($query, 'reports.submitted_at', $filters['granularity']),
        ];
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     * @return array<string, mixed>
     */
    public function cases(User $user, array $filters): array
    {
        $query = $this->casesQuery($user, $filters);

        return [
            'scope' => $this->scopeName($user),
            'filters' => $this->formatFilters($filters),
            'total' => $this->count($query),
            'by_status_code' => $this->groupedCount($query, 'cases.status_code'),
            'by_risk_level_code' => $this->groupedCount($query, 'cases.risk_level_code'),
            'by_priority_code' => $this->groupedCount($query, 'cases.priority_code'),
            'by_current_stage' => $this->groupedCount($query, 'cases.current_stage'),
            'assignments' => [
                'assigned_cases' => $this->assignedCaseCount($user, $filters),
                'unassigned_cases' => $this->unassignedCaseCount($user, $filters),
                'active_assignments' => $this->activeAssignmentCount($user, $filters),
            ],
            'time_series' => $this->timeSeries($query, 'cases.forwarded_at', $filters['granularity']),
        ];
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     * @return array<string, mixed>
     */
    public function workflow(User $user, array $filters): array
    {
        return [
            'scope' => $this->scopeName($user),
            'filters' => $this->formatFilters($filters),
            'metric_semantics' => 'descriptive_counts_only_not_kpi_not_sla_not_success_rate_not_performance_scoring',
            'status_distributions' => [
                'investigations' => $this->groupedCount($this->investigationsQuery($user, $filters), 'investigations.status_code'),
                'recommendations' => $this->groupedCount($this->recommendationsQuery($user, $filters), 'recommendations.status_code'),
                'decisions' => $this->groupedCount($this->decisionsQuery($user, $filters), 'decisions.status_code'),
                'recoveries' => $this->groupedCount($this->recoveriesQuery($user, $filters), 'recoveries.status_code'),
            ],
            'decision_outcomes' => $this->groupedCount($this->decisionsQuery($user, $filters), 'decisions.outcome_code'),
            'recovery_types' => $this->groupedCount($this->recoveriesQuery($user, $filters), 'recoveries.recovery_type_code'),
            'monitoring_time_series' => $this->timeSeries($this->monitoringQuery($user, $filters), 'recovery_monitorings.monitoring_date', $filters['granularity']),
            'conversion_counts' => [
                'reports_forwarded_to_cases' => $this->count($this->casesQuery($user, $filters)),
                'cases_with_investigations' => $this->count($this->investigationsQuery($user, $filters)),
                'cases_with_recommendations' => $this->distinctCount($this->recommendationsQuery($user, $filters), 'recommendations.case_id'),
                'recommendations_with_decisions' => $this->count($this->decisionsQuery($user, $filters)),
                'decisions_with_recoveries' => $this->distinctCount($this->recoveriesQuery($user, $filters), 'recoveries.decision_id'),
            ],
        ];
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     * @return array<string, mixed>
     */
    public function evidence(User $user, array $filters): array
    {
        $query = $this->evidencesQuery($user, $filters);

        return [
            'scope' => $this->scopeName($user),
            'filters' => $this->formatFilters($filters),
            'privacy' => 'count_based_metadata_only_no_filenames_no_checksums_no_custody_events',
            'total' => $this->count($query),
            'by_status' => $this->groupedCount($query, 'evidences.status'),
            'by_classification' => $this->groupedCount($query, 'evidences.classification'),
            'by_evidence_type_code' => $this->groupedCount($query, 'evidences.evidence_type_code'),
            'file_metadata_presence' => [
                'with_metadata' => $this->count($this->evidencesWithFileMetadataQuery($user, $filters)),
                'without_metadata' => $this->count($this->evidencesWithoutFileMetadataQuery($user, $filters)),
            ],
            'time_series' => $this->timeSeries($query, 'evidences.created_at', $filters['granularity']),
        ];
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function reportsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('reports')
            ->whereNull('reports.deleted_at')
            ->whereBetween('reports.submitted_at', [$filters['date_from'], $filters['date_to']]);

        if (! $this->isGlobalScope($user)) {
            $query->join('cases', 'cases.report_id', '=', 'reports.id')
                ->join('case_assignments', 'case_assignments.case_id', '=', 'cases.id')
                ->whereNull('cases.deleted_at')
                ->where('case_assignments.satgas_id', $user->id)
                ->where('case_assignments.is_active', true);
        }

        return $query;
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function casesQuery(User $user, array $filters): Builder
    {
        $query = DB::table('cases')
            ->whereNull('cases.deleted_at')
            ->whereBetween('cases.forwarded_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function investigationsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('investigations')
            ->join('cases', 'cases.id', '=', 'investigations.case_id')
            ->whereNull('cases.deleted_at')
            ->whereBetween('investigations.started_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function recommendationsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('recommendations')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->whereNull('cases.deleted_at')
            ->whereBetween('recommendations.created_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function decisionsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('decisions')
            ->join('recommendations', 'recommendations.id', '=', 'decisions.recommendation_id')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->whereNull('cases.deleted_at')
            ->whereBetween('decisions.recorded_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function recoveriesQuery(User $user, array $filters): Builder
    {
        $query = DB::table('recoveries')
            ->join('decisions', 'decisions.id', '=', 'recoveries.decision_id')
            ->join('recommendations', 'recommendations.id', '=', 'decisions.recommendation_id')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->whereNull('recoveries.deleted_at')
            ->whereNull('cases.deleted_at')
            ->whereBetween('recoveries.created_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function monitoringQuery(User $user, array $filters): Builder
    {
        $query = DB::table('recovery_monitorings')
            ->join('recoveries', 'recoveries.id', '=', 'recovery_monitorings.recovery_id')
            ->join('decisions', 'decisions.id', '=', 'recoveries.decision_id')
            ->join('recommendations', 'recommendations.id', '=', 'decisions.recommendation_id')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->whereNull('recoveries.deleted_at')
            ->whereNull('cases.deleted_at')
            ->whereBetween('recovery_monitorings.monitoring_date', [$filters['date_from']->toDateString(), $filters['date_to']->toDateString()]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function evidencesQuery(User $user, array $filters): Builder
    {
        $query = DB::table('evidences')
            ->join('investigations', 'investigations.id', '=', 'evidences.investigation_id')
            ->join('cases', 'cases.id', '=', 'investigations.case_id')
            ->whereNull('evidences.deleted_at')
            ->whereNull('cases.deleted_at')
            ->whereBetween('evidences.created_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user);
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function evidencesWithFileMetadataQuery(User $user, array $filters): Builder
    {
        return $this->evidencesQuery($user, $filters)
            ->where(function (Builder $query): void {
                $query->whereNotNull('evidences.original_filename')
                    ->orWhereNotNull('evidences.mime_type')
                    ->orWhereNotNull('evidences.file_size')
                    ->orWhereNotNull('evidences.checksum_sha256');
            });
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     */
    private function evidencesWithoutFileMetadataQuery(User $user, array $filters): Builder
    {
        return $this->evidencesQuery($user, $filters)
            ->whereNull('evidences.original_filename')
            ->whereNull('evidences.mime_type')
            ->whereNull('evidences.file_size')
            ->whereNull('evidences.checksum_sha256');
    }

    private function scopeCasesToUser(Builder $query, User $user): Builder
    {
        if ($this->isGlobalScope($user)) {
            return $query;
        }

        return $query
            ->join('case_assignments', 'case_assignments.case_id', '=', 'cases.id')
            ->where('case_assignments.satgas_id', $user->id)
            ->where('case_assignments.is_active', true);
    }

    private function assignedCaseCount(User $user, array $filters): int
    {
        return $this->count($this->casesQuery($user, $filters));
    }

    private function unassignedCaseCount(User $user, array $filters): int
    {
        if (! $this->isGlobalScope($user)) {
            return 0;
        }

        return $this->count(
            $this->casesQuery($user, $filters)
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('case_assignments')
                        ->whereColumn('case_assignments.case_id', 'cases.id')
                        ->where('case_assignments.is_active', true);
                })
        );
    }

    private function activeAssignmentCount(User $user, array $filters): int
    {
        $query = DB::table('case_assignments')
            ->join('cases', 'cases.id', '=', 'case_assignments.case_id')
            ->whereNull('cases.deleted_at')
            ->where('case_assignments.is_active', true)
            ->whereBetween('cases.forwarded_at', [$filters['date_from'], $filters['date_to']]);

        if (! $this->isGlobalScope($user)) {
            $query->where('case_assignments.satgas_id', $user->id);
        }

        return $this->count($query);
    }

    /**
     * @return array<int, array{key: string|null, count: int}>
     */
    private function groupedCount(Builder $query, string $column): array
    {
        return (clone $query)
            ->selectRaw("{$column} as aggregate_key, count(*) as aggregate_count")
            ->groupBy($column)
            ->orderBy('aggregate_key')
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->aggregate_key,
                'count' => (int) $row->aggregate_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{bucket: string, count: int}>
     */
    private function timeSeries(Builder $query, string $column, string $granularity): array
    {
        $bucket = $this->bucketExpression($column, $granularity);

        return (clone $query)
            ->selectRaw("{$bucket} as bucket, count(*) as aggregate_count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($row): array => [
                'bucket' => (string) $row->bucket,
                'count' => (int) $row->aggregate_count,
            ])
            ->all();
    }

    private function bucketExpression(string $column, string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return match ($granularity) {
                'week' => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                'month' => "to_char(date_trunc('month', {$column}), 'YYYY-MM')",
                default => "to_char({$column}, 'YYYY-MM-DD')",
            };
        }

        return match ($granularity) {
            'week' => "strftime('%Y-W%W', {$column})",
            'month' => "strftime('%Y-%m', {$column})",
            default => "strftime('%Y-%m-%d', {$column})",
        };
    }

    private function count(Builder $query): int
    {
        return (int) (clone $query)->count();
    }

    private function distinctCount(Builder $query, string $column): int
    {
        return (int) (clone $query)->distinct()->count($column);
    }

    private function isGlobalScope(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    private function scopeName(User $user): string
    {
        return $this->isGlobalScope($user) ? 'global' : 'assigned_cases';
    }

    /**
     * @param  array{date_from: CarbonInterface, date_to: CarbonInterface, granularity: string}  $filters
     * @return array<string, string>
     */
    private function formatFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from']->toDateString(),
            'date_to' => $filters['date_to']->toDateString(),
            'granularity' => $filters['granularity'],
        ];
    }
}
