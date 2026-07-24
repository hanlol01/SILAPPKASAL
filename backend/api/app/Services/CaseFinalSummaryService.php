<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseFinalOutcome;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\Recovery;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CaseCampusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CaseFinalSummaryService
{
    /** @var list<string> */
    public const NARRATIVE_FIELDS = [
        'official_statement',
        'investigation_summary',
        'recommendation_result',
        'decision_result',
        'recovery_result',
        'actions_completed',
        'actions_uncompleted',
        'follow_up_or_referral',
        'closing_explanation',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
        private readonly CaseMutationGuard $caseMutationGuard,
    ) {}

    public function findForCase(CaseRecord $case): ?CaseFinalSummary
    {
        return CaseFinalSummary::query()
            ->where('case_id', $case->id)
            ->first();
    }

    /** @return list<array{code: string, label: string}> */
    public function outcomeOptions(CaseRecord $case): array
    {
        $recovery = $this->latestRecovery($case);
        $terminalStatus = $recovery?->status?->name !== null
            ? RecoveryStatusEnum::tryFrom($recovery->status->name)
            : null;
        $outcomes = $terminalStatus && in_array($terminalStatus, [RecoveryStatusEnum::Completed, RecoveryStatusEnum::Discontinued], true)
            ? CaseFinalOutcome::compatibleWithRecovery($terminalStatus)
            : CaseFinalOutcome::cases();

        return array_map(static fn (CaseFinalOutcome $outcome): array => [
            'code' => $outcome->value,
            'label' => $outcome->label(app()->getLocale()),
        ], $outcomes);
    }

    /** @param array<string, mixed> $data */
    public function create(CaseRecord $case, User $actor, array $data): CaseFinalSummary
    {
        return DB::transaction(function () use ($case, $actor, $data): CaseFinalSummary {
            $case = $this->caseMutationGuard
                ->lockAndAssertMutable($case)
                ->load('report.reporter');
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeManager($actor, $case);
            $this->ensureCaseAcceptsDraft($case);

            if (CaseFinalSummary::query()->where('case_id', $case->id)->exists()) {
                throw $this->unprocessable('A final summary already exists for this Case');
            }

            $this->assertAnonymousIdentityAbsent($case, $data);
            $summary = CaseFinalSummary::query()->create([
                ...$data,
                'case_id' => $case->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->recordAudit(AuditAction::CaseFinalSummaryCreated, $summary, $case, $actor, false);

            return $summary;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CaseFinalSummary $summary, User $actor, array $data): CaseFinalSummary
    {
        return DB::transaction(function () use ($summary, $actor, $data): CaseFinalSummary {
            $case = $this->caseMutationGuard
                ->lockAndAssertMutable($summary->case_id)
                ->load('report.reporter');
            $summary = CaseFinalSummary::query()->whereKey($summary->id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeManager($actor, $case);
            $this->ensureMutable($case, $summary);
            $this->assertAnonymousIdentityAbsent($case, $data);
            $before = [
                'outcome_code' => $summary->outcome_code?->value,
                'published' => $summary->isPublished(),
            ];

            $summary->fill([...$data, 'updated_by' => $actor->id])->save();
            $this->recordAudit(
                AuditAction::CaseFinalSummaryUpdated,
                $summary,
                $case,
                $actor,
                false,
                $before,
            );

            return $summary;
        });
    }

    public function publish(CaseFinalSummary $summary, User $actor): CaseFinalSummary
    {
        return DB::transaction(function () use ($summary, $actor): CaseFinalSummary {
            $case = $this->caseMutationGuard
                ->lockAndAssertMutable($summary->case_id)
                ->load('report.reporter');
            $recovery = $this->latestRecovery($case, true);
            $summary = CaseFinalSummary::query()->whereKey($summary->id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $this->authorizeManager($actor, $case);
            $this->ensureMutable($case, $summary);
            $this->validatePublication($case, $summary, $recovery);

            $summary->forceFill([
                'updated_by' => $actor->id,
                'published_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            $this->recordAudit(AuditAction::CaseFinalSummaryPublished, $summary, $case, $actor, true);

            return $summary;
        });
    }

    public function validatePublication(CaseRecord $case, CaseFinalSummary $summary, ?Recovery $recovery = null): void
    {
        if (
            blank($summary->outcome_code)
            || blank($summary->completion_date)
            || blank($summary->official_statement)
            || blank($summary->closing_explanation)
            || $summary->completion_date->isFuture()
        ) {
            throw $this->unprocessableCode(ApiErrorCode::FinalSummaryPublicationRequired);
        }

        $this->assertAnonymousIdentityAbsent($case, $summary->only(self::NARRATIVE_FIELDS));
        $recovery ??= $this->latestRecovery($case);
        $recoveryStatus = $recovery?->status?->name !== null
            ? RecoveryStatusEnum::tryFrom($recovery->status->name)
            : null;

        if (! $recoveryStatus || ! in_array($recoveryStatus, [RecoveryStatusEnum::Completed, RecoveryStatusEnum::Discontinued], true)) {
            throw $this->unprocessableCode(ApiErrorCode::CaseClosureRecoveryRequired);
        }

        if (! $summary->outcome_code->isCompatibleWithRecovery($recoveryStatus)) {
            throw $this->unprocessableCode(ApiErrorCode::FinalOutcomeIncompatible);
        }
    }

    private function ensureCaseAcceptsDraft(CaseRecord $case): void
    {
        if ($case->isOperationallyTerminal()) {
            throw $this->unprocessableCode(ApiErrorCode::FinalSummaryImmutable);
        }

        if (! in_array($case->status?->name, [CaseStatusEnum::Recovery->value, CaseStatusEnum::Monitoring->value], true)) {
            throw $this->unprocessable('A final summary can only be prepared during Recovery or Monitoring');
        }
    }

    private function ensureMutable(CaseRecord $case, CaseFinalSummary $summary): void
    {
        $this->ensureCaseAcceptsDraft($case);

        if ($summary->isPublished()) {
            throw $this->unprocessableCode(ApiErrorCode::FinalSummaryImmutable);
        }
    }

    private function authorizeManager(User $actor, CaseRecord $case): void
    {
        if (
            ! $actor->is_active
            || ! $actor->hasRole('admin')
            || ! $actor->hasPermission('cases.monitor')
            || ! $this->campusScope->sameCampus($actor, $case)
        ) {
            throw $this->forbidden();
        }
    }

    /** @param array<string, mixed> $values */
    private function assertAnonymousIdentityAbsent(CaseRecord $case, array $values): void
    {
        if ($case->report?->report_type !== 'anonymous' || ! $case->report?->reporter) {
            return;
        }

        $reporter = $case->report->reporter;
        $identityValues = array_filter([
            $reporter->name,
            $reporter->email,
            $reporter->nim,
            $reporter->nip,
            $reporter->phone_number,
            $case->report->reporter_phone_encrypted,
            ...preg_split('/\s+/u', (string) $reporter->name, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ], static fn ($value): bool => is_string($value) && mb_strlen(trim($value)) >= 4);
        $narrative = mb_strtolower(implode("\n", array_map('strval', array_intersect_key($values, array_flip(self::NARRATIVE_FIELDS)))));

        foreach ($identityValues as $identity) {
            if (str_contains($narrative, mb_strtolower(trim((string) $identity)))) {
                throw $this->unprocessableCode(ApiErrorCode::FinalSummaryAnonymousIdentityDetected);
            }
        }
    }

    private function latestRecovery(CaseRecord $case, bool $lock = false): ?Recovery
    {
        $query = Recovery::query()
            ->with('status')
            ->whereHas('decision.recommendation', fn (Builder $query): Builder => $query->where('case_id', $case->id))
            ->latest('id');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /** @param array<string, mixed> $before */
    private function recordAudit(
        AuditAction $action,
        CaseFinalSummary $summary,
        CaseRecord $case,
        User $actor,
        bool $published,
        array $before = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Case,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $summary,
            metadata: [
                'case_number' => $case->case_number,
                'outcome_code' => $summary->outcome_code?->value,
                'published' => $published,
                'result' => 'succeeded',
            ],
            beforeChanges: $before,
            afterChanges: [
                'outcome_code' => $summary->outcome_code?->value,
                'published' => $published,
            ],
        );
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

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }

    private function unprocessableCode(string $errorCode): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __("api.errors.{$errorCode}"),
            'error_code' => $errorCode,
            'errors' => null,
        ], 422));
    }
}
