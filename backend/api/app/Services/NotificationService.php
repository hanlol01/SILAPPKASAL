<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseMinute;
use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\Recommendation;
use App\Models\Recovery;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use App\Support\CaseCampusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public const TYPE_CASE_ASSIGNED = 'NOTIF-12';

    public const TYPE_CASE_STATUS_CHANGED = 'NOTIF-13';

    public const TYPE_RECOMMENDATION_SUBMITTED_FOR_REVIEW = 'NOTIF-14';

    /** @deprecated One-release compatibility alias. */
    public const TYPE_RECOMMENDATION_SUBMITTED_TO_LEADER = self::TYPE_RECOMMENDATION_SUBMITTED_FOR_REVIEW;

    public const TYPE_DECISION_FINALIZED = 'NOTIF-15';

    public const TYPE_RECOMMENDATION_CREATED = 'NOTIF-16';

    public const TYPE_RECOMMENDATION_STATUS_CHANGED = 'NOTIF-17';

    public const TYPE_DECISION_CREATED = 'NOTIF-18';

    public const TYPE_DECISION_STATUS_CHANGED = 'NOTIF-19';

    public const TYPE_RECOVERY_CREATED = 'NOTIF-20';

    public const TYPE_RECOVERY_STATUS_CHANGED = 'NOTIF-21';

    public const TYPE_CASE_ASSESSMENT_RECORDED = 'NOTIF-22';

    public const TYPE_RECOMMENDATION_RETURNED = 'NOTIF-23';

    public const TYPE_RECOMMENDATION_APPROVED = 'NOTIF-24';

    public const TYPE_REPORT_DIRECT_CANCELLATION = 'NOTIF-25';

    public const TYPE_REPORT_FORMAL_WITHDRAWAL_SUBMITTED = 'NOTIF-26';

    public const TYPE_REPORT_FORMAL_WITHDRAWAL_CANCELLED = 'NOTIF-27';

    public const TYPE_REPORT_FORMAL_WITHDRAWAL_APPROVED = 'NOTIF-28';

    public const TYPE_REPORT_FORMAL_WITHDRAWAL_REJECTED = 'NOTIF-29';

    public const TYPE_CASE_MINUTE_FINALIZED = 'NOTIF-30';

    public function __construct(private readonly CaseCampusScope $campusScope) {}

    public function caseAssessmentRecorded(CaseRecord $case): void
    {
        $case->loadMissing(['activeAssignments.satgas', 'status', 'report.reporter']);

        $this->send($this->activeAssignedSatgas($case), [
            'notification_type_code' => self::TYPE_CASE_ASSESSMENT_RECORDED,
            'event' => 'case_assessment_recorded',
            'title' => 'Case assessment recorded',
            'body' => 'A risk and priority assessment has been recorded for an assigned case.',
            'subject_type' => 'case',
            'subject_id' => $case->id,
            'case_id' => $case->id,
            'status_code' => $case->status_code,
        ]);
    }

    public function caseMinuteFinalized(CaseMinute $minute, User $actor): void
    {
        $minute->loadMissing(['case.activeAssignments.satgas', 'creator']);
        $case = $minute->case;

        if ($case === null) {
            return;
        }

        $creator = User::query()
            ->whereKey($minute->created_by)
            ->where('is_active', true)
            ->first();
        $recipients = collect([$creator])
            ->merge($this->activeAssignedSatgas($case))
            ->reject(fn (User $recipient): bool => (int) $recipient->id === (int) $actor->id);

        $this->send($recipients, [
            'notification_type_code' => self::TYPE_CASE_MINUTE_FINALIZED,
            'event' => 'case_minute_finalized',
            'title' => 'Berita Acara telah difinalkan',
            'body' => 'Berita Acara untuk Kasus yang Anda tangani telah difinalkan.',
            'subject_type' => 'case_minute',
            'subject_id' => $minute->id,
            'case_id' => $case->id,
            'case_minute_public_id' => $minute->public_id,
            'case_minute_version' => $minute->version,
            'status_code' => $minute->status?->value,
            'finalized_at' => $minute->finalized_at?->toJSON(),
        ]);
    }

    public function reportDirectCancellationCompleted(
        Report $report,
        ReportWithdrawal $withdrawal,
    ): void {
        $this->send($this->campusWithdrawalReviewers($report), [
            'notification_type_code' => self::TYPE_REPORT_DIRECT_CANCELLATION,
            'event' => 'report_direct_cancellation_completed',
            'title' => 'Pengaduan dibatalkan oleh Pelapor',
            'body' => "Pengaduan {$report->registration_number} telah dibatalkan oleh Pelapor sebelum proses penanganan dimulai.",
            'subject_type' => 'report',
            'subject_id' => $report->id,
            'registration_number' => $report->registration_number,
            'withdrawal_public_id' => $withdrawal->public_id,
            'status_code' => $report->status,
        ]);
    }

    public function formalReportWithdrawalSubmitted(
        Report $report,
        ReportWithdrawal $withdrawal,
    ): void {
        $this->send($this->campusWithdrawalReviewers($report), [
            'notification_type_code' => self::TYPE_REPORT_FORMAL_WITHDRAWAL_SUBMITTED,
            'event' => 'report_formal_withdrawal_submitted',
            'title' => 'Permohonan pencabutan menunggu verifikasi',
            'body' => "Pengaduan {$report->registration_number} mengajukan pencabutan dan menunggu verifikasi.",
            'subject_type' => 'report',
            'subject_id' => $report->id,
            'registration_number' => $report->registration_number,
            'withdrawal_public_id' => $withdrawal->public_id,
            'status_code' => $withdrawal->status->value,
        ]);
    }

    public function formalReportWithdrawalCancelled(
        Report $report,
        ReportWithdrawal $withdrawal,
    ): void {
        $this->send($this->campusWithdrawalReviewers($report), [
            'notification_type_code' => self::TYPE_REPORT_FORMAL_WITHDRAWAL_CANCELLED,
            'event' => 'report_formal_withdrawal_cancelled',
            'title' => 'Permohonan pencabutan dibatalkan',
            'body' => "Permohonan pencabutan Pengaduan {$report->registration_number} telah dibatalkan oleh Pelapor.",
            'subject_type' => 'report',
            'subject_id' => $report->id,
            'registration_number' => $report->registration_number,
            'withdrawal_public_id' => $withdrawal->public_id,
            'status_code' => $withdrawal->status->value,
        ]);
    }

    public function formalReportWithdrawalApproved(
        Report $report,
        ReportWithdrawal $withdrawal,
    ): void {
        $recipient = $this->activeReporterOwner($report, $withdrawal);

        $this->send($recipient ? collect([$recipient]) : collect(), [
            'notification_type_code' => self::TYPE_REPORT_FORMAL_WITHDRAWAL_APPROVED,
            'event' => 'report_formal_withdrawal_approved',
            'title' => 'Permohonan pencabutan disetujui',
            'body' => "Permohonan pencabutan Pengaduan {$report->registration_number} telah disetujui.",
            'subject_type' => 'report',
            'registration_number' => $report->registration_number,
            'withdrawal_public_id' => $withdrawal->public_id,
            'status_code' => $withdrawal->status->value,
        ]);
    }

    public function formalReportWithdrawalRejected(
        Report $report,
        ReportWithdrawal $withdrawal,
    ): void {
        $recipient = $this->activeReporterOwner($report, $withdrawal);

        $this->send($recipient ? collect([$recipient]) : collect(), [
            'notification_type_code' => self::TYPE_REPORT_FORMAL_WITHDRAWAL_REJECTED,
            'event' => 'report_formal_withdrawal_rejected',
            'title' => 'Permohonan pencabutan memerlukan perhatian',
            'body' => "Permohonan pencabutan Pengaduan {$report->registration_number} memerlukan perhatian. Silakan buka detail Pengaduan.",
            'subject_type' => 'report',
            'registration_number' => $report->registration_number,
            'withdrawal_public_id' => $withdrawal->public_id,
            'status_code' => $withdrawal->status->value,
        ]);
    }

    /**
     * @param  list<int>  $satgasIds
     */
    public function caseAssigned(CaseRecord $case, array $satgasIds): void
    {
        $recipients = User::query()
            ->whereIn('id', $satgasIds)
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'satgas_ppks'))
            ->get();

        $this->send($recipients, [
            'notification_type_code' => self::TYPE_CASE_ASSIGNED,
            'event' => 'case_assigned',
            'title' => 'Case assigned',
            'body' => 'A case has been assigned to you.',
            'subject_type' => 'case',
            'subject_id' => $case->id,
            'case_id' => $case->id,
            'status_code' => $case->status_code,
        ]);
    }

    public function caseStatusChanged(CaseRecord $case): void
    {
        $case->loadMissing('activeAssignments.satgas');

        $this->send($this->activeAssignedSatgas($case), [
            'notification_type_code' => self::TYPE_CASE_STATUS_CHANGED,
            'event' => 'case_status_changed',
            'title' => 'Case status updated',
            'body' => 'A case assigned to you has a status update.',
            'subject_type' => 'case',
            'subject_id' => $case->id,
            'case_id' => $case->id,
            'status_code' => $case->status_code,
        ]);

        if ($case->status?->name === CaseStatusEnum::Closed->value && $case->report?->reporter?->is_active) {
            $this->send(collect([$case->report->reporter]), [
                'notification_type_code' => self::TYPE_CASE_STATUS_CHANGED,
                'event' => 'case_completed',
                'title' => 'Complaint completed',
                'body' => 'Your complaint process has been completed.',
                'subject_type' => 'case',
                'subject_id' => $case->id,
                'case_id' => $case->id,
                'status_code' => $case->status_code,
            ]);
        }
    }

    public function recommendationSubmittedForReview(Recommendation $recommendation, ?User $actor = null): void
    {
        $recommendation->loadMissing(['status', 'case.report.reporter:id,university_id']);

        if (! in_array($recommendation->status?->name, RecommendationStatusEnum::submittedReviewValues(), true) || ! $recommendation->case) {
            return;
        }

        $this->send($this->campusAdminsForCase($recommendation->case, 'cases.review_recommendation', $actor?->id), [
            'notification_type_code' => self::TYPE_RECOMMENDATION_SUBMITTED_FOR_REVIEW,
            'event' => 'recommendation_submitted_for_review',
            'title' => 'Recommendation submitted for review',
            'body' => 'A recommendation is waiting for Campus Admin review.',
            'subject_type' => 'recommendation',
            'subject_id' => $recommendation->id,
            'case_id' => $recommendation->case_id,
            'recommendation_id' => $recommendation->id,
            'status_code' => $recommendation->status_code,
        ]);
    }

    /** One-release compatibility alias for callers outside this repository. */
    public function recommendationSubmittedToLeader(Recommendation $recommendation): void
    {
        $this->recommendationSubmittedForReview($recommendation);
    }

    public function recommendationCreated(Recommendation $recommendation): void
    {
        $recommendation->loadMissing('status');

        // Draft creation remains an assigned-Satgas concern until submission.
        $this->send(collect(), [
            'notification_type_code' => self::TYPE_RECOMMENDATION_CREATED,
            'event' => 'recommendation_created',
            'title' => 'Recommendation created',
            'body' => 'A recommendation has been created for decision preparation.',
            'subject_type' => 'recommendation',
            'subject_id' => $recommendation->id,
            'case_id' => $recommendation->case_id,
            'recommendation_id' => $recommendation->id,
            'status_code' => $recommendation->status_code,
        ]);
    }

    public function recommendationStatusChanged(Recommendation $recommendation): void
    {
        $recommendation->loadMissing(['status', 'case.activeAssignments.satgas']);

        if (in_array($recommendation->status?->name, RecommendationStatusEnum::submittedReviewValues(), true) || ! $recommendation->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($recommendation->case), [
            'notification_type_code' => self::TYPE_RECOMMENDATION_STATUS_CHANGED,
            'event' => 'recommendation_status_changed',
            'title' => 'Recommendation status updated',
            'body' => 'A recommendation for an assigned case has a status update.',
            'subject_type' => 'recommendation',
            'subject_id' => $recommendation->id,
            'case_id' => $recommendation->case_id,
            'recommendation_id' => $recommendation->id,
            'status_code' => $recommendation->status_code,
        ]);
    }

    public function recommendationReturnedForRevision(Recommendation $recommendation): void
    {
        $recommendation->loadMissing(['status', 'case.activeAssignments.satgas']);

        if ($recommendation->status?->name !== RecommendationStatusEnum::Revised->value || ! $recommendation->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($recommendation->case), [
            'notification_type_code' => self::TYPE_RECOMMENDATION_RETURNED,
            'event' => 'recommendation_returned_for_revision',
            'title' => 'Recommendation returned for revision',
            'body' => 'A recommendation for an assigned case requires revision.',
            'subject_type' => 'recommendation',
            'subject_id' => $recommendation->id,
            'case_id' => $recommendation->case_id,
            'recommendation_id' => $recommendation->id,
            'status_code' => $recommendation->status_code,
        ]);
    }

    public function recommendationApproved(Recommendation $recommendation, ?User $actor = null): void
    {
        $recommendation->loadMissing(['status', 'case.report.reporter:id,university_id']);

        if ($recommendation->status?->name !== RecommendationStatusEnum::Accepted->value) {
            return;
        }

        $this->send($recommendation->case
            ? $this->campusAdminsForCase($recommendation->case, 'cases.record_decision', $actor?->id)
            : collect(), [
                'notification_type_code' => self::TYPE_RECOMMENDATION_APPROVED,
                'event' => 'recommendation_approved',
                'title' => 'Recommendation approved',
                'body' => 'An approved recommendation is ready for decision recording.',
                'subject_type' => 'recommendation',
                'subject_id' => $recommendation->id,
                'case_id' => $recommendation->case_id,
                'recommendation_id' => $recommendation->id,
                'status_code' => $recommendation->status_code,
            ]);
    }

    public function decisionFinalized(Decision $decision): void
    {
        $decision->loadMissing(['status', 'recommendation.case.activeAssignments.satgas']);

        if ($decision->status?->name !== DecisionStatusEnum::Finalized->value || ! $decision->recommendation?->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($decision->recommendation->case), [
            'notification_type_code' => self::TYPE_DECISION_FINALIZED,
            'event' => 'decision_finalized',
            'title' => 'Decision finalized',
            'body' => 'A decision has been finalized for an assigned case.',
            'subject_type' => 'decision',
            'subject_id' => $decision->id,
            'case_id' => $decision->recommendation->case_id,
            'recommendation_id' => $decision->recommendation_id,
            'decision_id' => $decision->id,
            'status_code' => $decision->status_code,
            'outcome_code' => $decision->outcome_code,
            'decision_number' => $decision->decision_number,
        ]);
    }

    public function decisionCreated(Decision $decision): void
    {
        $decision->loadMissing(['recommendation.case.activeAssignments.satgas']);

        if (! $decision->recommendation?->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($decision->recommendation->case), [
            'notification_type_code' => self::TYPE_DECISION_CREATED,
            'event' => 'decision_created',
            'title' => 'Decision recorded',
            'body' => 'A decision has been recorded for an assigned case.',
            'subject_type' => 'decision',
            'subject_id' => $decision->id,
            'case_id' => $decision->recommendation->case_id,
            'recommendation_id' => $decision->recommendation_id,
            'decision_id' => $decision->id,
            'status_code' => $decision->status_code,
            'outcome_code' => $decision->outcome_code,
        ]);
    }

    public function decisionStatusChanged(Decision $decision): void
    {
        $decision->loadMissing(['status', 'recommendation.case.activeAssignments.satgas']);

        if ($decision->status?->name === DecisionStatusEnum::Finalized->value || ! $decision->recommendation?->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($decision->recommendation->case), [
            'notification_type_code' => self::TYPE_DECISION_STATUS_CHANGED,
            'event' => 'decision_status_changed',
            'title' => 'Decision status updated',
            'body' => 'A decision for an assigned case has a status update.',
            'subject_type' => 'decision',
            'subject_id' => $decision->id,
            'case_id' => $decision->recommendation->case_id,
            'recommendation_id' => $decision->recommendation_id,
            'decision_id' => $decision->id,
            'status_code' => $decision->status_code,
            'outcome_code' => $decision->outcome_code,
        ]);
    }

    public function recoveryCreated(Recovery $recovery): void
    {
        $recovery->loadMissing(['decision.recommendation.case.activeAssignments.satgas']);

        if (! $recovery->decision?->recommendation?->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($recovery->decision->recommendation->case), [
            'notification_type_code' => self::TYPE_RECOVERY_CREATED,
            'event' => 'recovery_created',
            'title' => 'Recovery plan created',
            'body' => 'A recovery plan has been created for an assigned case.',
            'subject_type' => 'recovery',
            'subject_id' => $recovery->id,
            'case_id' => $recovery->decision->recommendation->case_id,
            'decision_id' => $recovery->decision_id,
            'recovery_id' => $recovery->id,
            'status_code' => $recovery->status_code,
            'recovery_type_code' => $recovery->recovery_type_code,
        ]);
    }

    public function recoveryStatusChanged(Recovery $recovery): void
    {
        $recovery->loadMissing(['decision.recommendation.case.activeAssignments.satgas']);

        if (! $recovery->decision?->recommendation?->case) {
            return;
        }

        $this->send($this->activeAssignedSatgas($recovery->decision->recommendation->case), [
            'notification_type_code' => self::TYPE_RECOVERY_STATUS_CHANGED,
            'event' => 'recovery_status_changed',
            'title' => 'Recovery status updated',
            'body' => 'A recovery for an assigned case has a status update.',
            'subject_type' => 'recovery',
            'subject_id' => $recovery->id,
            'case_id' => $recovery->decision->recommendation->case_id,
            'decision_id' => $recovery->decision_id,
            'recovery_id' => $recovery->id,
            'status_code' => $recovery->status_code,
            'recovery_type_code' => $recovery->recovery_type_code,
        ]);
    }

    public function recoveryMonitoringCreated(Recovery $recovery, User $actor): void
    {
        $recovery->loadMissing('decision.recommendation.case.report.reporter:id,university_id');
        $case = $recovery->decision?->recommendation?->case;

        if (! $case) {
            return;
        }

        $this->send($this->campusAdminsForCase($case, 'cases.monitor', $actor->id), [
            'notification_type_code' => self::TYPE_RECOVERY_STATUS_CHANGED,
            'event' => 'recovery_monitoring_added',
            'title' => 'Recovery monitoring added',
            'body' => 'A monitoring entry has been added to a Recovery plan.',
            'subject_type' => 'recovery',
            'subject_id' => $recovery->id,
            'case_id' => $case->id,
            'decision_id' => $recovery->decision_id,
            'recovery_id' => $recovery->id,
            'status_code' => $recovery->status_code,
        ]);
    }

    /**
     * @param  iterable<int, User>  $recipients
     * @param  array<string, mixed>  $payload
     */
    private function send(iterable $recipients, array $payload): void
    {
        $safePayload = $this->safePayload($payload);

        $uniqueRecipients = collect($recipients)->filter()->unique('id')->values();
        Notification::send($uniqueRecipients, new WorkflowDatabaseNotification($safePayload));
    }

    /**
     * @return Collection<int, User>
     */
    private function activeAssignedSatgas(CaseRecord $case): Collection
    {
        return $case->activeAssignments
            ->pluck('satgas')
            ->filter(fn (?User $user): bool => $user?->is_active === true && $user->hasRole('satgas_ppks'))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function campusAdminsForCase(CaseRecord $case, string $permission, ?int $excludeUserId = null): Collection
    {
        $universityId = $this->campusScope->caseUniversityId($case);

        if ($universityId === null) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('university_id', $universityId)
            ->when($excludeUserId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $excludeUserId))
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'admin'))
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission($permission))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function campusWithdrawalReviewers(Report $report): Collection
    {
        $report->loadMissing('reporter:id,university_id');
        $universityId = $report->reporter?->university_id;

        if ($universityId === null) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('university_id', $universityId)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'admin'))
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('reports.withdraw.review.own_campus'))
            ->values();
    }

    private function activeReporterOwner(Report $report, ReportWithdrawal $withdrawal): ?User
    {
        if ($report->reporter_id === null || (int) $report->reporter_id !== (int) $withdrawal->requester_id) {
            return null;
        }

        return User::query()
            ->whereKey($report->reporter_id)
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'reporter'))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        $allowedKeys = [
            'notification_type_code',
            'event',
            'title',
            'body',
            'subject_type',
            'subject_id',
            'case_id',
            'recommendation_id',
            'decision_id',
            'recovery_id',
            'status_code',
            'outcome_code',
            'decision_number',
            'recovery_type_code',
            'registration_number',
            'withdrawal_public_id',
            'case_minute_public_id',
            'case_minute_version',
            'finalized_at',
        ];

        return collect($payload)
            ->only($allowedKeys)
            ->all();
    }
}
