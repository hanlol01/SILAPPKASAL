<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\ReportWithdrawalDocumentType;
use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Models\ReportWithdrawalAttachment;
use App\Models\User;
use App\Policies\ReportPolicy;
use App\Support\ApiErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FormalReportWithdrawalService
{
    private const FILE_DISK = 'withdrawal';

    private const DRAFT_EXAMPLE_TEMPLATE_PATH = 'templates/withdrawals/contoh_draft.pdf';

    private const DRAFT_DOCUMENT_MIME = 'application/pdf';

    private const DRAFT_DOCUMENT_FILENAME = 'Surat Pernyataan Permohonan Penghentian Penanganan Laporan.pdf';

    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    private const MAX_IMAGE_PIXELS = 40_000_000;

    /** @var array<string, string> */
    private const EXTENSION_BY_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** @var array<string, list<string>> */
    private const CLIENT_EXTENSIONS_BY_MIME = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

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
        private readonly ReportPolicy $reportPolicy,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications,
        private readonly WithdrawalDraftPdfConverter $draftPdfConverter,
    ) {}

    /**
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    public function create(User $actor, string $registrationNumber, string $reason): array
    {
        $this->authorizeActor($actor);

        try {
            return DB::transaction(function () use ($actor, $registrationNumber, $reason): array {
                $report = $this->lockOwnedReport($actor, $registrationNumber);
                $case = $this->lockCaseForReport($report);
                $activeWithdrawal = $this->lockActiveWithdrawal($report);
                $blockReason = $this->formalBlockReason($report, $actor, $case, $activeWithdrawal);

                if ($blockReason !== null) {
                    throw $this->formalConflict($blockReason);
                }

                $withdrawal = ReportWithdrawal::query()->create([
                    'report_id' => $report->id,
                    'case_id' => $case?->id,
                    'requester_id' => $actor->id,
                    'registration_number_snapshot' => $report->registration_number,
                    'requester_display_name_snapshot' => $report->report_type === 'anonymous'
                        ? 'Pelapor Anonim'
                        : $actor->name,
                    'request_type' => ReportWithdrawalRequestType::FormalWithdrawal,
                    'status' => ReportWithdrawalStatus::Draft,
                    'reason' => $reason,
                    'previous_report_status' => $report->status,
                    'previous_case_status' => $case?->status?->name,
                    'resubmission_allowed' => false,
                    'lock_version' => 0,
                ]);

                $withdrawal->setRelation('attachments', $withdrawal->attachments()->get());

                $this->recordAudit(
                    AuditAction::ReportWithdrawalCreated,
                    $actor,
                    $report,
                    $withdrawal,
                    fromStatus: null,
                    toStatus: ReportWithdrawalStatus::Draft->value,
                );
                $this->recordAudit(
                    AuditAction::ReportWithdrawalDraftDocumentPrepared,
                    $actor,
                    $report,
                    $withdrawal,
                    fromStatus: ReportWithdrawalStatus::Draft->value,
                    toStatus: ReportWithdrawalStatus::Draft->value,
                );

                return $this->resourcePayload($withdrawal, $actor);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw $this->formalConflict('active_request');
            }

            throw $exception;
        }
    }

    /**
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    public function current(User $actor, string $registrationNumber): array
    {
        $this->authorizeActor($actor);

        $report = Report::query()
            ->where('registration_number', $registrationNumber)
            ->whereNotNull('reporter_id')
            ->where('reporter_id', $actor->id)
            ->first() ?? throw $this->notFound();

        $withdrawal = ReportWithdrawal::query()
            ->with('attachments')
            ->where('report_id', $report->id)
            ->where('requester_id', $actor->id)
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->latest('id')
            ->first() ?? throw $this->notFound();

        return $this->resourcePayload($withdrawal, $actor);
    }

    public function draftDocument(User $actor, string $publicId): Response
    {
        $this->authorizeActor($actor);
        [$report, $withdrawal] = $this->resolveOwnedDraftDocumentContext($actor, $publicId);
        $draftData = $this->draftDocumentData($actor, $report, $withdrawal);

        $this->recordAudit(
            AuditAction::ReportWithdrawalDraftDocumentViewed,
            $actor,
            $report,
            $withdrawal,
            fromStatus: $withdrawal->status->value,
            toStatus: $withdrawal->status->value,
        );
        return response()->view('withdrawals.draft-document', $draftData['preview'])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; script-src 'none'; object-src 'none'; img-src 'none'; font-src 'none'; connect-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function downloadDraftDocument(User $actor, string $publicId): StreamedResponse
    {
        $this->authorizeActor($actor);
        [$report, $withdrawal] = $this->resolveOwnedDraftDocumentContext($actor, $publicId);
        $draftData = $this->draftDocumentData($actor, $report, $withdrawal);
        try {
            $pdf = $this->draftPdfConverter->convert($draftData['replacements']);
        } catch (Throwable) {
            throw $this->serverError();
        }
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false || fwrite($stream, $pdf) === false || ! rewind($stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $this->serverError();
        }

        return response()->streamDownload(function () use ($stream, $actor, $report, $withdrawal): void {
            try {
                if (fpassthru($stream) === false) {
                    throw new \RuntimeException('Draft document stream failed');
                }

                DB::transaction(fn () => $this->recordAudit(
                    AuditAction::ReportWithdrawalDraftDocumentDownloaded,
                    $actor,
                    $report,
                    $withdrawal,
                    fromStatus: $withdrawal->status->value,
                    toStatus: $withdrawal->status->value,
                ));
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, self::DRAFT_DOCUMENT_FILENAME, [
            'Content-Type' => self::DRAFT_DOCUMENT_MIME,
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ], 'inline');
    }

    public function draftDocumentExample(User $actor, string $publicId): BinaryFileResponse
    {
        $this->authorizeActor($actor);
        $this->resolveOwnedDraftDocumentContext($actor, $publicId);
        $path = resource_path(self::DRAFT_EXAMPLE_TEMPLATE_PATH);

        if (! is_file($path)) {
            throw $this->notFound();
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contoh-pengisian-surat-pencabutan.pdf"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    /**
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    public function uploadSignedDocument(
        User $actor,
        string $publicId,
        UploadedFile $file,
        int $expectedLockVersion,
    ): array {
        $this->authorizeActor($actor);
        $this->ensureFeatureEnabled();
        $fileData = $this->validatedFileData($file);
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $actor,
                $publicId,
                $fileData,
                $expectedLockVersion,
                &$storedPath,
            ): array {
                [$report, , $withdrawal] = $this->lockOwnedContext($actor, $publicId);

                if ($withdrawal->request_type !== ReportWithdrawalRequestType::FormalWithdrawal) {
                    throw $this->notFound();
                }

                $this->assertExpectedLockVersion($withdrawal, $expectedLockVersion);

                if (! in_array($withdrawal->status, [
                    ReportWithdrawalStatus::Draft,
                    ReportWithdrawalStatus::WaitingDocument,
                ], true)) {
                    throw $this->formalConflict('upload_not_allowed');
                }

                $documentType = ReportWithdrawalDocumentType::SignedWithdrawalStatement;
                $nextVersion = (int) $withdrawal->attachments()
                    ->where('document_type', $documentType->value)
                    ->max('version') + 1;
                $publicId = (string) Str::uuid();
                $storedPath = sprintf(
                    'formal/%s/%s.%s',
                    $withdrawal->public_id,
                    $publicId,
                    $fileData['extension'],
                );
                $source = fopen($fileData['temporary_path'], 'rb');

                if ($source === false) {
                    throw $this->serverError();
                }

                try {
                    $stored = Storage::disk(self::FILE_DISK)->writeStream($storedPath, $source, [
                        'visibility' => 'private',
                    ]);
                } finally {
                    fclose($source);
                }

                if (! $stored) {
                    throw $this->serverError();
                }

                $attachment = ReportWithdrawalAttachment::query()->create([
                    'withdrawal_id' => $withdrawal->id,
                    'public_id' => $publicId,
                    'document_type' => $documentType,
                    'version' => $nextVersion,
                    'disk' => self::FILE_DISK,
                    'path' => $storedPath,
                    'original_name' => $fileData['original_filename'],
                    'server_mime' => $fileData['mime_type'],
                    'size' => $fileData['file_size'],
                    'sha256' => $fileData['checksum'],
                    'uploaded_by' => $actor->id,
                ]);
                $fromStatus = $withdrawal->status->value;
                $withdrawal->forceFill([
                    'status' => ReportWithdrawalStatus::WaitingDocument,
                    'lock_version' => $withdrawal->lock_version + 1,
                ])->save();

                $this->recordAudit(
                    AuditAction::ReportWithdrawalSignedDocumentUploaded,
                    $actor,
                    $report,
                    $withdrawal,
                    attachment: $attachment,
                    fromStatus: $fromStatus,
                    toStatus: ReportWithdrawalStatus::WaitingDocument->value,
                );

                $withdrawal->setRelation('attachments', $withdrawal->attachments()->get());

                return $this->resourcePayload($withdrawal, $actor);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                try {
                    Storage::disk(self::FILE_DISK)->delete($storedPath);
                } catch (Throwable) {
                    // Prefer an orphaned private binary over a committed row that references no file.
                }
            }

            if ($exception instanceof HttpResponseException) {
                throw $exception;
            }

            if ($exception instanceof QueryException && $this->isUniqueViolation($exception)) {
                throw $this->formalConflict('attachment_version_conflict');
            }

            throw $this->serverError();
        }
    }

    public function downloadSignedDocument(
        User $actor,
        string $withdrawalPublicId,
        string $attachmentPublicId,
    ): StreamedResponse {
        $this->authorizeActor($actor);

        $withdrawal = ReportWithdrawal::query()
            ->with('report')
            ->where('public_id', $withdrawalPublicId)
            ->where('requester_id', $actor->id)
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->first() ?? throw $this->notFound();
        $report = $withdrawal->report;

        if (! $report instanceof Report || ! $this->reportPolicy->withdraw($actor, $report)) {
            throw $this->notFound();
        }

        $attachment = $withdrawal->attachments()
            ->where('public_id', $attachmentPublicId)
            ->where('document_type', ReportWithdrawalDocumentType::SignedWithdrawalStatement->value)
            ->first() ?? throw $this->notFound();

        if (! $this->attachmentStorageIsValid($withdrawal, $attachment)) {
            throw $this->notFound();
        }

        $stream = Storage::disk(self::FILE_DISK)->readStream($attachment->path);

        if ($stream === false) {
            throw $this->notFound();
        }

        DB::transaction(fn () => $this->recordAudit(
            AuditAction::ReportWithdrawalSignedDocumentDownloaded,
            $actor,
            $report,
            $withdrawal,
            attachment: $attachment,
            fromStatus: $withdrawal->status->value,
            toStatus: $withdrawal->status->value,
        ));

        $extension = self::EXTENSION_BY_MIME[$attachment->server_mime] ?? 'bin';
        $filename = $this->sanitizeOriginalFilename($attachment->original_name, $extension);

        return response()->streamDownload(function () use ($stream): void {
            try {
                if (fpassthru($stream) === false) {
                    throw new \RuntimeException('Withdrawal document stream failed');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, $filename, [
            'Content-Type' => $attachment->server_mime,
            'Content-Length' => (string) $attachment->size,
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    /**
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    public function submit(User $actor, string $publicId, int $expectedLockVersion): array
    {
        $this->authorizeActor($actor);
        $this->ensureFeatureEnabled();

        return DB::transaction(function () use ($actor, $publicId, $expectedLockVersion): array {
            [$report, $case, $withdrawal] = $this->lockOwnedContext($actor, $publicId);

            if ($withdrawal->request_type !== ReportWithdrawalRequestType::FormalWithdrawal) {
                throw $this->notFound();
            }

            $this->assertExpectedLockVersion($withdrawal, $expectedLockVersion);

            if (! $withdrawal->isWaitingDocument()) {
                throw $this->formalConflict('invalid_transition');
            }

            $blockReason = $this->formalBlockReason($report, $actor, $case, null);

            if ($blockReason !== null) {
                throw $this->formalConflict($blockReason);
            }

            $withdrawal->load('attachments');
            $attachment = $withdrawal->currentSignedAttachment();

            if ($attachment === null || ! $this->attachmentStorageIsValid($withdrawal, $attachment)) {
                throw $this->formalConflict('signed_document_required');
            }

            $fromStatus = $withdrawal->status->value;
            $withdrawal->forceFill([
                'status' => ReportWithdrawalStatus::PendingReview,
                'submitted_at' => now(),
                'lock_version' => $withdrawal->lock_version + 1,
            ])->save();

            $this->recordAudit(
                AuditAction::ReportWithdrawalSubmitted,
                $actor,
                $report,
                $withdrawal,
                attachment: $attachment,
                fromStatus: $fromStatus,
                toStatus: ReportWithdrawalStatus::PendingReview->value,
            );
            $this->notifications->formalReportWithdrawalSubmitted($report, $withdrawal);

            return $this->resourcePayload($withdrawal->fresh('attachments'), $actor);
        });
    }

    /**
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    public function cancel(User $actor, string $publicId, int $expectedLockVersion): array
    {
        $this->authorizeActor($actor);

        return DB::transaction(function () use ($actor, $publicId, $expectedLockVersion): array {
            [$report, , $withdrawal] = $this->lockOwnedContext($actor, $publicId);

            if ($withdrawal->request_type !== ReportWithdrawalRequestType::FormalWithdrawal) {
                throw $this->notFound();
            }

            $this->assertExpectedLockVersion($withdrawal, $expectedLockVersion);

            if (! $withdrawal->isCancellableByRequester()) {
                throw $this->formalConflict('invalid_transition');
            }

            $fromStatus = $withdrawal->status->value;
            $wasPendingReview = $withdrawal->isPendingReview();
            $withdrawal->forceFill([
                'status' => ReportWithdrawalStatus::Cancelled,
                'cancelled_at' => now(),
                'lock_version' => $withdrawal->lock_version + 1,
            ])->save();

            $this->recordAudit(
                AuditAction::ReportWithdrawalCancelled,
                $actor,
                $report,
                $withdrawal,
                fromStatus: $fromStatus,
                toStatus: ReportWithdrawalStatus::Cancelled->value,
            );

            if ($wasPendingReview) {
                $this->notifications->formalReportWithdrawalCancelled($report, $withdrawal);
            }

            return $this->resourcePayload($withdrawal->fresh('attachments'), $actor);
        });
    }

    /**
     * Create a fresh immutable draft that supersedes an eligible rejected request.
     *
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    public function resubmit(
        User $actor,
        string $publicId,
        string $reason,
        int $expectedLockVersion,
    ): array {
        $this->authorizeActor($actor);
        $this->ensureFeatureEnabled();

        try {
            return DB::transaction(function () use ($actor, $publicId, $reason, $expectedLockVersion): array {
                [$report, $case, $previous] = $this->lockOwnedContext($actor, $publicId);

                $this->assertExpectedLockVersion($previous, $expectedLockVersion);

                if ($previous->request_type !== ReportWithdrawalRequestType::FormalWithdrawal
                    || $previous->status !== ReportWithdrawalStatus::Rejected
                    || ! $previous->resubmission_allowed) {
                    throw $this->formalConflict('resubmission_not_allowed');
                }

                if (ReportWithdrawal::query()
                    ->where('supersedes_id', $previous->id)
                    ->lockForUpdate()
                    ->exists()) {
                    throw $this->formalConflict('resubmission_not_allowed');
                }

                $active = $this->lockActiveWithdrawal($report);
                $blockReason = $this->formalBlockReason($report, $actor, $case, $active);

                if ($blockReason !== null) {
                    throw $this->formalConflict($blockReason);
                }

                $withdrawal = ReportWithdrawal::query()->create([
                    'report_id' => $report->id,
                    'case_id' => $case?->id,
                    'requester_id' => $actor->id,
                    'registration_number_snapshot' => $report->registration_number,
                    'requester_display_name_snapshot' => $report->report_type === 'anonymous'
                        ? 'Pelapor Anonim'
                        : $actor->name,
                    'request_type' => ReportWithdrawalRequestType::FormalWithdrawal,
                    'status' => ReportWithdrawalStatus::Draft,
                    'reason' => $reason,
                    'previous_report_status' => $report->status,
                    'previous_case_status' => $case?->status?->name,
                    'resubmission_allowed' => false,
                    'supersedes_id' => $previous->id,
                    'lock_version' => 0,
                ]);

                $withdrawal->setRelation('attachments', collect());
                $this->recordAudit(
                    AuditAction::ReportWithdrawalResubmitted,
                    $actor,
                    $report,
                    $withdrawal,
                    fromStatus: ReportWithdrawalStatus::Rejected->value,
                    toStatus: ReportWithdrawalStatus::Draft->value,
                );

                return $this->resourcePayload($withdrawal, $actor);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw $this->formalConflict('active_request');
            }

            throw $exception;
        }
    }

    public function formalBlockReason(
        Report $report,
        User $actor,
        ?CaseRecord $case,
        ?ReportWithdrawal $activeWithdrawal,
    ): ?string {
        if (! $this->reportPolicy->withdraw($actor, $report)) {
            return 'ownership_unavailable';
        }

        if (! (bool) config('withdrawal.formal_withdrawal_enabled', false)) {
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

        if ($case === null) {
            return $report->status === ReportStatus::Forwarded->value
                || $report->forwarded_at !== null
                    ? null
                    : 'not_forwarded';
        }

        $case->loadMissing(['status', 'recommendation.decision.status']);
        $caseStatus = $case->status?->name;

        if ($case->escalated_at !== null
            || in_array($caseStatus, self::INELIGIBLE_CASE_STATUSES, true)) {
            return 'case_stage_ineligible';
        }

        $decision = $case->recommendation?->decision;

        if ($decision?->finalized_at !== null
            || $decision?->status?->name === DecisionStatusEnum::Finalized->value) {
            return 'decision_finalized';
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    public function withdrawalCapabilities(ReportWithdrawal $withdrawal, User $actor): array
    {
        $withdrawal->loadMissing([
            'attachments',
            'report.case.status',
            'report.case.recommendation.decision.status',
        ]);
        $isOwner = $actor->is_active
            && $actor->hasRole('reporter')
            && $actor->hasPermission('reports.withdraw.own')
            && $withdrawal->requester_id === $actor->id;
        $featureEnabled = (bool) config('withdrawal.formal_withdrawal_enabled', false);
        $attachment = $withdrawal->currentSignedAttachment();
        $report = $withdrawal->report;
        $activeRequestExists = $report instanceof Report
            && ReportWithdrawal::query()
                ->where('report_id', $report->id)
                ->where('id', '<>', $withdrawal->id)
                ->whereIn('status', ReportWithdrawalStatus::activeValues())
                ->exists();
        $alreadySuperseded = ReportWithdrawal::query()
            ->where('supersedes_id', $withdrawal->id)
            ->exists();
        $resubmissionEligible = $isOwner
            && $featureEnabled
            && $withdrawal->status === ReportWithdrawalStatus::Rejected
            && $withdrawal->resubmission_allowed
            && $report instanceof Report
            && ! $activeRequestExists
            && ! $alreadySuperseded
            && $this->formalBlockReason($report, $actor, $report->case, null) === null;

        return [
            'can_view_draft' => $isOwner
                && $withdrawal->request_type === ReportWithdrawalRequestType::FormalWithdrawal
                && in_array($withdrawal->status, [
                    ReportWithdrawalStatus::Draft,
                    ReportWithdrawalStatus::WaitingDocument,
                    ReportWithdrawalStatus::PendingReview,
                    ReportWithdrawalStatus::Cancelled,
                ], true),
            'can_upload_document' => $isOwner
                && $featureEnabled
                && in_array($withdrawal->status, [
                    ReportWithdrawalStatus::Draft,
                    ReportWithdrawalStatus::WaitingDocument,
                ], true),
            'can_submit' => $isOwner
                && $featureEnabled
                && $withdrawal->isWaitingDocument()
                && $attachment !== null,
            'can_cancel_request' => $isOwner && $withdrawal->isCancellableByRequester(),
            'can_resubmit' => $resubmissionEligible,
        ];
    }

    /**
     * @return array{0: Report, 1: ReportWithdrawal}
     */
    private function resolveOwnedDraftDocumentContext(User $actor, string $publicId): array
    {
        $withdrawal = ReportWithdrawal::query()
            ->with('report')
            ->where('public_id', $publicId)
            ->where('requester_id', $actor->id)
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->first() ?? throw $this->notFound();
        $report = $withdrawal->report;

        if (! $report instanceof Report || ! $this->reportPolicy->withdraw($actor, $report)) {
            throw $this->notFound();
        }

        if (! in_array($withdrawal->status, [
            ReportWithdrawalStatus::Draft,
            ReportWithdrawalStatus::WaitingDocument,
            ReportWithdrawalStatus::PendingReview,
            ReportWithdrawalStatus::Cancelled,
        ], true)) {
            throw $this->formalConflict('document_unavailable');
        }

        if (blank($withdrawal->registration_number_snapshot)) {
            throw $this->formalConflict('document_snapshot_unavailable');
        }

        return [$report, $withdrawal];
    }

    private function draftDocumentNumber(ReportWithdrawal $withdrawal): string
    {
        $registrationNumber = Str::upper((string) $withdrawal->registration_number_snapshot);
        $registrationNumber = preg_replace('/^SLP-DEMO-/', 'SLP-', $registrationNumber) ?? $registrationNumber;

        return 'DRAFT/'.$registrationNumber;
    }

    /**
     * @return array{
     *     preview: array<string, string>,
     *     replacements: array<string, string>
     * }
     */
    private function draftDocumentData(User $actor, Report $report, ReportWithdrawal $withdrawal): array
    {
        $actor->loadMissing('studyProgram');
        $this->assertDraftProfileComplete($actor);

        $status = $this->draftProfileStatusLabel($actor);
        $address = $this->shortDraftAddress((string) $actor->address);
        $documentNumber = $this->draftDocumentNumber($withdrawal);
        $reportDate = $report->submitted_at?->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('j F Y') ?? '-';
        $issuedDate = now('Asia/Jakarta')->locale('id')->translatedFormat('l, j F Y');
        $program = $actor->studyProgram?->name ?? '-';

        $preview = [
            'documentNumber' => $documentNumber,
            'reporterAccountName' => (string) $actor->name,
            'reporterName' => (string) $actor->name,
            'reporterNim' => (string) ($actor->nim ?? '-'),
            'reporterStatus' => $status,
            'reporterProgram' => $program,
            'reporterAddress' => $address,
            'reporterPhone' => (string) ($actor->phone_number ?? '-'),
            'reportNumber' => (string) $report->registration_number,
            'reportDate' => $reportDate,
            'issuedDate' => $issuedDate,
        ];

        return [
            'preview' => $preview,
            'replacements' => [
                'generate_system' => $documentNumber,
                'nama_akun_pelapor' => (string) $actor->name,
                'nim_pelapor' => (string) ($actor->nim ?? '-'),
                'status_akun_pelapor' => $status,
                'program_studi_pelapor' => $program,
                'alamat_pelapor' => $address,
                'nomor_telepon_pelapor' => (string) ($actor->phone_number ?? '-'),
                'nomor_laporan' => (string) $report->registration_number,
                'tanggal_pelaporan' => $reportDate,
                'hari, tanggal bulan tahun' => $issuedDate,
                'nama_pelapor' => (string) $actor->name,
            ],
        ];
    }

    private function assertDraftProfileComplete(User $actor): void
    {
        $missing = blank($actor->profile_status)
            || blank($actor->address)
            || ($actor->profile_status === 'other' && blank($actor->profile_status_other));

        if ($missing) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.'.ApiErrorCode::ReportWithdrawalDraftProfileIncomplete),
                'error_code' => ApiErrorCode::ReportWithdrawalDraftProfileIncomplete,
                'errors' => null,
            ], 422));
        }
    }

    private function draftProfileStatusLabel(User $actor): string
    {
        return match ($actor->profile_status) {
            'student' => 'Mahasiswa',
            'lecturer' => 'Dosen',
            'education_staff' => 'Tenaga Kependidikan',
            'employee' => 'Pegawai',
            'other' => (string) $actor->profile_status_other,
            default => '-',
        };
    }

    private function shortDraftAddress(string $address): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $address));
    }

    /**
     * @return array{0: Report, 1: CaseRecord|null, 2: ReportWithdrawal}
     */
    private function lockOwnedContext(User $actor, string $publicId): array
    {
        $reference = ReportWithdrawal::query()
            ->select(['id', 'report_id'])
            ->where('public_id', $publicId)
            ->first() ?? throw $this->notFound();

        $report = Report::query()
            ->whereKey($reference->report_id)
            ->whereNotNull('reporter_id')
            ->where('reporter_id', $actor->id)
            ->lockForUpdate()
            ->first() ?? throw $this->notFound();

        if (! $this->reportPolicy->withdraw($actor, $report)) {
            throw $this->forbidden();
        }

        $case = $this->lockCaseForReport($report);
        $withdrawal = ReportWithdrawal::query()
            ->with('attachments')
            ->whereKey($reference->id)
            ->where('report_id', $report->id)
            ->where('requester_id', $actor->id)
            ->lockForUpdate()
            ->first() ?? throw $this->notFound();

        return [$report, $case, $withdrawal];
    }

    private function lockOwnedReport(User $actor, string $registrationNumber): Report
    {
        $report = Report::query()
            ->where('registration_number', $registrationNumber)
            ->whereNotNull('reporter_id')
            ->where('reporter_id', $actor->id)
            ->lockForUpdate()
            ->first() ?? throw $this->notFound();

        if (! $this->reportPolicy->withdraw($actor, $report)) {
            throw $this->forbidden();
        }

        return $report;
    }

    private function lockCaseForReport(Report $report): ?CaseRecord
    {
        return CaseRecord::query()
            ->with(['status', 'recommendation.decision.status'])
            ->where('report_id', $report->id)
            ->lockForUpdate()
            ->first();
    }

    private function lockActiveWithdrawal(Report $report): ?ReportWithdrawal
    {
        return ReportWithdrawal::query()
            ->where('report_id', $report->id)
            ->whereIn('status', ReportWithdrawalStatus::activeValues())
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array{withdrawal: ReportWithdrawal, capabilities: array<string, bool>}
     */
    private function resourcePayload(ReportWithdrawal $withdrawal, User $actor): array
    {
        $withdrawal->loadMissing('attachments');

        return [
            'withdrawal' => $withdrawal,
            'capabilities' => $this->withdrawalCapabilities($withdrawal, $actor),
        ];
    }

    private function authorizeActor(User $actor): void
    {
        if (! $actor->is_active
            || ! $actor->hasRole('reporter')
            || ! $actor->hasPermission('reports.withdraw.own')) {
            throw $this->forbidden();
        }
    }

    private function ensureFeatureEnabled(): void
    {
        if (! (bool) config('withdrawal.formal_withdrawal_enabled', false)) {
            throw $this->formalConflict('feature_disabled');
        }
    }

    private function assertExpectedLockVersion(
        ReportWithdrawal $withdrawal,
        int $expectedLockVersion,
    ): void {
        if ($withdrawal->lock_version !== $expectedLockVersion) {
            throw $this->formalConflict('stale_update');
        }
    }

    /**
     * @return array{temporary_path: string, mime_type: string, extension: string, file_size: int, checksum: string, original_filename: string}
     */
    private function validatedFileData(UploadedFile $file): array
    {
        $temporaryPath = $file->getRealPath();
        $serverMime = $file->getMimeType();
        $declaredMime = strtolower(trim($file->getClientMimeType()));
        $fileSize = $file->getSize();
        $clientName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $clientExtension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        $extension = is_string($serverMime) ? (self::EXTENSION_BY_MIME[$serverMime] ?? null) : null;

        if (! $file->isValid()
            || ! is_string($temporaryPath)
            || $temporaryPath === ''
            || ! is_string($serverMime)
            || $extension === null
            || $declaredMime !== $serverMime
            || ! in_array($clientExtension, self::CLIENT_EXTENSIONS_BY_MIME[$serverMime] ?? [], true)
            || substr_count($clientName, '.') !== 1
            || $fileSize === false
            || $fileSize < 1
            || $fileSize > self::MAX_FILE_SIZE) {
            throw $this->unprocessable();
        }

        $bytes = file_get_contents($temporaryPath);

        if (! is_string($bytes)
            || strlen($bytes) !== $fileSize
            || ! $this->hasSafeFileStructure($bytes, $serverMime)) {
            throw $this->unprocessable();
        }

        $checksum = hash_file('sha256', $temporaryPath);

        if ($checksum === false) {
            throw $this->serverError();
        }

        return [
            'temporary_path' => $temporaryPath,
            'mime_type' => $serverMime,
            'extension' => $extension,
            'file_size' => $fileSize,
            'checksum' => $checksum,
            'original_filename' => $this->sanitizeOriginalFilename($clientName, $extension),
        ];
    }

    private function hasSafeFileStructure(string $bytes, string $mimeType): bool
    {
        if ($mimeType === 'application/pdf') {
            $lower = strtolower($bytes);
            $canonicalNames = preg_replace_callback(
                '/#([a-f0-9]{2})/i',
                static fn (array $match): string => chr((int) hexdec($match[1])),
                $lower,
            );

            if (! is_string($canonicalNames)) {
                return false;
            }

            $canonicalNames = preg_replace('/[\x00\x09\x0A\x0C\x0D\x20]+/', '', $canonicalNames);

            if (! is_string($canonicalNames)) {
                return false;
            }

            if (! str_starts_with($bytes, '%PDF-')
                || ! preg_match('/%%EOF[\x00\x09\x0A\x0C\x0D\x20]*\z/', $bytes)) {
                return false;
            }

            foreach ([
                '/javascript',
                '/js',
                '/launch',
                '/embeddedfile',
                '/filespec',
                '/openaction',
                '/aa',
                '/acroform',
                '/xfa',
                '/richmedia',
                '/submitform',
                '/importdata',
                '/gotoe',
                '/rendition',
                '/movie',
                '/sound',
                '/3d',
                '/uri',
                '<script',
                '<?php',
            ] as $marker) {
                if (str_contains($canonicalNames, $marker)) {
                    return false;
                }
            }

            return ! str_starts_with($bytes, 'MZ')
                && ! str_starts_with($bytes, "PK\x03\x04");
        }

        $image = @getimagesizefromstring($bytes);

        if (! is_array($image)
            || ($image['mime'] ?? null) !== $mimeType
            || ! isset($image[0], $image[1])
            || $image[0] < 1
            || $image[1] < 1
            || $image[0] * $image[1] > self::MAX_IMAGE_PIXELS) {
            return false;
        }

        return match ($mimeType) {
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
                && str_ends_with($bytes, "IEND\xaeB`\x82"),
            'image/jpeg' => str_starts_with($bytes, "\xff\xd8")
                && str_ends_with($bytes, "\xff\xd9"),
            default => false,
        };
    }

    public function attachmentStorageIsValid(
        ReportWithdrawal $withdrawal,
        ReportWithdrawalAttachment $attachment,
    ): bool {
        $extension = self::EXTENSION_BY_MIME[$attachment->server_mime] ?? null;

        if ($extension === null
            || $attachment->disk !== self::FILE_DISK
            || ! is_string($attachment->path)
            || ! preg_match(
                '#^formal/'.preg_quote($withdrawal->public_id, '#').'/'
                    .preg_quote($attachment->public_id, '#').'\.'.preg_quote($extension, '#').'$#D',
                $attachment->path,
            )
            || ! is_int($attachment->size)
            || $attachment->size < 1
            || $attachment->size > self::MAX_FILE_SIZE
            || ! is_string($attachment->sha256)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $attachment->sha256)) {
            return false;
        }

        try {
            if (! Storage::disk(self::FILE_DISK)->exists($attachment->path)) {
                return false;
            }

            $stream = Storage::disk(self::FILE_DISK)->readStream($attachment->path);
        } catch (Throwable) {
            return false;
        }

        if ($stream === false) {
            return false;
        }

        try {
            $context = hash_init('sha256');
            $bytesRead = hash_update_stream($context, $stream);
            $checksum = hash_final($context);
        } finally {
            fclose($stream);
        }

        return $bytesRead === $attachment->size
            && hash_equals($attachment->sha256, $checksum);
    }

    private function sanitizeOriginalFilename(string $filename, string $fallbackExtension): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        if ($filename === '') {
            return 'surat-pencabutan.'.$fallbackExtension;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $extension = $fallbackExtension;
        }

        $suffix = '.'.$extension;
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = mb_strimwidth($stem, 0, max(1, 255 - mb_strwidth($suffix)), '');
        $stem = rtrim($stem, " .\t\n\r\0\x0B");

        return ($stem !== '' ? $stem : 'surat-pencabutan').$suffix;
    }

    private function recordAudit(
        AuditAction $action,
        User $actor,
        Report $report,
        ReportWithdrawal $withdrawal,
        ?ReportWithdrawalAttachment $attachment = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
    ): void {
        $this->auditLog->record(
            $action,
            AuditCategory::Report,
            AuditSeverity::Info,
            actor: $actor,
            subject: $report,
            metadata: [
                'registration_number' => $report->registration_number,
                'withdrawal_public_id' => $withdrawal->public_id,
                'attachment_public_id' => $attachment?->public_id,
                'attachment_version' => $attachment?->version,
                'document_type' => $attachment?->document_type?->value,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'request_type' => ReportWithdrawalRequestType::FormalWithdrawal->value,
                'file_size' => $attachment?->size,
                'server_mime' => $attachment?->server_mime,
                'result' => AuditResult::Succeeded->value,
            ],
            beforeChanges: $fromStatus === null ? [] : ['status' => $fromStatus],
            afterChanges: $toStatus === null ? [] : ['status' => $toStatus],
            result: AuditResult::Succeeded,
        );
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
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

    private function formalConflict(string $reasonCode): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.'.ApiErrorCode::ReportWithdrawalConflict),
            'error_code' => ApiErrorCode::ReportWithdrawalConflict,
            'reason_code' => $reasonCode,
            'errors' => null,
        ], 409));
    }

    private function unprocessable(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.'.ApiErrorCode::ReportWithdrawalDocumentInvalid),
            'error_code' => ApiErrorCode::ReportWithdrawalDocumentInvalid,
            'errors' => null,
        ], 422));
    }

    private function serverError(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.'.ApiErrorCode::ReportWithdrawalStorageFailed),
            'error_code' => ApiErrorCode::ReportWithdrawalStorageFailed,
            'errors' => null,
        ], 500));
    }
}
