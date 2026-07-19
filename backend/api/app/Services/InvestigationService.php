<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\InvestigationActivityType;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\Investigation;
use App\Models\InvestigationActivity;
use App\Models\InvestigationStatus;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Notifications\WorkflowDatabaseNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InvestigationService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForCase(CaseRecord $case, User $actor, array $data): Investigation
    {
        $this->authorizeAssignedInvestigator($case, $actor);
        $this->ensureCaseCanStartInvestigation($case);

        return DB::transaction(function () use ($case, $actor, $data): Investigation {
            $case = CaseRecord::query()->with(['status', 'investigation', 'activeAssignments.satgas'])->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedLead($case, $actor);
            $this->ensureCaseCanStartInvestigation($case);

            $status = $this->statusByName(InvestigationStatusEnum::Planning);

            $investigation = Investigation::query()
                ->create([
                    'case_id' => $case->id,
                    'lead_investigator_id' => $actor->id,
                    'status_code' => $status->code,
                    'plan_summary' => $data['plan_summary'],
                    'started_at' => now(),
                ])
                ->load(['case', 'status', 'leadInvestigator', 'activities.investigator', 'activities.stage']);

            $this->recordAudit(
                AuditAction::InvestigationCreated,
                $actor,
                $investigation,
                [
                    'case_id' => $case->id,
                    'case_number' => $case->case_number,
                    'investigation_id' => $investigation->id,
                    'lead_investigator_id' => $investigation->lead_investigator_id,
                    'status_code' => $investigation->status_code,
                ],
                afterChanges: ['status_code' => $investigation->status_code],
            );

            $this->notifyInvestigationCreated($investigation);

            return $investigation;
        });
    }

    /**
     * @return Collection<int, Investigation>
     */
    public function listForCase(CaseRecord $case, User $user): Collection
    {
        if (! $this->canReadMetadata($user) && ! $this->isAssignedInvestigator($case, $user)) {
            throw $this->forbidden();
        }

        return Investigation::query()
            ->where('case_id', $case->id)
            ->with(['case', 'status', 'leadInvestigator'])
            ->withCount('activities')
            ->latest('started_at')
            ->latest('id')
            ->get();
    }

    public function loadForUser(Investigation $investigation, User $user): Investigation
    {
        $relations = ['case', 'status', 'leadInvestigator'];

        if ($this->canReadSensitive($investigation, $user)) {
            $relations[] = 'activities.investigator';
            $relations[] = 'activities.stage';
        } else {
            $investigation->loadCount('activities');
        }

        return $investigation->load($relations);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addActivity(Investigation $investigation, User $actor, array $data): InvestigationActivity
    {
        return DB::transaction(function () use ($investigation, $actor, $data): InvestigationActivity {
            $investigation = Investigation::query()->with(['case.status', 'status'])->whereKey($investigation->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedInvestigator($investigation->case, $actor);
            $this->ensureCaseStillInInvestigation($investigation->case);
            $this->ensureInvestigationOpen($investigation);

            $stageName = (string) $investigation->status?->name;
            $activityType = InvestigationActivityType::from($data['activity_type']);

            if (! in_array($stageName, $activityType->permittedStages(), true)) {
                throw $this->unprocessableCode(
                    ApiErrorCode::InvestigationActivityStageIncompatible,
                    ['stage' => $this->localizedStage($stageName)],
                );
            }

            $activity = $investigation->activities()
                ->create([
                    'investigator_id' => $actor->id,
                    'activity_type' => $data['activity_type'],
                    'investigation_stage_code' => $investigation->status_code,
                    'activity_date' => $data['activity_date'],
                    'description' => $data['description'],
                    'findings' => $data['findings'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ])
                ->load(['investigator', 'stage']);

            $this->recordAudit(
                AuditAction::InvestigationActivityCreated,
                $actor,
                $activity,
                [
                    'case_id' => $investigation->case_id,
                    'case_number' => $investigation->case?->case_number,
                    'investigation_id' => $investigation->id,
                    'activity_id' => $activity->id,
                    'activity_type' => $activity->activity_type,
                    'investigation_stage_code' => $activity->investigation_stage_code,
                ],
                afterChanges: [
                    'activity_type' => $activity->activity_type,
                    'investigation_stage_code' => $activity->investigation_stage_code,
                ],
            );

            return $activity;
        });
    }

    public function updateStatus(Investigation $investigation, User $actor, string $requestedStatus): Investigation
    {
        return DB::transaction(function () use ($investigation, $actor, $requestedStatus): Investigation {
            $investigation = Investigation::query()->with(['case.status', 'case.activeAssignments.satgas', 'status'])->whereKey($investigation->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedInvestigator($investigation->case, $actor);
            $this->ensureCaseStillInInvestigation($investigation->case);
            $this->ensureInvestigationOpen($investigation);

            $nextStatus = $this->resolveStatus($requestedStatus);
            $allowedTransitions = $investigation->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid investigation status transition');
            }

            $currentStageActivityCount = $investigation->activities()
                ->where('investigation_stage_code', $investigation->status_code)
                ->count();

            if ($currentStageActivityCount === 0) {
                throw $this->unprocessableCode(
                    ApiErrorCode::InvestigationStageActivityRequired,
                    ['stage' => $this->localizedStage((string) $investigation->status?->name)],
                );
            }

            $beforeStatusCode = $investigation->status_code;
            $beforeStatusName = $investigation->status?->name;

            $investigation->forceFill([
                'status_code' => $nextStatus->code,
                'completed_at' => $nextStatus->name === InvestigationStatusEnum::Completed->value ? now() : $investigation->completed_at,
            ])->save();

            $investigation = $investigation->load(['case.activeAssignments.satgas', 'status', 'leadInvestigator', 'activities.investigator', 'activities.stage']);

            $this->recordAudit(
                AuditAction::InvestigationStatusChanged,
                $actor,
                $investigation,
                [
                    'case_id' => $investigation->case_id,
                    'case_number' => $investigation->case?->case_number,
                    'investigation_id' => $investigation->id,
                    'from_status' => $beforeStatusName,
                    'to_status' => $nextStatus->name,
                ],
                beforeChanges: ['status_code' => $beforeStatusCode],
                afterChanges: ['status_code' => $investigation->status_code],
            );

            $this->notifyInvestigationStatusChanged($investigation, $beforeStatusName, $nextStatus->name);

            if ($nextStatus->name === InvestigationStatusEnum::Completed->value) {
                $this->notifyInvestigationCompleted($investigation);
            }

            return $investigation;
        });
    }

    /**
     * @return array{current_status: array{code: string|null, name: string|null, description: string|null}, valid_transitions: list<array{code: string, name: string, description: string|null}>, current_stage_activity_count: int, can_transition: bool, reason_code: string|null}
     */
    public function statusOptions(Investigation $investigation): array
    {
        $investigation->loadMissing('status');
        $currentStageActivityCount = $investigation->activities()
            ->where('investigation_stage_code', $investigation->status_code)
            ->count();

        $transitionNames = $investigation->status?->valid_transitions ?? [];
        $statuses = InvestigationStatus::query()
            ->where('is_active', true)
            ->whereIn('name', $transitionNames)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (InvestigationStatus $status): array => [
                'code' => $status->code,
                'name' => $status->name,
                'description' => $status->description,
            ])
            ->values()
            ->all();

        return [
            'current_status' => [
                'code' => $investigation->status?->code,
                'name' => $investigation->status?->name,
                'description' => $investigation->status?->description,
            ],
            'valid_transitions' => $statuses,
            'current_stage_activity_count' => $currentStageActivityCount,
            'can_transition' => $currentStageActivityCount > 0,
            'reason_code' => $currentStageActivityCount > 0
                ? null
                : ApiErrorCode::InvestigationStageActivityRequired,
        ];
    }

    public function canReadSensitive(Investigation $investigation, User $user): bool
    {
        return $user->hasPermission('cases.investigate')
            && $user->hasRole('satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $investigation->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    private function authorizeAssignedInvestigator(CaseRecord $case, User $actor): void
    {
        if (! $actor->is_active || ! $actor->hasPermission('cases.investigate') || ! $actor->hasRole('satgas_ppks') || ! $this->isAssignedInvestigator($case, $actor)) {
            throw $this->forbidden();
        }
    }

    private function ensureCaseCanStartInvestigation(CaseRecord $case): void
    {
        $case->loadMissing(['status', 'investigation']);

        if ($case->status?->name === CaseStatusEnum::Closed->value) {
            throw $this->unprocessable('Closed cases cannot start investigations');
        }

        if ($case->status?->name !== CaseStatusEnum::Investigation->value) {
            throw $this->unprocessable('Case must be in investigation status before starting an investigation');
        }

        if ($case->investigation()->exists()) {
            throw $this->unprocessable('Case already has an investigation');
        }
    }

    private function ensureCaseStillInInvestigation(CaseRecord $case): void
    {
        $case->loadMissing('status');

        if ($case->status?->name === CaseStatusEnum::Closed->value) {
            throw $this->unprocessable('Closed cases cannot be investigated');
        }

        if ($case->status?->name !== CaseStatusEnum::Investigation->value) {
            throw $this->unprocessable('Case is not in investigation status');
        }
    }

    private function ensureInvestigationOpen(Investigation $investigation): void
    {
        if ($investigation->status?->name === InvestigationStatusEnum::Completed->value) {
            throw $this->unprocessable('Completed investigations cannot be changed');
        }
    }

    private function authorizeAssignedLead(CaseRecord $case, User $actor): void
    {
        $isActiveLead = CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $actor->id)
            ->where('is_active', true)
            ->where('is_lead', true)
            ->whereHas('satgas', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('code', 'satgas_ppks')))
            ->exists();

        if (! $isActiveLead) {
            throw $this->forbidden();
        }
    }

    private function isAssignedInvestigator(CaseRecord $case, User $user): bool
    {
        return CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    private function canReadMetadata(User $user): bool
    {
        return ($user->hasPermission('cases.read.metadata') && ($user->hasRole('admin') || $user->hasRole('super_admin')))
            || ($user->hasPermission('cases.read.all') && $user->hasRole('super_admin'));
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $beforeChanges
     * @param array<string, mixed> $afterChanges
     */
    private function recordAudit(
        AuditAction $action,
        User $actor,
        Investigation|InvestigationActivity $subject,
        array $metadata = [],
        array $beforeChanges = [],
        array $afterChanges = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Investigation,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $subject,
            metadata: $metadata,
            beforeChanges: $beforeChanges,
            afterChanges: $afterChanges,
        );
    }

    private function notifyInvestigationCreated(Investigation $investigation): void
    {
        $investigation->loadMissing(['case', 'leadInvestigator']);

        $this->notifyAdmins([
            'notification_type_code' => 'investigation_created',
            'event' => 'investigation_created',
            'title' => 'Investigation created',
            'body' => 'A new investigation has been created.',
            'subject_type' => 'investigation',
            'subject_id' => $investigation->id,
            'case_id' => $investigation->case_id,
            'case_number' => $investigation->case?->case_number,
            'investigation_id' => $investigation->id,
            'lead_investigator_name' => $investigation->leadInvestigator?->name,
        ]);
    }

    private function notifyInvestigationCompleted(Investigation $investigation): void
    {
        $investigation->loadMissing('case');

        $this->notifyAdmins([
            'notification_type_code' => 'investigation_completed',
            'event' => 'investigation_completed',
            'title' => 'Investigation completed',
            'body' => 'An investigation has been completed.',
            'subject_type' => 'investigation',
            'subject_id' => $investigation->id,
            'case_id' => $investigation->case_id,
            'case_number' => $investigation->case?->case_number,
            'investigation_id' => $investigation->id,
        ]);
    }

    private function notifyInvestigationStatusChanged(Investigation $investigation, ?string $fromStatus, string $toStatus): void
    {
        $investigation->loadMissing('case.activeAssignments.satgas');

        $recipients = $investigation->case?->activeAssignments
            ->pluck('satgas')
            ->filter(fn (?User $user): bool => $user?->is_active === true && $user->hasRole('satgas_ppks'))
            ->values() ?? collect();

        Notification::send($recipients, new WorkflowDatabaseNotification([
            'notification_type_code' => 'investigation_status_changed',
            'event' => 'investigation_status_changed',
            'title' => 'Investigation status updated',
            'body' => 'An investigation assigned to your case has a status update.',
            'subject_type' => 'investigation',
            'subject_id' => $investigation->id,
            'case_id' => $investigation->case_id,
            'case_number' => $investigation->case?->case_number,
            'investigation_id' => $investigation->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notifyAdmins(array $payload): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->whereIn('code', ['admin', 'super_admin']))
            ->get();

        Notification::send($recipients, new WorkflowDatabaseNotification($payload));
    }

    private function statusByName(InvestigationStatusEnum $status): InvestigationStatus
    {
        return InvestigationStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveStatus(string $status): InvestigationStatus
    {
        $normalized = mb_strtolower(trim($status));

        return InvestigationStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first() ?? throw $this->unprocessable('Unknown investigation status');
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action',
            'errors' => null,
        ], 403));
    }

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }

    /**
     * @param array<string, string> $replace
     */
    private function unprocessableCode(string $errorCode, array $replace = []): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __("api.errors.{$errorCode}", $replace),
            'error_code' => $errorCode,
            'errors' => null,
        ], 422));
    }

    private function localizedStage(string $stage): string
    {
        return __("api.investigation_stages.{$stage}");
    }
}
