<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\Investigation;
use App\Models\InvestigationActivity;
use App\Models\InvestigationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class InvestigationService
{
    /**
     * @param array<string, mixed> $data
     */
    public function createForCase(CaseRecord $case, User $actor, array $data): Investigation
    {
        $this->authorizeAssignedInvestigator($case, $actor);
        $this->ensureCaseCanStartInvestigation($case);
        $this->ensureAssignedSatgas($case, (int) $data['lead_investigator_id']);

        return DB::transaction(function () use ($case, $data): Investigation {
            $case = CaseRecord::query()->with(['status', 'investigation'])->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $this->ensureCaseCanStartInvestigation($case);

            $status = $this->statusByName(InvestigationStatusEnum::Planning);

            return Investigation::query()
                ->create([
                    'case_id' => $case->id,
                    'lead_investigator_id' => (int) $data['lead_investigator_id'],
                    'status_code' => $status->code,
                    'plan_summary' => $data['plan_summary'] ?? null,
                    'started_at' => now(),
                ])
                ->load(['case', 'status', 'leadInvestigator', 'activities.investigator']);
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
            ->get();
    }

    public function loadForUser(Investigation $investigation, User $user): Investigation
    {
        $relations = ['case', 'status', 'leadInvestigator'];

        if ($this->canReadSensitive($investigation, $user)) {
            $relations[] = 'activities.investigator';
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

            return $investigation->activities()
                ->create([
                    'investigator_id' => $actor->id,
                    'activity_type' => $data['activity_type'],
                    'activity_date' => $data['activity_date'],
                    'description' => $data['description'],
                    'findings' => $data['findings'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ])
                ->load('investigator');
        });
    }

    public function updateStatus(Investigation $investigation, User $actor, string $requestedStatus): Investigation
    {
        return DB::transaction(function () use ($investigation, $actor, $requestedStatus): Investigation {
            $investigation = Investigation::query()->with(['case.status', 'status'])->whereKey($investigation->id)->lockForUpdate()->firstOrFail();

            $this->authorizeAssignedInvestigator($investigation->case, $actor);
            $this->ensureCaseStillInInvestigation($investigation->case);
            $this->ensureInvestigationOpen($investigation);

            $nextStatus = $this->resolveStatus($requestedStatus);
            $allowedTransitions = $investigation->status?->valid_transitions ?? [];

            if (! in_array($nextStatus->name, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid investigation status transition');
            }

            $investigation->forceFill([
                'status_code' => $nextStatus->code,
                'completed_at' => $nextStatus->name === InvestigationStatusEnum::Completed->value ? now() : $investigation->completed_at,
            ])->save();

            return $investigation->load(['case', 'status', 'leadInvestigator', 'activities.investigator']);
        });
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

    private function ensureAssignedSatgas(CaseRecord $case, int $userId): void
    {
        $isAssigned = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'satgas_ppks'))
            ->whereHas('caseAssignments', function (Builder $query) use ($case): void {
                $query->where('case_id', $case->id)
                    ->where('is_active', true);
            })
            ->exists();

        if (! $isAssigned) {
            throw $this->unprocessable('Lead investigator must be an active assigned Satgas user');
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
}
