<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ContentItem;
use App\Models\ContentReviewDecision;
use Illuminate\Support\Collection;

class ContentEditorialTimelineService
{
    private const MAX_EVENTS = 200;

    /** @return array{events: Collection<int, array<string, mixed>>, truncated: bool} */
    public function forGovernance(ContentItem $item): array
    {
        return $this->timeline($item, false);
    }

    /** @return array{events: Collection<int, array<string, mixed>>, truncated: bool} */
    public function forCampusManagement(ContentItem $item): array
    {
        return $this->timeline($item, true);
    }

    /** @return array{events: Collection<int, array<string, mixed>>, truncated: bool} */
    private function timeline(ContentItem $item, bool $maskInternalActors): array
    {
        $actionStates = $this->actionStates();
        $auditRows = AuditLog::query()
            ->where('subject_type', $item->getMorphClass())
            ->where('subject_id', $item->getKey())
            ->whereIn('action', array_keys($actionStates))
            ->with('actor.role')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_EVENTS + 1)
            ->get();
        $truncated = $auditRows->count() > self::MAX_EVENTS;
        $audits = $auditRows
            ->take(self::MAX_EVENTS)
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $decisionNotes = $this->decisionNotes($item, $audits, $actionStates);

        $events = $audits->map(function (AuditLog $audit) use (
            $actionStates,
            &$decisionNotes,
            $maskInternalActors,
        ): array {
            $state = $actionStates[$audit->action];
            $versionNumber = (int) data_get($audit->metadata, 'version_number', 0);
            $result = data_get($audit->metadata, 'result');
            if ($state === 'featured' && is_string($result) && $result !== '') {
                $state = 'featured_'.$result;
            }

            $decisionKey = $versionNumber.'|'.$state;
            $note = isset($decisionNotes[$decisionKey])
                ? array_shift($decisionNotes[$decisionKey])
                : null;

            return [
                'public_id' => $audit->public_id,
                'action' => $audit->action,
                'state' => $state,
                'actor' => $this->timelineActor($audit, $maskInternalActors),
                'timestamp' => $audit->created_at?->toJSON(),
                'note' => $note,
                'version_number' => $versionNumber ?: null,
                'from_status' => data_get($audit->metadata, 'from_status'),
                'to_status' => data_get($audit->metadata, 'to_status'),
                'result' => $result,
            ];
        });

        return ['events' => $events, 'truncated' => $truncated];
    }

    /**
     * Notes are paired by explicit version and action state. Timestamp is used only
     * for deterministic ordering, with the immutable decision id as its tie-breaker.
     *
     * @param  Collection<int, AuditLog>  $audits
     * @param  array<string, string>  $actionStates
     * @return array<string, list<string|null>>
     */
    private function decisionNotes(ContentItem $item, Collection $audits, array $actionStates): array
    {
        $decisionStates = [
            'review_started' => 'review_started',
            'revision_requested' => 'revision_requested',
            'rejected' => 'rejected',
            'approved' => 'approved',
            'direct_global_published' => 'published',
            'archived' => 'archived',
        ];
        $needed = $audits
            ->map(function (AuditLog $audit) use ($actionStates): string {
                $versionNumber = (int) data_get($audit->metadata, 'version_number', 0);

                return $versionNumber.'|'.$actionStates[$audit->action];
            })
            ->countBy();

        return ContentReviewDecision::query()
            ->whereHas('version', fn ($version) => $version->where('content_item_id', $item->getKey()))
            ->with('version:id,content_item_id,version_number')
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->limit(self::MAX_EVENTS)
            ->get()
            ->sortBy([
                ['decided_at', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (ContentReviewDecision $decision): array => [
                'key' => $decision->version->version_number.'|'.(
                    $decisionStates[$decision->decision_code?->value]
                    ?? $decision->decision_code?->value
                ),
                'note' => $decision->narrative_reason,
            ])
            ->groupBy('key')
            ->map(function (Collection $entries, string $key) use ($needed): array {
                $count = (int) ($needed[$key] ?? 0);
                if ($count === 0) {
                    return [];
                }

                return $entries->take(-$count)->pluck('note')->values()->all();
            })
            ->filter()
            ->all();
    }

    /** @return array{name: string|null, email: string|null, role: string|null, label: string|null} */
    private function timelineActor(AuditLog $audit, bool $maskInternalActors): array
    {
        if ($maskInternalActors && ! in_array($audit->action, $this->campusVisibleActorActions(), true)) {
            return [
                'name' => null,
                'email' => null,
                'role' => null,
                'label' => 'central_team',
            ];
        }

        return [
            'name' => $audit->actor?->name ?? $audit->actor_display_name_safe,
            'email' => $audit->actor?->email,
            'role' => $audit->actor?->role?->code ?? $audit->actor_role_code,
            'label' => $audit->actor_id === null ? 'system' : null,
        ];
    }

    /** @return list<string> */
    private function campusVisibleActorActions(): array
    {
        return [
            AuditAction::ContentItemCreated->value,
            AuditAction::ContentVersionCreated->value,
            AuditAction::ContentDraftUpdated->value,
            AuditAction::ContentSubmitted->value,
        ];
    }

    /** @return array<string, string> */
    private function actionStates(): array
    {
        return [
            AuditAction::ContentItemCreated->value => 'draft_created',
            AuditAction::ContentVersionCreated->value => 'version_created',
            AuditAction::ContentDraftUpdated->value => 'draft_updated',
            AuditAction::ContentSubmitted->value => 'submitted',
            AuditAction::ContentReviewStarted->value => 'review_started',
            AuditAction::ContentRevisionRequested->value => 'revision_requested',
            AuditAction::ContentRejected->value => 'rejected',
            AuditAction::ContentApproved->value => 'approved',
            AuditAction::ContentPublished->value => 'published',
            AuditAction::ContentDirectGlobalPublished->value => 'published',
            AuditAction::ContentArchived->value => 'archived',
            AuditAction::ContentFeaturedPlacementChanged->value => 'featured',
        ];
    }
}
