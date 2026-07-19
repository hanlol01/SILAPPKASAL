<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\Recovery;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CaseCampusScope
{
    public function reportUniversityId(Report $report): ?int
    {
        $report->loadMissing('reporter:id,university_id');

        return $report->reporter_id !== null && $report->reporter?->university_id !== null
            ? (int) $report->reporter->university_id
            : null;
    }

    public function caseUniversityId(CaseRecord $case): ?int
    {
        $case->loadMissing('report.reporter:id,university_id');

        if (
            $case->report === null
            || (int) $case->report_id !== (int) $case->report->id
            || (string) $case->registration_number !== (string) $case->report->registration_number
        ) {
            return null;
        }

        return $this->reportUniversityId($case->report);
    }

    public function recommendationUniversityId(Recommendation $recommendation): ?int
    {
        $recommendation->loadMissing('case.report.reporter:id,university_id');

        return $recommendation->case !== null
            && (int) $recommendation->case_id === (int) $recommendation->case->id
                ? $this->caseUniversityId($recommendation->case)
                : null;
    }

    public function decisionUniversityId(Decision $decision): ?int
    {
        $decision->loadMissing('recommendation.case.report.reporter:id,university_id');

        return $decision->recommendation !== null
            && (int) $decision->recommendation_id === (int) $decision->recommendation->id
                ? $this->recommendationUniversityId($decision->recommendation)
                : null;
    }

    public function recoveryUniversityId(Recovery $recovery): ?int
    {
        $recovery->loadMissing('decision.recommendation.case.report.reporter:id,university_id');

        return $recovery->decision !== null
            && (int) $recovery->decision_id === (int) $recovery->decision->id
                ? $this->decisionUniversityId($recovery->decision)
                : null;
    }

    public function sameCampus(User $actor, Report|CaseRecord|Recommendation|Decision|Recovery $subject): bool
    {
        if (! $actor->is_active || ! $actor->hasRole('admin') || $actor->university_id === null) {
            return false;
        }

        $universityId = match (true) {
            $subject instanceof Report => $this->reportUniversityId($subject),
            $subject instanceof CaseRecord => $this->caseUniversityId($subject),
            $subject instanceof Recommendation => $this->recommendationUniversityId($subject),
            $subject instanceof Decision => $this->decisionUniversityId($subject),
            $subject instanceof Recovery => $this->recoveryUniversityId($subject),
        };

        return $universityId !== null && (int) $actor->university_id === $universityId;
    }

    public function canSensitiveOversight(User $actor): bool
    {
        return (bool) config('oversight.cross_campus_sensitive_read', false)
            && $actor->is_active
            && $actor->hasRole('super_admin')
            && $actor->hasPermission('cases.read.sensitive_oversight');
    }

    public function scopeReports(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('super_admin')) {
            return $query;
        }

        if (! $actor->hasRole('admin') || $actor->university_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('reporter', fn (Builder $reporter): Builder => $reporter
            ->where('university_id', $actor->university_id));
    }

    public function scopeCases(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('super_admin')) {
            return $query;
        }

        if (! $actor->hasRole('admin') || $actor->university_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('report', fn (Builder $report): Builder => $report
            ->whereColumn('reports.registration_number', 'cases.registration_number')
            ->whereHas('reporter', fn (Builder $reporter): Builder => $reporter
                ->where('university_id', $actor->university_id)));
    }
}
