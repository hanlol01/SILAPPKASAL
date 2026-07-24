<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\ReportWithdrawalAttachment;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CaseCampusScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportWithdrawalReviewService
{
    /** @var list<string> */
    private const INELIGIBLE_CASE_STATUSES = [
        CaseStatusEnum::Decided->value,
        CaseStatusEnum::Recovery->value,
        CaseStatusEnum::Monitoring->value,
        CaseStatusEnum::Closed->value,
        CaseStatusEnum::Withdrawn->value,
        CaseStatusEnum::Escalated->value,
    ];

    public function __construct(
        private readonly CaseCampusScope $campusScope,
        private readonly FormalReportWithdrawalService $formalWithdrawalService,
        private readonly BreakGlassService $breakGlassService,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $filters */
    public function index(User $actor, array $filters): LengthAwarePaginator
    {
        $this->authorizeMonitoring($actor);
        $status = (string) ($filters['status'] ?? ReportWithdrawalStatus::PendingReview->value);

        $query = ReportWithdrawal::query()
            ->with(['report.reporter.university'])
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->whereNotNull('submitted_at')
            ->whereHas('report')
            ->where(function (Builder $availability): void {
                $availability
                    ->whereNull('case_id')
                    ->orWhereHas('case');
            });

        if ($actor->hasRole('admin')) {
            $universityId = (int) $actor->university_id;
            $query->whereHas('report.reporter', fn (Builder $reporter): Builder => $reporter
                ->where('university_id', $universityId));
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->whereRaw(
                "registration_number_snapshot LIKE ? ESCAPE '!'",
                ['%'.$escapedSearch.'%'],
            );
        }

        return $query
            ->oldest('submitted_at')
            ->oldest('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function detail(User $actor, string $publicId, bool $recordView = true): ReportWithdrawal
    {
        $this->authorizeMonitoring($actor);
        $withdrawal = ReportWithdrawal::query()
            ->with([
                'report.reporter.university',
                'report.case.status',
                'case.status',
                'case.recommendation.decision.status',
                'attachments' => fn ($attachments) => $attachments
                    ->oldest('version')
                    ->oldest('id'),
            ])
            ->where('public_id', $publicId)
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->whereNotNull('submitted_at')
            ->first() ?? throw $this->notFound();

        $report = $withdrawal->report;
        $case = $report?->case;
        if (! $report instanceof Report
            || $this->contextIntegrityConflictReason($report, $case, $withdrawal) !== null) {
            throw $this->notFound();
        }

        $withdrawal->setRelation('case', $case);

        if ($actor->hasRole('admin')) {
            $this->authorizeCampusReviewer($actor, $withdrawal->report);
            $withdrawal->setAttribute('review_capabilities', $this->capabilities($actor, $withdrawal));

            if ($recordView) {
                $this->recordWithdrawalAudit(
                    AuditAction::ReportWithdrawalReviewViewed,
                    $actor,
                    $withdrawal,
                    $withdrawal->status->value,
                    $withdrawal->status->value,
                );
            }
        } else {
            $withdrawal->setAttribute('review_capabilities', $this->emptyCapabilities());
        }

        return $withdrawal;
    }

    public function signedDocument(
        User $actor,
        string $publicId,
        string $attachmentPublicId,
    ): StreamedResponse {
        $withdrawal = $this->detail($actor, $publicId, recordView: false);

        if (! $actor->hasRole('admin')) {
            throw $this->forbidden();
        }

        $attachment = $withdrawal->currentSignedAttachment();

        if (! $attachment instanceof ReportWithdrawalAttachment
            || $attachment->public_id !== $attachmentPublicId
            || ! $this->formalWithdrawalService->attachmentStorageIsValid($withdrawal, $attachment)) {
            throw $this->conflict('signed_document_unavailable');
        }

        try {
            $stream = Storage::disk($attachment->disk)->readStream($attachment->path);
        } catch (Throwable) {
            throw $this->conflict('signed_document_unavailable');
        }

        if ($stream === false) {
            throw $this->conflict('signed_document_unavailable');
        }

        $this->recordWithdrawalAudit(
            AuditAction::ReportWithdrawalSignedDocumentReviewed,
            $actor,
            $withdrawal,
            $withdrawal->status->value,
            $withdrawal->status->value,
            $attachment,
        );

        $extension = match ($attachment->server_mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
        $filename = 'surat-pencabutan-v'.$attachment->version.'.'.$extension;

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->server_mime,
            'Content-Length' => (string) $attachment->size,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; sandbox; frame-ancestors 'none'",
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    public function approve(User $actor, string $publicId, int $expectedLockVersion): ReportWithdrawal
    {
        return DB::transaction(function () use ($actor, $publicId, $expectedLockVersion): ReportWithdrawal {
            [$report, $case, $withdrawal] = $this->lockContext($publicId);
            $reviewer = $this->reloadReviewer($actor);
            $this->authorizeCampusReviewer($reviewer, $report);
            $this->assertPendingAndCurrent($withdrawal, $expectedLockVersion);

            $reason = $this->finalStateConflictReason($report, $case, $withdrawal);
            if ($reason !== null) {
                throw $this->conflict($reason);
            }

            $withdrawal->setRelation('attachments', $withdrawal->attachments()->lockForUpdate()->get());
            $attachment = $withdrawal->currentSignedAttachment();
            if (! $attachment instanceof ReportWithdrawalAttachment
                || ! $this->formalWithdrawalService->attachmentStorageIsValid($withdrawal, $attachment)) {
                throw $this->conflict('signed_document_unavailable');
            }

            $withdrawnStatus = $case ? CaseStatus::query()
                ->where('name', CaseStatusEnum::Withdrawn->value)
                ->lockForUpdate()
                ->first() : null;
            if ($case && ! $withdrawnStatus instanceof CaseStatus) {
                throw $this->conflict('withdrawn_status_unavailable');
            }

            $reviewedAt = now();
            $previousReportStatus = $report->status;
            $previousCaseStatus = $case?->status_code;
            $this->breakGlassService->revokeActiveForReportWithdrawal($report, $reviewer);

            $withdrawal->forceFill([
                'status' => ReportWithdrawalStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'approved_at' => $reviewedAt,
                'completed_at' => $reviewedAt,
                'rejection_reason' => null,
                'resubmission_allowed' => false,
                'lock_version' => $withdrawal->lock_version + 1,
            ])->save();
            $report->forceFill([
                'status' => ReportStatus::Withdrawn->value,
                'withdrawn_at' => $reviewedAt,
            ])->save();

            if ($case && $withdrawnStatus) {
                $case->forceFill([
                    'status_code' => $withdrawnStatus->code,
                    'withdrawn_at' => $reviewedAt,
                ])->save();
            }

            $this->recordWithdrawalAudit(
                AuditAction::ReportWithdrawalApproved,
                $reviewer,
                $withdrawal,
                ReportWithdrawalStatus::PendingReview->value,
                ReportWithdrawalStatus::Approved->value,
                $attachment,
            );
            $this->recordLifecycleAudit(
                AuditAction::ReportMarkedWithdrawn,
                AuditCategory::Report,
                $reviewer,
                $report,
                $withdrawal,
                $previousReportStatus,
                ReportStatus::Withdrawn->value,
                'status',
            );
            if ($case) {
                $this->recordLifecycleAudit(
                    AuditAction::CaseMarkedWithdrawn,
                    AuditCategory::Case,
                    $reviewer,
                    $case,
                    $withdrawal,
                    $previousCaseStatus,
                    $withdrawnStatus?->code,
                    'status_code',
                );
            }
            $this->notifications->formalReportWithdrawalApproved($report, $withdrawal);

            return $withdrawal->fresh([
                'report.reporter.university',
                'case.status',
                'attachments',
            ])->setAttribute('review_capabilities', $this->emptyCapabilities());
        });
    }

    public function reject(
        User $actor,
        string $publicId,
        int $expectedLockVersion,
        string $rejectionReason,
        bool $resubmissionAllowed,
    ): ReportWithdrawal {
        return DB::transaction(function () use (
            $actor,
            $publicId,
            $expectedLockVersion,
            $rejectionReason,
            $resubmissionAllowed,
        ): ReportWithdrawal {
            [$report, $case, $withdrawal] = $this->lockContext($publicId);
            $reviewer = $this->reloadReviewer($actor);
            $this->authorizeCampusReviewer($reviewer, $report);
            $this->assertPendingAndCurrent($withdrawal, $expectedLockVersion);

            $reason = $this->contextIntegrityConflictReason($report, $case, $withdrawal);
            if ($reason !== null) {
                throw $this->conflict($reason);
            }

            $reviewedAt = now();
            $withdrawal->forceFill([
                'status' => ReportWithdrawalStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'rejected_at' => $reviewedAt,
                'completed_at' => $reviewedAt,
                'rejection_reason' => $rejectionReason,
                'resubmission_allowed' => $resubmissionAllowed,
                'lock_version' => $withdrawal->lock_version + 1,
            ])->save();

            $this->recordWithdrawalAudit(
                AuditAction::ReportWithdrawalRejected,
                $reviewer,
                $withdrawal,
                ReportWithdrawalStatus::PendingReview->value,
                ReportWithdrawalStatus::Rejected->value,
                resubmissionAllowed: $resubmissionAllowed,
            );
            $this->notifications->formalReportWithdrawalRejected($report, $withdrawal);

            return $withdrawal->fresh([
                'report.reporter.university',
                'case.status',
                'attachments',
            ])->setAttribute('review_capabilities', $this->emptyCapabilities());
        });
    }

    /** @return array<string, bool> */
    private function capabilities(User $actor, ReportWithdrawal $withdrawal): array
    {
        $eligible = $withdrawal->isPendingReview()
            && $this->finalStateConflictReason($withdrawal->report, $withdrawal->case, $withdrawal) === null;
        $attachment = $withdrawal->currentSignedAttachment();
        $documentAvailable = $attachment instanceof ReportWithdrawalAttachment
            && $this->formalWithdrawalService->attachmentStorageIsValid($withdrawal, $attachment);
        $canReview = $actor->is_active
            && $actor->hasRole('admin')
            && $actor->hasPermission('reports.withdraw.review.own_campus')
            && $eligible;
        $canViewDocument = $actor->is_active
            && $actor->hasRole('admin')
            && $actor->hasPermission('reports.withdraw.review.own_campus')
            && $documentAvailable;

        return [
            'can_review' => $canReview,
            'can_approve' => $canReview && $documentAvailable,
            'can_reject' => $canReview,
            'can_view_signed_document' => $canViewDocument,
        ];
    }

    /** @return array<string, bool> */
    private function emptyCapabilities(): array
    {
        return [
            'can_review' => false,
            'can_approve' => false,
            'can_reject' => false,
            'can_view_signed_document' => false,
        ];
    }

    /** @return array{0: Report, 1: CaseRecord|null, 2: ReportWithdrawal} */
    private function lockContext(string $publicId): array
    {
        $reference = ReportWithdrawal::query()
            ->select(['id', 'report_id'])
            ->where('public_id', $publicId)
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->first() ?? throw $this->notFound();
        $report = Report::withTrashed()->whereKey($reference->report_id)->lockForUpdate()->first()
            ?? throw $this->notFound();
        $case = CaseRecord::withTrashed()
            ->with(['status', 'recommendation.decision.status'])
            ->where('report_id', $report->id)
            ->lockForUpdate()
            ->first();
        $withdrawal = ReportWithdrawal::query()
            ->whereKey($reference->id)
            ->where('report_id', $report->id)
            ->lockForUpdate()
            ->first() ?? throw $this->notFound();

        return [$report, $case, $withdrawal];
    }

    private function reloadReviewer(User $actor): User
    {
        return User::query()
            ->with('role.permissions')
            ->whereKey($actor->id)
            ->first() ?? throw $this->forbidden();
    }

    private function authorizeMonitoring(User $actor): void
    {
        if (! $actor->is_active || ! (
            ($actor->hasRole('admin')
                && $actor->university_id !== null
                && $actor->hasPermission('reports.withdraw.review.own_campus'))
            || ($actor->hasRole('super_admin') && $actor->hasPermission('reports.read.all'))
        )) {
            throw $this->forbidden();
        }
    }

    private function authorizeCampusReviewer(User $actor, ?Report $report): void
    {
        if (! $report instanceof Report
            || ! $actor->is_active
            || ! $actor->hasRole('admin')
            || ! $actor->hasPermission('reports.withdraw.review.own_campus')
            || ! $this->campusScope->sameCampus($actor, $report)) {
            throw $this->notFound();
        }
    }

    private function assertPendingAndCurrent(ReportWithdrawal $withdrawal, int $expectedLockVersion): void
    {
        if ($withdrawal->lock_version !== $expectedLockVersion) {
            throw $this->conflict('stale_update');
        }

        if (! $withdrawal->isPendingReview()) {
            throw $this->conflict('state_conflict');
        }
    }

    private function finalStateConflictReason(
        Report $report,
        ?CaseRecord $case,
        ReportWithdrawal $withdrawal,
    ): ?string {
        $integrityReason = $this->contextIntegrityConflictReason($report, $case, $withdrawal);
        if ($integrityReason !== null) {
            return $integrityReason;
        }

        if (in_array($report->status, [
            ReportStatus::Rejected->value,
            ReportStatus::Cancelled->value,
            ReportStatus::Withdrawn->value,
        ], true)) {
            return 'report_state_changed';
        }

        if ($case === null) {
            return $report->status === ReportStatus::Forwarded->value
                || $report->forwarded_at !== null
                    ? null
                    : 'report_state_changed';
        }

        $case->loadMissing(['status', 'recommendation.decision.status']);
        if ($case->escalated_at !== null || in_array($case->status?->name, self::INELIGIBLE_CASE_STATUSES, true)) {
            return 'case_state_changed';
        }

        $decision = $case->recommendation?->decision;
        if ($decision?->finalized_at !== null
            || $decision?->status?->name === DecisionStatusEnum::Finalized->value) {
            return 'decision_finalized';
        }

        return null;
    }

    private function contextIntegrityConflictReason(
        Report $report,
        ?CaseRecord $case,
        ReportWithdrawal $withdrawal,
    ): ?string {
        if ($report->trashed() || $case?->trashed()) {
            return 'record_unavailable';
        }

        if ((int) $withdrawal->report_id !== (int) $report->id
            || $report->reporter_id === null
            || (int) $withdrawal->requester_id !== (int) $report->reporter_id
            || ! is_string($withdrawal->registration_number_snapshot)
            || ! hash_equals($withdrawal->registration_number_snapshot, (string) $report->registration_number)) {
            return 'ownership_changed';
        }

        if ($case === null) {
            return $withdrawal->case_id === null ? null : 'ownership_changed';
        }

        return (int) $withdrawal->case_id === (int) $case->id
            && (int) $case->report_id === (int) $report->id
            && hash_equals((string) $case->registration_number, (string) $report->registration_number)
                ? null
                : 'ownership_changed';
    }

    private function recordWithdrawalAudit(
        AuditAction $action,
        User $actor,
        ReportWithdrawal $withdrawal,
        ?string $fromStatus,
        ?string $toStatus,
        ?ReportWithdrawalAttachment $attachment = null,
        ?bool $resubmissionAllowed = null,
    ): void {
        $this->auditLog->record(
            action: $action,
            category: AuditCategory::Report,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $withdrawal->report,
            metadata: [
                'registration_number' => $withdrawal->registration_number_snapshot,
                'withdrawal_public_id' => $withdrawal->public_id,
                'attachment_public_id' => $attachment?->public_id,
                'attachment_version' => $attachment?->version,
                'document_type' => $attachment?->document_type?->value,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'request_type' => ReportWithdrawalRequestType::FormalWithdrawal->value,
                'resubmission_allowed' => $resubmissionAllowed,
                'result' => AuditResult::Succeeded->value,
            ],
            beforeChanges: $fromStatus === null ? [] : ['status' => $fromStatus],
            afterChanges: $toStatus === null ? [] : ['status' => $toStatus],
        );
    }

    private function recordLifecycleAudit(
        AuditAction $action,
        AuditCategory $category,
        User $actor,
        Report|CaseRecord $subject,
        ReportWithdrawal $withdrawal,
        ?string $fromStatus,
        ?string $toStatus,
        string $deltaKey,
    ): void {
        $this->auditLog->record(
            action: $action,
            category: $category,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $subject,
            metadata: [
                'registration_number' => $withdrawal->registration_number_snapshot,
                'case_number' => $subject instanceof CaseRecord ? $subject->case_number : null,
                'withdrawal_public_id' => $withdrawal->public_id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'result' => AuditResult::Succeeded->value,
            ],
            beforeChanges: [$deltaKey => $fromStatus],
            afterChanges: [$deltaKey => $toStatus],
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

    private function notFound(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Permohonan pencabutan tidak ditemukan.',
            'error_code' => ApiErrorCode::ReportWithdrawalNotFound,
            'errors' => null,
        ], 404));
    }

    private function conflict(string $reasonCode): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.'.ApiErrorCode::ReportWithdrawalConflict),
            'error_code' => ApiErrorCode::ReportWithdrawalConflict,
            'reason_code' => $reasonCode,
            'errors' => null,
        ], 409));
    }
}
