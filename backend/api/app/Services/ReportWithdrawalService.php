<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Enums\ReporterSafeStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\User;
use App\Policies\ReportPolicy;
use App\Support\ApiErrorCode;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ReportWithdrawalService
{
    public function __construct(
        private readonly ReportPolicy $reportPolicy,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications,
        private readonly FormalReportWithdrawalService $formalWithdrawalService,
    ) {}

    /**
     * @return array{withdrawal: ReportWithdrawal, report: Report, capabilities: array<string, mixed>}
     */
    public function cancelDirectly(User $actor, string $registrationNumber, string $reason): array
    {
        return DB::transaction(function () use ($actor, $registrationNumber, $reason): array {
            $report = Report::query()
                ->where('registration_number', $registrationNumber)
                ->whereNotNull('reporter_id')
                ->where('reporter_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $report) {
                throw $this->notFound();
            }

            if (! $this->reportPolicy->cancel($actor, $report)) {
                throw $this->forbidden();
            }

            $case = CaseRecord::query()
                ->where('report_id', $report->id)
                ->lockForUpdate()
                ->first();

            $activeWithdrawal = ReportWithdrawal::query()
                ->where('report_id', $report->id)
                ->whereIn('status', ReportWithdrawalStatus::activeValues())
                ->lockForUpdate()
                ->first();

            $blockReason = $this->cancellationBlockReason(
                $report,
                $actor,
                $case !== null,
                $activeWithdrawal
            );

            if ($blockReason !== null) {
                throw $this->conflict($blockReason);
            }

            $previousStatus = $report->status;
            $completedAt = now();

            $withdrawal = ReportWithdrawal::query()->create([
                'report_id' => $report->id,
                'case_id' => null,
                'requester_id' => $actor->id,
                'request_type' => ReportWithdrawalRequestType::EarlyCancellation,
                'status' => ReportWithdrawalStatus::Completed,
                'reason' => $reason,
                'previous_report_status' => $previousStatus,
                'previous_case_status' => null,
                'resubmission_allowed' => false,
                'cancelled_at' => $completedAt,
                'completed_at' => $completedAt,
                'lock_version' => 0,
            ]);

            $report->forceFill([
                'status' => ReportStatus::Cancelled->value,
                'cancelled_at' => $completedAt,
            ])->save();

            $this->auditLog->record(
                AuditAction::ReportDirectCancellationCompleted,
                AuditCategory::Report,
                AuditSeverity::Info,
                actor: $actor,
                subject: $report,
                metadata: [
                    'registration_number' => $report->registration_number,
                    'withdrawal_public_id' => $withdrawal->public_id,
                    'request_type' => ReportWithdrawalRequestType::EarlyCancellation->value,
                    'from_status' => $previousStatus,
                    'to_status' => ReportStatus::Cancelled->value,
                    'result' => AuditResult::Succeeded->value,
                ],
                beforeChanges: ['status' => $previousStatus],
                afterChanges: ['status' => ReportStatus::Cancelled->value],
                result: AuditResult::Succeeded,
            );

            $this->notifications->reportDirectCancellationCompleted($report, $withdrawal);

            $report->setAttribute('portal_status', ReporterSafeStatus::forReport($report)->value);
            $report->setRelation('activeWithdrawal', null);

            return [
                'withdrawal' => $withdrawal,
                'report' => $report,
                'capabilities' => $this->capabilities($report, $actor),
            ];
        });
    }

    /**
     * @return array{
     *     can_cancel: bool,
     *     can_request_withdrawal: bool,
     *     cancellation_block_reason_code: string|null,
     *     withdrawal_block_reason_code: string|null,
     *     active_withdrawal: array<string, mixed>|null
     * }
     */
    public function capabilities(Report $report, User $actor): array
    {
        $report->loadMissing([
            'case.status',
            'case.recommendation.decision.status',
        ]);

        if (! $report->relationLoaded('activeWithdrawal')) {
            $report->load('activeWithdrawal.attachments');
        } elseif ($report->activeWithdrawal !== null) {
            $report->activeWithdrawal->loadMissing('attachments');
        }

        $activeWithdrawal = $report->activeWithdrawal;
        $blockReason = $this->cancellationBlockReason(
            $report,
            $actor,
            $report->case !== null,
            $activeWithdrawal
        );

        $formalBlockReason = $this->formalWithdrawalService->formalBlockReason(
            $report,
            $actor,
            $report->case,
            $activeWithdrawal,
        );
        $activeAttachment = $activeWithdrawal?->currentSignedAttachment();

        return [
            'can_cancel' => $blockReason === null,
            'can_request_withdrawal' => $formalBlockReason === null,
            'cancellation_block_reason_code' => $blockReason,
            'withdrawal_block_reason_code' => $formalBlockReason,
            'active_withdrawal' => $activeWithdrawal ? [
                'withdrawal_reference' => $activeWithdrawal->public_id,
                'request_type' => $activeWithdrawal->request_type->value,
                'status' => $activeWithdrawal->status->value,
                'lock_version' => $activeWithdrawal->lock_version,
                'created_at' => $activeWithdrawal->created_at?->toJSON(),
                'draft_document_viewed_at' => $activeWithdrawal->draft_document_viewed_at?->toJSON(),
                'submitted_at' => $activeWithdrawal->submitted_at?->toJSON(),
                'has_signed_document' => $activeAttachment !== null,
                'latest_attachment' => $activeAttachment ? [
                    'attachment_reference' => $activeAttachment->public_id,
                    'document_type' => $activeAttachment->document_type->value,
                    'version' => $activeAttachment->version,
                    'mime_type' => $activeAttachment->server_mime,
                    'size' => $activeAttachment->size,
                    'uploaded_at' => $activeAttachment->created_at?->toJSON(),
                ] : null,
                'capabilities' => $this->formalWithdrawalService
                    ->withdrawalCapabilities($activeWithdrawal, $actor),
            ] : null,
        ];
    }

    private function cancellationBlockReason(
        Report $report,
        User $actor,
        bool $hasCase,
        ?ReportWithdrawal $activeWithdrawal,
    ): ?string {
        if (! $this->reportPolicy->cancel($actor, $report)) {
            return 'ownership_unavailable';
        }

        if (! (bool) config('withdrawal.early_cancellation_enabled', false)) {
            return 'feature_disabled';
        }

        if ($activeWithdrawal !== null) {
            return 'active_request';
        }

        if (in_array($report->status, [
            ReportStatus::Rejected->value,
            ReportStatus::Cancelled->value,
            ReportStatus::Withdrawn->value,
        ], true)) {
            return 'terminal_state';
        }

        if ($report->status !== ReportStatus::Submitted->value
            || $report->forwarded_at !== null
            || $hasCase) {
            return 'already_processed';
        }

        return null;
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

    private function notFound(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.portal_report_not_found'),
            'error_code' => ApiErrorCode::PortalReportNotFound,
            'errors' => null,
        ], 404));
    }

    private function conflict(string $reasonCode): HttpResponseException
    {
        $errorCode = $reasonCode === 'feature_disabled'
            ? ApiErrorCode::ReportCancellationFeatureDisabled
            : ApiErrorCode::ReportCancellationConflict;

        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __("api.errors.{$errorCode}"),
            'error_code' => $errorCode,
            'errors' => null,
        ], 409));
    }
}
