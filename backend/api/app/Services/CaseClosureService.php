<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Models\CaseAssignment;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Recovery;
use App\Models\User;
use App\Support\ApiErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CaseClosureService
{
    public function __construct(
        private readonly CaseFinalSummaryService $finalSummaryService,
        private readonly CaseWorkflowContextService $workflowContextService,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function close(CaseRecord $case, User $actor): CaseRecord
    {
        return DB::transaction(function () use ($case, $actor): CaseRecord {
            $case = CaseRecord::query()
                ->with(['status', 'report.reporter'])
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($case->closed_at !== null || $case->status?->name === CaseStatusEnum::Closed->value) {
                throw $this->unprocessable('The Case is already closed');
            }

            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();
            $assignment = CaseAssignment::query()
                ->where('case_id', $case->id)
                ->where('satgas_id', $actor->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (
                ! $actor->is_active
                || ! $actor->hasRole('satgas_ppks')
                || ! $actor->hasPermission('cases.close')
                || $assignment === null
            ) {
                throw $this->forbidden();
            }

            $recovery = Recovery::query()
                ->with('status')
                ->whereHas('decision.recommendation', fn (Builder $query): Builder => $query->where('case_id', $case->id))
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $summary = CaseFinalSummary::query()
                ->where('case_id', $case->id)
                ->lockForUpdate()
                ->first();

            if (! $recovery || ! $recovery->status) {
                throw $this->unprocessableCode(ApiErrorCode::CaseClosureRecoveryRequired);
            }

            $recoveryStatus = RecoveryStatusEnum::tryFrom($recovery->status->name);
            $isCompletedPath = $recoveryStatus === RecoveryStatusEnum::Completed;
            $isDiscontinuedPath = $recoveryStatus === RecoveryStatusEnum::Discontinued;

            if (! $isCompletedPath && ! $isDiscontinuedPath) {
                throw $this->unprocessableCode(ApiErrorCode::CaseClosureRecoveryRequired);
            }

            if (
                ($isCompletedPath && $case->status?->name !== CaseStatusEnum::Monitoring->value)
                || ($isDiscontinuedPath && $case->status?->name !== CaseStatusEnum::Recovery->value)
            ) {
                throw $this->unprocessableCode(ApiErrorCode::CaseClosureStageInvalid);
            }

            if ($isCompletedPath && ! $recovery->monitorings()->exists()) {
                throw $this->unprocessableCode(ApiErrorCode::CaseClosureMonitoringRequired);
            }

            if ($isDiscontinuedPath && blank($recovery->discontinuation_reason)) {
                throw $this->unprocessableCode(ApiErrorCode::RecoveryDiscontinuationReasonRequired);
            }

            if (! $summary || ! $summary->isPublished()) {
                throw $this->unprocessableCode(ApiErrorCode::CaseClosureSummaryRequired);
            }

            $this->finalSummaryService->validatePublication($case, $summary, $recovery);
            $closedStatus = CaseStatus::query()
                ->where('name', CaseStatusEnum::Closed->value)
                ->where('is_active', true)
                ->firstOrFail();
            $previousStatusCode = $case->status_code;

            $case->forceFill([
                'status_code' => $closedStatus->code,
                'current_stage' => $closedStatus->workflow_stage,
                'closed_at' => now(),
            ])->save();

            $this->auditLogService->record(
                action: AuditAction::CaseClosed,
                category: AuditCategory::Case,
                severity: AuditSeverity::Info,
                actor: $actor,
                subject: $case,
                metadata: [
                    'case_number' => $case->case_number,
                    'outcome_code' => $summary->outcome_code?->value,
                    'published' => true,
                    'recovery_terminal_type' => $recoveryStatus->value,
                    'result' => 'succeeded',
                ],
                beforeChanges: ['status_code' => $previousStatusCode],
                afterChanges: [
                    'status_code' => $case->status_code,
                    'outcome_code' => $summary->outcome_code?->value,
                    'published' => true,
                ],
            );

            DB::afterCommit(fn (): mixed => $this->notificationService->caseStatusChanged($case));

            $case->load(['status', 'riskLevel', 'priorityLevel', 'activeAssignments.satgas']);
            $case->setAttribute('workflow_context', $this->workflowContextService->forCase($case, $actor));

            return $case;
        });
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
