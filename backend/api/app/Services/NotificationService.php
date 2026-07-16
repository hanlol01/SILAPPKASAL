<?php

namespace App\Services;

use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\Recommendation;
use App\Models\Recovery;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public const TYPE_CASE_ASSIGNED = 'NOTIF-12';
    public const TYPE_CASE_STATUS_CHANGED = 'NOTIF-13';
    public const TYPE_RECOMMENDATION_SUBMITTED_TO_LEADER = 'NOTIF-14';
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

    public function caseAssessmentRecorded(CaseRecord $case): void
    {
        $case->loadMissing('activeAssignments.satgas');

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

    /**
     * @param list<int> $satgasIds
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
    }

    public function recommendationSubmittedToLeader(Recommendation $recommendation): void
    {
        $recommendation->loadMissing('status');

        if ($recommendation->status?->name !== RecommendationStatusEnum::SubmittedToLeader->value) {
            return;
        }

        $this->send($this->leadershipReviewers(), [
            'notification_type_code' => self::TYPE_RECOMMENDATION_SUBMITTED_TO_LEADER,
            'event' => 'recommendation_submitted_to_leader',
            'title' => 'Recommendation submitted',
            'body' => 'A recommendation has been submitted for decision review.',
            'subject_type' => 'recommendation',
            'subject_id' => $recommendation->id,
            'case_id' => $recommendation->case_id,
            'recommendation_id' => $recommendation->id,
            'status_code' => $recommendation->status_code,
        ]);
    }

    public function recommendationCreated(Recommendation $recommendation): void
    {
        $recommendation->loadMissing('status');

        $this->send($this->decisionManagers(), [
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

        if ($recommendation->status?->name === RecommendationStatusEnum::SubmittedToLeader->value || ! $recommendation->case) {
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

    public function recommendationApproved(Recommendation $recommendation): void
    {
        $recommendation->loadMissing('status');

        if ($recommendation->status?->name !== RecommendationStatusEnum::Accepted->value) {
            return;
        }

        $this->send($this->decisionManagers(), [
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

    /**
     * @param iterable<int, User> $recipients
     * @param array<string, mixed> $payload
     */
    private function send(iterable $recipients, array $payload): void
    {
        $safePayload = $this->safePayload($payload);

        Notification::send($recipients, new WorkflowDatabaseNotification($safePayload));
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
    private function decisionManagers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'admin'))
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('cases.record_decision'))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function leadershipReviewers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'super_admin'))
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('cases.review_recommendation'))
            ->values();
    }

    /**
     * @param array<string, mixed> $payload
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
            'recovery_type_code',
        ];

        return collect($payload)
            ->only($allowedKeys)
            ->all();
    }
}
