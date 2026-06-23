<?php

namespace App\Services;

use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\Recommendation;
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

        $this->send($this->decisionManagers(), [
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
            ->whereHas('role', fn (Builder $query): Builder => $query->whereIn('code', ['admin', 'super_admin']))
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('cases.record_decision'))
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
            'status_code',
            'outcome_code',
        ];

        return collect($payload)
            ->only($allowedKeys)
            ->all();
    }
}
