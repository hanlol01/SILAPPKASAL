<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseMinuteStatus;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Models\CaseMinute;
use App\Models\CaseRecord;
use App\Models\User;
use App\Policies\CaseMinutePolicy;
use App\Support\ApiErrorCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CaseMinuteService
{
    /** @var list<string> */
    private const NARRATIVE_FIELDS = [
        'internal_summary',
        'anonymized_summary',
        'outcome',
        'follow_up',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseMutationGuard $caseMutationGuard,
        private readonly CaseMinutePolicy $policy,
        private readonly NotificationService $notificationService,
    ) {}

    /** @return Collection<int, CaseMinute> */
    public function listForCase(CaseRecord $case): Collection
    {
        return CaseMinute::query()
            ->with($this->resourceRelations())
            ->where('case_id', $case->id)
            ->orderByDesc('version')
            ->get();
    }

    public function find(CaseMinute $minute): CaseMinute
    {
        return CaseMinute::query()
            ->with($this->resourceRelations())
            ->whereKey($minute->id)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(CaseRecord $case, User $actor, array $data): CaseMinute
    {
        try {
            return DB::transaction(function () use ($case, $actor, $data): CaseMinute {
                $case = $this->lockedEligibleCase($case);
                $actor = $this->freshActor($actor);
                $this->assertCanWrite($actor, $case);
                $minutes = $this->lockedMinutes($case);

                if ($minutes->contains(fn (CaseMinute $minute): bool => $minute->status === CaseMinuteStatus::Draft)) {
                    throw $this->conflict(ApiErrorCode::CaseMinuteDraftExists);
                }

                $minute = new CaseMinute(Arr::only($data, array_merge(['occurred_at'], self::NARRATIVE_FIELDS)));
                $minute->forceFill([
                    'case_id' => $case->id,
                    'version' => ((int) $minutes->max('version')) + 1,
                    'status' => CaseMinuteStatus::Draft,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ])->save();

                $this->recordAudit(AuditAction::CaseMinuteCreated, $minute, $case, $actor);

                return $this->find($minute);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw $this->conflict(ApiErrorCode::CaseMinuteConflict);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(CaseMinute $minute, User $actor, array $data): CaseMinute
    {
        return DB::transaction(function () use ($minute, $actor, $data): CaseMinute {
            $case = $this->lockedEligibleCase($minute->case_id);
            $target = $this->lockedMinute($case, $minute);
            $actor = $this->freshActor($actor);
            $this->assertCanWrite($actor, $case);
            $this->assertDraft($target);
            $this->assertLockVersion($target, (string) $data['lock_version']);

            $changes = Arr::only($data, array_merge(['occurred_at'], self::NARRATIVE_FIELDS));
            if ($changes === []) {
                throw $this->unprocessable(ApiErrorCode::CaseMinuteUpdateRequired);
            }

            $before = ['status' => $target->status?->value, 'version' => $target->version];
            $target->fill($changes);
            $target->forceFill([
                'updated_by' => $actor->id,
                'updated_at' => $this->nextUpdatedAt($target),
            ])->save();
            $this->recordAudit(AuditAction::CaseMinuteUpdated, $target, $case, $actor, $before);

            return $this->find($target);
        });
    }

    public function createRevision(CaseMinute $minute, User $actor): CaseMinute
    {
        try {
            return DB::transaction(function () use ($minute, $actor): CaseMinute {
                $case = $this->lockedEligibleCase($minute->case_id);
                $minutes = $this->lockedMinutes($case);
                $source = $this->lockedMinute($case, $minute);
                $actor = $this->freshActor($actor);
                $this->assertCanWrite($actor, $case);

                if ($source->status !== CaseMinuteStatus::Finalized) {
                    throw $this->unprocessable(ApiErrorCode::CaseMinuteRevisionSourceInvalid);
                }

                if ($minutes->contains(fn (CaseMinute $candidate): bool => $candidate->status === CaseMinuteStatus::Draft)) {
                    throw $this->conflict(ApiErrorCode::CaseMinuteDraftExists);
                }

                $activeFinalized = $minutes
                    ->filter(fn (CaseMinute $candidate): bool => $candidate->status === CaseMinuteStatus::Finalized)
                    ->values();

                if ($activeFinalized->count() !== 1 || (int) $activeFinalized->first()->id !== (int) $source->id) {
                    throw $this->conflict(ApiErrorCode::CaseMinuteRevisionSourceInvalid);
                }

                $revision = new CaseMinute([
                    'occurred_at' => $source->occurred_at,
                    'internal_summary' => $source->internal_summary,
                    'anonymized_summary' => $source->anonymized_summary,
                    'outcome' => $source->outcome,
                    'follow_up' => $source->follow_up,
                ]);
                $revision->forceFill([
                    'case_id' => $case->id,
                    'version' => ((int) $minutes->max('version')) + 1,
                    'status' => CaseMinuteStatus::Draft,
                    'supersedes_id' => $source->id,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ])->save();

                $this->recordAudit(AuditAction::CaseMinuteRevisionCreated, $revision, $case, $actor);

                return $this->find($revision);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw $this->conflict(ApiErrorCode::CaseMinuteConflict);
            }

            throw $exception;
        }
    }

    public function finalize(CaseMinute $minute, User $actor, string $lockVersion): CaseMinute
    {
        return DB::transaction(function () use ($minute, $actor, $lockVersion): CaseMinute {
            $case = $this->lockedEligibleCase($minute->case_id);
            $minutes = $this->lockedMinutes($case);
            $target = $this->lockedMinute($case, $minute);
            $actor = $this->freshActor($actor);
            $this->assertCanFinalize($actor, $target);

            if ($target->status === CaseMinuteStatus::Finalized) {
                return $this->find($target);
            }

            $this->assertDraft($target);
            $this->assertLockVersion($target, $lockVersion);
            $this->assertFinalizationComplete($target);
            $this->assertAnonymizedIdentityAbsent($case, $target->anonymized_summary);

            $activeFinalized = $minutes
                ->filter(fn (CaseMinute $candidate): bool => $candidate->status === CaseMinuteStatus::Finalized)
                ->values();

            if ($activeFinalized->count() > 1) {
                throw $this->conflict(ApiErrorCode::CaseMinuteConflict);
            }

            $previous = $activeFinalized->first();
            if ($previous !== null) {
                $previous->forceFill([
                    'status' => CaseMinuteStatus::Superseded,
                    'updated_by' => $actor->id,
                    'updated_at' => $this->nextUpdatedAt($previous),
                ])->save();
                $this->recordAudit(AuditAction::CaseMinuteSuperseded, $previous, $case, $actor, [
                    'status' => CaseMinuteStatus::Finalized->value,
                    'version' => $previous->version,
                ]);
            }

            $target->forceFill([
                'status' => CaseMinuteStatus::Finalized,
                'updated_by' => $actor->id,
                'updated_at' => $this->nextUpdatedAt($target),
                'finalized_by' => $actor->id,
                'finalized_at' => now('UTC'),
            ])->save();
            $this->recordAudit(AuditAction::CaseMinuteFinalized, $target, $case, $actor);

            DB::afterCommit(function () use ($target, $actor): void {
                $this->notificationService->caseMinuteFinalized($this->find($target), $actor);
            });

            return $this->find($target);
        });
    }

    /** @return array{create: bool} */
    public function caseCapabilities(CaseRecord $case, User $actor): array
    {
        $canCreate = $this->policy->create($actor, $case)
            && $this->caseIsEligible($case)
            && ! $case->pendingFormalWithdrawal()->exists()
            && ! $case->minutes()->where('status', CaseMinuteStatus::Draft->value)->exists();

        return ['create' => $canCreate];
    }

    /** @return array{update: bool, finalize: bool, create_revision: bool} */
    public function minuteCapabilities(CaseMinute $minute, User $actor): array
    {
        $case = $minute->case;
        if ($case === null || ! $this->caseIsEligible($case) || $case->pendingFormalWithdrawal()->exists()) {
            return ['update' => false, 'finalize' => false, 'create_revision' => false];
        }

        $hasDraft = $case->minutes()->where('status', CaseMinuteStatus::Draft->value)->exists();

        return [
            'update' => $minute->status === CaseMinuteStatus::Draft && $this->policy->update($actor, $minute),
            'finalize' => $minute->status === CaseMinuteStatus::Draft && $this->policy->finalize($actor, $minute),
            'create_revision' => $minute->status === CaseMinuteStatus::Finalized
                && ! $hasDraft
                && $this->policy->createRevision($actor, $minute),
        ];
    }

    public function projectionFor(User $actor): string
    {
        return $this->policy->canReadMetadata($actor) ? 'metadata' : 'internal';
    }

    private function lockedEligibleCase(CaseRecord|int $case): CaseRecord
    {
        $lockedCase = $this->caseMutationGuard->lockAndAssertMutable($case)
            ->loadMissing('report.reporter.university');
        $this->assertEligibleStage($lockedCase);

        return $lockedCase;
    }

    /** @return Collection<int, CaseMinute> */
    private function lockedMinutes(CaseRecord $case): Collection
    {
        return CaseMinute::query()
            ->where('case_id', $case->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function lockedMinute(CaseRecord $case, CaseMinute $minute): CaseMinute
    {
        return CaseMinute::query()
            ->where('case_id', $case->id)
            ->whereKey($minute->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function freshActor(User $actor): User
    {
        return User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
    }

    private function assertCanWrite(User $actor, CaseRecord $case): void
    {
        if (! $this->policy->create($actor, $case)) {
            throw $this->forbidden();
        }
    }

    private function assertCanFinalize(User $actor, CaseMinute $minute): void
    {
        if (! $this->policy->finalize($actor, $minute)) {
            throw $this->forbidden();
        }
    }

    private function assertEligibleStage(CaseRecord $case): void
    {
        if (! $this->caseIsEligible($case)) {
            throw $this->unprocessable(ApiErrorCode::CaseMinuteStageUnavailable);
        }
    }

    private function caseIsEligible(CaseRecord $case): bool
    {
        $case->loadMissing('status');

        return in_array($case->status?->name, [
            CaseStatusEnum::Investigation->value,
            CaseStatusEnum::Recommendation->value,
        ], true);
    }

    private function assertDraft(CaseMinute $minute): void
    {
        if ($minute->status !== CaseMinuteStatus::Draft) {
            throw $this->unprocessable(ApiErrorCode::CaseMinuteImmutable);
        }
    }

    private function assertLockVersion(CaseMinute $minute, string $lockVersion): void
    {
        if (! hash_equals($minute->lockVersion(), $lockVersion)) {
            throw $this->conflict(ApiErrorCode::CaseMinuteStale);
        }
    }

    private function assertFinalizationComplete(CaseMinute $minute): void
    {
        foreach (['occurred_at', ...self::NARRATIVE_FIELDS] as $field) {
            if (blank($minute->{$field})) {
                throw $this->unprocessable(ApiErrorCode::CaseMinuteFinalizationRequired);
            }
        }
    }

    private function assertAnonymizedIdentityAbsent(CaseRecord $case, ?string $anonymizedSummary): void
    {
        $reporter = $case->report?->reporter;
        if ($reporter === null || blank($anonymizedSummary)) {
            return;
        }

        $identityValues = array_filter([
            $reporter->name,
            $reporter->email,
            $reporter->nim,
            $reporter->nip,
            $reporter->phone_number,
            $case->report?->reporter_phone_encrypted,
            $case->registration_number,
            $case->report?->registration_number,
            ...preg_split('/\s+/u', (string) $reporter->name, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ], static fn ($value): bool => is_string($value) && mb_strlen(trim($value)) >= 4);
        $narrative = $this->normalizedIdentifier($anonymizedSummary);

        foreach ($identityValues as $identity) {
            $needle = $this->normalizedIdentifier((string) $identity);

            if ($needle !== '' && str_contains($narrative, $needle)) {
                throw $this->unprocessable(ApiErrorCode::CaseMinuteAnonymizedIdentityDetected);
            }
        }
    }

    /** @param array<string, mixed> $before */
    private function recordAudit(
        AuditAction $action,
        CaseMinute $minute,
        CaseRecord $case,
        User $actor,
        array $before = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Case,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $minute,
            metadata: [
                'case_number' => $case->case_number,
                'case_minute_public_id' => $minute->public_id,
                'version' => $minute->version,
                'status' => $minute->status?->value,
                'supersedes_public_id' => $minute->supersedes?->public_id,
                'result' => 'succeeded',
            ],
            beforeChanges: $before,
            afterChanges: [
                'status' => $minute->status?->value,
                'version' => $minute->version,
                'finalized_at' => $minute->finalized_at?->toJSON(),
            ],
        );
    }

    /** @return list<string> */
    private function resourceRelations(): array
    {
        return [
            'case:id,report_id,case_number',
            'case.report:id,reporter_id',
            'case.report.reporter:id,university_id',
            'case.report.reporter.university:id,code',
            'supersedes:id,public_id,version',
            'creator:id',
            'updater:id',
            'finalizer:id',
        ];
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    private function nextUpdatedAt(CaseMinute $minute): \DateTimeInterface
    {
        $next = now('UTC')->startOfSecond();

        if ($minute->updated_at !== null && $minute->updated_at->greaterThanOrEqualTo($next)) {
            return $minute->updated_at->copy()->addSecond();
        }

        return $next;
    }

    private function normalizedIdentifier(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?? $value;
        }

        return preg_replace('/[\p{Z}\p{P}\p{S}_]+/u', '', mb_strtolower(trim($value))) ?? '';
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.forbidden'),
            'error_code' => ApiErrorCode::Forbidden,
            'errors' => null,
        ], 403));
    }

    private function conflict(string $errorCode): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __("api.errors.{$errorCode}"),
            'error_code' => $errorCode,
            'errors' => null,
        ], 409));
    }

    private function unprocessable(string $errorCode): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __("api.errors.{$errorCode}"),
            'error_code' => $errorCode,
            'errors' => null,
        ], 422));
    }
}
