<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Models\CaseAssignment;
use App\Models\Decision;
use App\Models\Recovery;
use App\Models\RecoveryMonitoring;
use App\Models\RecoveryStatus;
use App\Models\RecoveryType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class RecoveryService
{
    /**
     * @param array<string, mixed> $data
     */
    public function createForDecision(Decision $decision, User $actor, array $data): Recovery
    {
        $this->authorizeRecoveryManager($actor);
        $this->ensureDecisionCanReceiveRecovery($decision);

        return DB::transaction(function () use ($decision, $actor, $data): Recovery {
            $decision = Decision::query()
                ->with(['status', 'recommendation.case.status'])
                ->whereKey($decision->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDecisionCanReceiveRecovery($decision);
            $recoveryType = $this->activeRecoveryType($data['recovery_type_code']);
            $status = $this->statusByName(RecoveryStatusEnum::Planned);

            $recovery = Recovery::query()->create([
                'decision_id' => $decision->id,
                'recovery_type_code' => $recoveryType->code,
                'status_code' => $status->code,
                'created_by' => $actor->id,
                'recovery_plan' => $data['recovery_plan'],
                'support_needs' => $data['support_needs'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recordStatusHistory($recovery, null, $status->code, $actor);

            return $recovery->load($this->detailRelations());
        });
    }

    /**
     * @return Collection<int, Recovery>
     */
    public function listForDecision(Decision $decision, User $user): Collection
    {
        $decision->loadMissing('recommendation.case.status');

        if (! $this->canManageRecovery($user) && ! $this->isAssignedToDecisionCase($decision, $user)) {
            throw $this->forbidden();
        }

        return Recovery::query()
            ->where('decision_id', $decision->id)
            ->with($this->detailRelations())
            ->latest()
            ->get();
    }

    public function loadForUser(Recovery $recovery, User $user): Recovery
    {
        $recovery->loadMissing('decision.recommendation.case.status');

        if (! $this->canManageRecovery($user) && ! $this->isAssignedToDecisionCase($recovery->decision, $user)) {
            throw $this->forbidden();
        }

        return $recovery->load($this->detailRelations());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Recovery $recovery, User $actor, array $data): Recovery
    {
        $this->authorizeRecoveryManager($actor);

        return DB::transaction(function () use ($recovery, $data): Recovery {
            $recovery = Recovery::query()->with('status')->whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            $this->ensureRecoveryOpen($recovery);

            if (isset($data['recovery_type_code'])) {
                $data['recovery_type_code'] = $this->activeRecoveryType($data['recovery_type_code'])->code;
            }

            $recovery->fill($data)->save();

            return $recovery->load($this->detailRelations());
        });
    }

    public function updateStatus(Recovery $recovery, User $actor, string $requestedStatus): Recovery
    {
        $this->authorizeRecoveryManager($actor);

        return DB::transaction(function () use ($recovery, $actor, $requestedStatus): Recovery {
            $recovery = Recovery::query()->with('status')->whereKey($recovery->id)->lockForUpdate()->firstOrFail();
            $this->ensureRecoveryOpen($recovery);

            $nextStatus = $this->resolveStatus($requestedStatus);
            $allowedTransitions = $recovery->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid recovery status transition');
            }

            $fromStatusCode = $recovery->status_code;
            $timestamps = [
                'status_code' => $nextStatus->code,
            ];

            if ($nextStatus->name === RecoveryStatusEnum::Ongoing->value) {
                $timestamps['started_at'] = $recovery->started_at ?? now();
            }

            if ($nextStatus->name === RecoveryStatusEnum::Completed->value) {
                $timestamps['completed_at'] = now();
            }

            if ($nextStatus->name === RecoveryStatusEnum::Discontinued->value) {
                $timestamps['discontinued_at'] = now();
            }

            $recovery->forceFill($timestamps)->save();
            $this->recordStatusHistory($recovery, $fromStatusCode, $nextStatus->code, $actor);

            return $recovery->load($this->detailRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createMonitoring(Recovery $recovery, User $actor, array $data): RecoveryMonitoring
    {
        $recovery = $this->loadForUser($recovery, $actor);

        if (! $this->canManageRecovery($actor) && ! $this->isAssignedToDecisionCase($recovery->decision, $actor)) {
            throw $this->forbidden();
        }

        if ($recovery->status?->name !== RecoveryStatusEnum::Ongoing->value) {
            throw $this->unprocessable('Monitoring can only be created for ongoing recovery');
        }

        return RecoveryMonitoring::query()->create([
            'recovery_id' => $recovery->id,
            'monitor_id' => $actor->id,
            'monitoring_date' => $data['monitoring_date'],
            'condition_summary' => $data['condition_summary'],
            'follow_up_plan' => $data['follow_up_plan'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['monitor']);
    }

    /**
     * @return Collection<int, RecoveryMonitoring>
     */
    public function listMonitoring(Recovery $recovery, User $user): Collection
    {
        $recovery = $this->loadForUser($recovery, $user);

        return $recovery->monitorings()
            ->with('monitor')
            ->latest('monitoring_date')
            ->latest()
            ->get();
    }

    private function ensureDecisionCanReceiveRecovery(Decision $decision): void
    {
        $decision->loadMissing(['status', 'recommendation.case.status']);

        if ($decision->status?->name !== DecisionStatusEnum::Finalized->value) {
            throw $this->unprocessable('Recovery requires a finalized decision');
        }

        if ($decision->recommendation?->case?->trashed()) {
            throw $this->unprocessable('Recovery cannot be created for a deleted case');
        }

        if ($decision->recommendation?->case?->status?->name === CaseStatusEnum::Closed->value) {
            throw $this->unprocessable('Recovery cannot be created for a closed case');
        }
    }

    private function ensureRecoveryOpen(Recovery $recovery): void
    {
        if (in_array($recovery->status?->name, RecoveryStatusEnum::terminalValues(), true)) {
            throw $this->unprocessable('Terminal recoveries cannot be changed');
        }
    }

    private function activeRecoveryType(string $code): RecoveryType
    {
        return RecoveryType::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first() ?? throw $this->unprocessable('Unknown recovery type');
    }

    private function statusByName(RecoveryStatusEnum $status): RecoveryStatus
    {
        return RecoveryStatus::query()
            ->where('name', $status->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveStatus(string $status): RecoveryStatus
    {
        $normalized = mb_strtolower(trim($status));

        return RecoveryStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first() ?? throw $this->unprocessable('Unknown recovery status');
    }

    private function recordStatusHistory(Recovery $recovery, ?string $fromStatusCode, string $toStatusCode, User $actor): void
    {
        $recovery->statusHistories()->create([
            'from_status_code' => $fromStatusCode,
            'to_status_code' => $toStatusCode,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    private function authorizeRecoveryManager(User $actor): void
    {
        if (! $this->canManageRecovery($actor)) {
            throw $this->forbidden();
        }
    }

    private function canManageRecovery(User $user): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.monitor')
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }

    private function isAssignedToDecisionCase(Decision $decision, User $user): bool
    {
        return $user->is_active
            && $user->hasPermission('cases.monitor')
            && $user->hasRole('satgas_ppks')
            && CaseAssignment::query()
                ->where('case_id', $decision->recommendation?->case_id)
                ->where('satgas_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'decision.recommendation.case.status',
            'recoveryType',
            'status',
            'creator',
            'statusHistories.fromStatus',
            'statusHistories.toStatus',
            'statusHistories.changedBy',
            'monitorings.monitor',
        ];
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
}
