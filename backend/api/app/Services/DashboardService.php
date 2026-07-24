<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @param  array<string, mixed>  $filters
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
                'cases_open' => $this->count($this->operationalCases($this->casesQuery($user, $filters))),
                'investigations_open' => $this->count(
                    $this->operationalCases($this->investigationsQuery($user, $filters))
                        ->whereNull('investigations.completed_at')
                ),
                'decisions_not_finalized' => $this->count(
                    $this->operationalCases($this->decisionsQuery($user, $filters))
                        ->whereNull('decisions.finalized_at')
                ),
                'recoveries_open' => $this->count(
                    $this->operationalCases($this->recoveriesQuery($user, $filters))
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
     * @param  array<string, mixed>  $filters
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
            'by_priority' => $this->reportPriorityCounts($query),
            'by_identity_mode' => [
                'anonymous' => $this->count($this->reportsQuery($user, $filters)->where('reports.report_type', 'anonymous')),
                'identified' => $this->count($this->reportsQuery($user, $filters)->where('reports.report_type', '<>', 'anonymous')),
            ],
            'time_series' => $this->timeSeries($query, 'reports.submitted_at', $filters['granularity']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
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
     * @param  array<string, mixed>  $filters
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
     * @param  array<string, mixed>  $filters
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
     * @param  array<string, mixed>  $filters
     */
    private function reportsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('reports')
            ->whereNull('reports.deleted_at')
            ->whereBetween('reports.submitted_at', [$filters['date_from'], $filters['date_to']]);

        if ($user->hasRole('admin')) {
            if ($user->university_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->join('users as report_reporters', 'report_reporters.id', '=', 'reports.reporter_id')
                    ->where('report_reporters.university_id', $user->university_id);
            }
        } elseif (! $this->isGlobalScope($user)) {
            $query->join('cases', 'cases.report_id', '=', 'reports.id')
                ->join('case_assignments', 'case_assignments.case_id', '=', 'cases.id')
                ->whereNull('cases.deleted_at')
                ->where('case_assignments.satgas_id', $user->id)
                ->where('case_assignments.is_active', true);
        }

        return $this->applyReportScopeFilter($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyReportScopeFilter(Builder $query, User $user, array $filters): Builder
    {
        if ($user->hasRole('admin')) {
            if (! empty($filters['satgas_id'])) {
                return $query->whereExists(function (Builder $assignment) use ($filters): void {
                    $assignment->selectRaw('1')
                        ->from('cases as dashboard_report_cases')
                        ->join(
                            'case_assignments as dashboard_report_assignments',
                            'dashboard_report_assignments.case_id',
                            '=',
                            'dashboard_report_cases.id'
                        )
                        ->whereColumn('dashboard_report_cases.report_id', 'reports.id')
                        ->whereColumn('dashboard_report_cases.registration_number', 'reports.registration_number')
                        ->whereNull('dashboard_report_cases.deleted_at')
                        ->where('dashboard_report_assignments.is_active', true)
                        ->where('dashboard_report_assignments.satgas_id', $filters['satgas_id']);
                });
            }

            if (($filters['assignment_status'] ?? null) === 'unassigned') {
                return $query->whereNotExists(function (Builder $assignment): void {
                    $assignment->selectRaw('1')
                        ->from('cases as dashboard_report_cases')
                        ->join(
                            'case_assignments as dashboard_report_assignments',
                            'dashboard_report_assignments.case_id',
                            '=',
                            'dashboard_report_cases.id'
                        )
                        ->whereColumn('dashboard_report_cases.report_id', 'reports.id')
                        ->whereColumn('dashboard_report_cases.registration_number', 'reports.registration_number')
                        ->whereNull('dashboard_report_cases.deleted_at')
                        ->where('dashboard_report_assignments.is_active', true);
                });
            }
        } elseif ($this->isGlobalScope($user) && ! empty($filters['university_id'])) {
            $query
                ->join('users as dashboard_report_filter_reporters', 'dashboard_report_filter_reporters.id', '=', 'reports.reporter_id')
                ->where('dashboard_report_filter_reporters.university_id', $filters['university_id']);
        }

        return $query;
    }

    /**
     * @return array<int, array{key: string|null, count: int}>
     */
    private function reportPriorityCounts(Builder $query): array
    {
        $priorityQuery = (clone $query)->leftJoin('cases as report_priority_cases', function ($join): void {
            $join->on('report_priority_cases.report_id', '=', 'reports.id')
                ->whereNull('report_priority_cases.deleted_at');
        });

        return $this->groupedCount($priorityQuery, 'report_priority_cases.priority_code');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function casesQuery(User $user, array $filters): Builder
    {
        $query = DB::table('cases')
            ->whereNull('cases.deleted_at')
            ->whereBetween('cases.forwarded_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function investigationsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('investigations')
            ->join('cases', 'cases.id', '=', 'investigations.case_id')
            ->whereNull('cases.deleted_at')
            ->whereBetween('investigations.started_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function recommendationsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('recommendations')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->whereNull('cases.deleted_at')
            ->whereBetween('recommendations.created_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function decisionsQuery(User $user, array $filters): Builder
    {
        $query = DB::table('decisions')
            ->join('recommendations', 'recommendations.id', '=', 'decisions.recommendation_id')
            ->join('cases', 'cases.id', '=', 'recommendations.case_id')
            ->whereNull('cases.deleted_at')
            ->whereBetween('decisions.recorded_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
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

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
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

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function evidencesQuery(User $user, array $filters): Builder
    {
        $query = DB::table('evidences')
            ->join('investigations', 'investigations.id', '=', 'evidences.investigation_id')
            ->join('cases', 'cases.id', '=', 'investigations.case_id')
            ->whereNull('evidences.deleted_at')
            ->whereNull('cases.deleted_at')
            ->whereBetween('evidences.created_at', [$filters['date_from'], $filters['date_to']]);

        return $this->scopeCasesToUser($query, $user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
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
     * @param  array<string, mixed>  $filters
     */
    private function evidencesWithoutFileMetadataQuery(User $user, array $filters): Builder
    {
        return $this->evidencesQuery($user, $filters)
            ->whereNull('evidences.original_filename')
            ->whereNull('evidences.mime_type')
            ->whereNull('evidences.file_size')
            ->whereNull('evidences.checksum_sha256');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scopeCasesToUser(Builder $query, User $user, array $filters): Builder
    {
        if ($this->isGlobalScope($user)) {
            if (! empty($filters['university_id'])) {
                $query
                    ->join('reports as dashboard_case_reports', 'dashboard_case_reports.id', '=', 'cases.report_id')
                    ->join('users as dashboard_case_reporters', 'dashboard_case_reporters.id', '=', 'dashboard_case_reports.reporter_id')
                    ->whereColumn('dashboard_case_reports.registration_number', 'cases.registration_number')
                    ->where('dashboard_case_reporters.university_id', $filters['university_id']);
            }

            return $query;
        }

        if ($user->hasRole('admin')) {
            if ($user->university_id === null) {
                return $query->whereRaw('1 = 0');
            }

            $query
                ->join('reports as campus_reports', 'campus_reports.id', '=', 'cases.report_id')
                ->join('users as campus_reporters', 'campus_reporters.id', '=', 'campus_reports.reporter_id')
                ->whereColumn('campus_reports.registration_number', 'cases.registration_number')
                ->where('campus_reporters.university_id', $user->university_id);

            return $this->applyAdminCaseAssignmentFilter($query, $filters);
        }

        return $query
            ->join('case_assignments', 'case_assignments.case_id', '=', 'cases.id')
            ->where('case_assignments.satgas_id', $user->id)
            ->where('case_assignments.is_active', true);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAdminCaseAssignmentFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['satgas_id'])) {
            return $query->whereExists(function (Builder $assignment) use ($filters): void {
                $assignment->selectRaw('1')
                    ->from('case_assignments as dashboard_case_assignment_filter')
                    ->whereColumn('dashboard_case_assignment_filter.case_id', 'cases.id')
                    ->where('dashboard_case_assignment_filter.is_active', true)
                    ->where('dashboard_case_assignment_filter.satgas_id', $filters['satgas_id']);
            });
        }

        if (($filters['assignment_status'] ?? null) === 'unassigned') {
            return $query->whereNotExists(function (Builder $assignment): void {
                $assignment->selectRaw('1')
                    ->from('case_assignments as dashboard_case_assignment_filter')
                    ->whereColumn('dashboard_case_assignment_filter.case_id', 'cases.id')
                    ->where('dashboard_case_assignment_filter.is_active', true);
            });
        }

        return $query;
    }

    private function assignedCaseCount(User $user, array $filters): int
    {
        $query = $this->operationalCases($this->casesQuery($user, $filters));

        if (! ($user->hasRole('admin') || $this->isGlobalScope($user))) {
            return $this->count($query);
        }

        return $this->count(
            $query->whereExists(function (Builder $assignment): void {
                $assignment->selectRaw('1')
                    ->from('case_assignments as dashboard_assigned_case_filter')
                    ->whereColumn('dashboard_assigned_case_filter.case_id', 'cases.id')
                    ->where('dashboard_assigned_case_filter.is_active', true);
            })
        );
    }

    private function unassignedCaseCount(User $user, array $filters): int
    {
        if (! ($user->hasRole('admin') || $this->isGlobalScope($user))) {
            return 0;
        }

        return $this->count(
            $this->operationalCases($this->casesQuery($user, $filters))
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
        $this->operationalCases($query);

        if ($user->hasRole('admin')) {
            if ($user->university_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->join('reports as assignment_reports', 'assignment_reports.id', '=', 'cases.report_id')
                    ->join('users as assignment_reporters', 'assignment_reporters.id', '=', 'assignment_reports.reporter_id')
                    ->whereColumn('assignment_reports.registration_number', 'cases.registration_number')
                    ->where('assignment_reporters.university_id', $user->university_id);
            }
            if (! empty($filters['satgas_id'])) {
                $query->where('case_assignments.satgas_id', $filters['satgas_id']);
            } elseif (($filters['assignment_status'] ?? null) === 'unassigned') {
                $query->whereRaw('1 = 0');
            }
        } elseif ($this->isGlobalScope($user) && ! empty($filters['university_id'])) {
            $query
                ->join('reports as assignment_campus_reports', 'assignment_campus_reports.id', '=', 'cases.report_id')
                ->join('users as assignment_campus_reporters', 'assignment_campus_reporters.id', '=', 'assignment_campus_reports.reporter_id')
                ->whereColumn('assignment_campus_reports.registration_number', 'cases.registration_number')
                ->where('assignment_campus_reporters.university_id', $filters['university_id']);
        } elseif (! $this->isGlobalScope($user)) {
            $query->where('case_assignments.satgas_id', $user->id);
        }

        return $this->count($query);
    }

    private function operationalCases(Builder $query): Builder
    {
        return $query
            ->whereNull('cases.closed_at')
            ->whereNotExists(function (Builder $status): void {
                $status->selectRaw('1')
                    ->from('case_statuses as operational_case_statuses')
                    ->whereColumn('operational_case_statuses.code', 'cases.status_code')
                    ->whereIn('operational_case_statuses.name', CaseStatusEnum::operationallyTerminalValues());
            });
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
        return $user->hasRole('super_admin');
    }

    private function scopeName(User $user): string
    {
        return $this->isGlobalScope($user)
            ? 'global'
            : ($user->hasRole('admin') ? 'campus' : 'assigned_cases');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    private function formatFilters(array $filters): array
    {
        $formatted = [
            'date_from' => $filters['date_from']->toDateString(),
            'date_to' => $filters['date_to']->toDateString(),
            'granularity' => $filters['granularity'],
        ];

        foreach (['satgas_id', 'assignment_status', 'university_id'] as $filter) {
            if (array_key_exists($filter, $filters)) {
                $formatted[$filter] = $filters[$filter];
            }
        }

        return $formatted;
    }
}
