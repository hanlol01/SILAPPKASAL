<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\EvidenceClassification;
use App\Enums\EvidenceCustodyEventType;
use App\Enums\EvidenceStatus;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\Investigation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class EvidenceService
{
    /**
     * @param array<string, mixed> $data
     */
    public function createForInvestigation(Investigation $investigation, User $actor, array $data): Evidence
    {
        $this->authorizeAssignedSatgas($investigation, $actor);
        $this->ensureInvestigationCanAcceptEvidence($investigation);

        return DB::transaction(function () use ($investigation, $actor, $data): Evidence {
            $investigation = Investigation::query()->with('case.status')->whereKey($investigation->id)->lockForUpdate()->firstOrFail();

            $this->ensureInvestigationCanAcceptEvidence($investigation);
            $evidenceType = $this->activeEvidenceType($data['evidence_type_code']);

            $evidence = Evidence::query()->create([
                'investigation_id' => $investigation->id,
                'evidence_type_code' => $evidenceType->code,
                'submitted_by' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source' => $data['source'] ?? null,
                'collected_at' => $data['collected_at'] ?? null,
                'classification' => $data['classification'] ?? EvidenceClassification::Confidential->value,
                'status' => EvidenceStatus::Registered->value,
                'original_filename' => $data['original_filename'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'checksum_sha256' => $data['checksum_sha256'] ?? null,
            ]);

            $this->recordStatusHistory($evidence, null, EvidenceStatus::Registered->value, $actor);
            $this->recordCustodyEvent($evidence, EvidenceCustodyEventType::Registered, $actor, [
                'evidence_type_code' => $evidenceType->code,
                'classification' => $evidence->classification,
            ]);

            return $evidence->load($this->detailRelations());
        });
    }

    /**
     * @return Collection<int, Evidence>
     */
    public function listForInvestigation(Investigation $investigation, User $user): Collection
    {
        $this->authorizeAssignedSatgas($investigation, $user);

        return Evidence::query()
            ->where('investigation_id', $investigation->id)
            ->with($this->summaryRelations())
            ->latest()
            ->get();
    }

    public function loadForUser(Evidence $evidence, User $user): Evidence
    {
        $evidence->loadMissing('investigation.case');
        $this->authorizeAssignedSatgas($evidence->investigation, $user);

        return $evidence->load($this->detailRelations());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Evidence $evidence, User $actor, array $data): Evidence
    {
        $evidence->loadMissing('investigation.case.status');
        $this->authorizeAssignedSatgas($evidence->investigation, $actor);
        $this->ensureEvidenceOpen($evidence);

        return DB::transaction(function () use ($evidence, $actor, $data): Evidence {
            $evidence = Evidence::query()->with('investigation.case.status')->whereKey($evidence->id)->lockForUpdate()->firstOrFail();

            $this->ensureEvidenceOpen($evidence);

            if (isset($data['evidence_type_code'])) {
                $data['evidence_type_code'] = $this->activeEvidenceType($data['evidence_type_code'])->code;
            }

            $evidence->fill($data)->save();
            $this->recordCustodyEvent($evidence, EvidenceCustodyEventType::MetadataUpdated, $actor, [
                'metadata_updated' => true,
            ]);

            return $evidence->load($this->detailRelations());
        });
    }

    public function updateStatus(Evidence $evidence, User $actor, string $nextStatus): Evidence
    {
        $evidence->loadMissing('investigation.case.status');
        $this->authorizeAssignedSatgas($evidence->investigation, $actor);

        return DB::transaction(function () use ($evidence, $actor, $nextStatus): Evidence {
            $evidence = Evidence::query()->with('investigation.case.status')->whereKey($evidence->id)->lockForUpdate()->firstOrFail();

            $this->ensureEvidenceOpen($evidence);

            $allowedTransitions = EvidenceStatus::transitions()[$evidence->status] ?? [];

            if (! in_array($nextStatus, $allowedTransitions, true)) {
                throw $this->unprocessable('Invalid evidence status transition');
            }

            $fromStatus = $evidence->status;
            $evidence->forceFill(['status' => $nextStatus])->save();

            $this->recordStatusHistory($evidence, $fromStatus, $nextStatus, $actor);
            $this->recordCustodyEvent($evidence, EvidenceCustodyEventType::StatusChanged, $actor, [
                'from_status' => $fromStatus,
                'to_status' => $nextStatus,
                'verified_semantics' => $nextStatus === EvidenceStatus::Verified->value
                    ? 'metadata_reviewed_admin_complete_not_forensic_authenticity'
                    : null,
            ]);

            if ($nextStatus === EvidenceStatus::Verified->value) {
                $this->recordCustodyEvent($evidence, EvidenceCustodyEventType::Reviewed, $actor, [
                    'meaning' => 'metadata_reviewed_admin_complete_not_forensic_authenticity',
                ]);
            }

            return $evidence->load($this->detailRelations());
        });
    }

    /**
     * @return Collection<int, \App\Models\EvidenceCustodyEvent>
     */
    public function listCustodyEvents(Evidence $evidence, User $user): Collection
    {
        $evidence = $this->loadForUser($evidence, $user);

        return $evidence->custodyEvents()
            ->with('actor')
            ->oldest('event_at')
            ->oldest()
            ->get();
    }

    private function ensureInvestigationCanAcceptEvidence(Investigation $investigation): void
    {
        $investigation->loadMissing('case.status');

        if ($investigation->case?->status?->name === CaseStatusEnum::Closed->value) {
            throw $this->unprocessable('Evidence cannot be added to a closed case');
        }
    }

    private function ensureEvidenceOpen(Evidence $evidence): void
    {
        if ($evidence->status === EvidenceStatus::Archived->value) {
            throw $this->unprocessable('Archived evidence cannot be changed');
        }
    }

    private function activeEvidenceType(string $code): EvidenceType
    {
        return EvidenceType::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first() ?? throw $this->unprocessable('Unknown evidence type');
    }

    private function authorizeAssignedSatgas(Investigation $investigation, User $actor): void
    {
        if (! $actor->is_active || ! $actor->hasPermission('evidence.upload') || ! $actor->hasPermission('evidence.view.case') || ! $actor->hasRole('satgas_ppks')) {
            throw $this->forbidden();
        }

        $isAssigned = \App\Models\CaseAssignment::query()
            ->where('case_id', $investigation->case_id)
            ->where('satgas_id', $actor->id)
            ->where('is_active', true)
            ->exists();

        if (! $isAssigned) {
            throw $this->forbidden();
        }
    }

    private function recordStatusHistory(Evidence $evidence, ?string $fromStatus, string $toStatus, User $actor): void
    {
        $evidence->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function recordCustodyEvent(Evidence $evidence, EvidenceCustodyEventType $eventType, User $actor, array $details = []): void
    {
        $evidence->custodyEvents()->create([
            'actor_id' => $actor->id,
            'event_type' => $eventType->value,
            'event_at' => now(),
            'details' => $details === [] ? null : $details,
        ]);
    }

    /**
     * @return list<string>
     */
    private function summaryRelations(): array
    {
        return [
            'investigation.case',
            'evidenceType',
            'submitter',
        ];
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            ...$this->summaryRelations(),
            'statusHistories.changedBy',
            'custodyEvents.actor',
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
