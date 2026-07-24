<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ReportStatus;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Models\ReportEvidenceSubmission;
use App\Models\User;
use App\Support\CaseCampusScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportEvidenceSubmissionService
{
    public const MAX_FILES_PER_REPORT = 5;

    private const FILE_DISK = 'evidence';

    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

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
    private const UPLOADABLE_REPORT_STATUSES = [
        ReportStatus::Submitted->value,
        ReportStatus::UnderReview->value,
        ReportStatus::NeedInfo->value,
        ReportStatus::Forwarded->value,
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
        private readonly CaseMutationGuard $caseMutationGuard,
    ) {}

    /**
     * @return array{files: Collection<int, ReportEvidenceSubmission>, upload_allowed: bool, remaining_slots: int}
     */
    public function listForReporter(User $actor, string $registrationNumber): array
    {
        $this->authorizeReporter($actor, 'reporter_evidence.read.own');
        $report = $this->ownedReportOrFail($actor, $registrationNumber);
        $files = $report->evidenceSubmissions()
            ->latest('uploaded_at')
            ->latest('id')
            ->get();
        $remainingSlots = max(0, self::MAX_FILES_PER_REPORT - $files->count());

        return [
            'files' => $files,
            'upload_allowed' => $remainingSlots > 0 && $this->canUploadToReport($actor, $report),
            'remaining_slots' => $remainingSlots,
        ];
    }

    public function uploadForReporter(
        User $actor,
        string $registrationNumber,
        UploadedFile $file,
    ): ReportEvidenceSubmission {
        $this->authorizeReporter($actor, 'reporter_evidence.upload.own');
        $report = $this->ownedReportOrFail($actor, $registrationNumber);
        $this->ensureUploadEligible($report);
        $fileData = $this->validatedFileData($file);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($actor, $report, $fileData, &$storedPath): ReportEvidenceSubmission {
                $lockedReport = Report::query()
                    ->with('case.status')
                    ->whereKey($report->id)
                    ->where('reporter_id', $actor->id)
                    ->whereNotNull('reporter_id')
                    ->lockForUpdate()
                    ->first() ?? throw $this->notFound();

                $this->authorizeReporter($actor, 'reporter_evidence.upload.own');
                if ($lockedReport->case !== null) {
                    $lockedCase = $this->caseMutationGuard->lockAndAssertMutable($lockedReport->case);
                    $lockedReport->setRelation('case', $lockedCase);
                }
                $this->ensureUploadEligible($lockedReport);

                if ($lockedReport->evidenceSubmissions()->count() >= self::MAX_FILES_PER_REPORT) {
                    throw $this->conflict('The maximum number of supporting files has been reached');
                }

                $uuid = Str::uuid()->toString();
                $storedPath = sprintf(
                    'reports/%d/reporter-submissions/%s.%s',
                    $lockedReport->id,
                    $uuid,
                    $fileData['extension'],
                );
                $source = fopen($fileData['temporary_path'], 'rb');

                if ($source === false) {
                    throw $this->serverError('The supporting file could not be processed');
                }

                try {
                    $stored = Storage::disk(self::FILE_DISK)->writeStream($storedPath, $source, [
                        'visibility' => 'private',
                    ]);
                } finally {
                    fclose($source);
                }

                if (! $stored) {
                    throw $this->serverError('The supporting file could not be stored');
                }

                $submission = ReportEvidenceSubmission::query()->create([
                    'uuid' => $uuid,
                    'report_id' => $lockedReport->id,
                    'uploaded_by' => $actor->id,
                    'original_filename' => $fileData['original_filename'],
                    'mime_type' => $fileData['mime_type'],
                    'file_size' => $fileData['file_size'],
                    'checksum_sha256' => $fileData['checksum'],
                    'storage_disk' => self::FILE_DISK,
                    'storage_path' => $storedPath,
                    'uploaded_at' => now(),
                ]);

                $this->recordAudit(
                    AuditAction::ReporterEvidenceUploaded,
                    $submission,
                    $actor,
                );

                return $submission;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk(self::FILE_DISK)->delete($storedPath);
            }

            if ($exception instanceof HttpResponseException) {
                throw $exception;
            }

            throw $this->serverError('The supporting file could not be saved');
        }
    }

    public function downloadForReporter(User $actor, string $uuid): StreamedResponse
    {
        $this->authorizeReporter($actor, 'reporter_evidence.download.own');

        return $this->download(
            $this->reporterSubmissionOrFail($actor, $uuid),
            $actor,
            AuditAction::ReporterEvidenceDownloadedByReporter,
        );
    }

    public function previewForReporter(User $actor, string $uuid): StreamedResponse
    {
        $this->authorizeReporter($actor, 'reporter_evidence.download.own');

        return $this->preview(
            $this->reporterSubmissionOrFail($actor, $uuid),
            $actor,
            AuditAction::ReporterEvidencePreviewedByReporter,
        );
    }

    /**
     * @return Collection<int, ReportEvidenceSubmission>
     */
    public function listForAssignedSatgas(User $actor, CaseRecord $case): Collection
    {
        $isOversight = $this->campusScope->canSensitiveOversight($actor);

        if (! $isOversight) {
            $this->authorizeAssignedSatgasForCase($actor, $case, 'reporter_evidence.read.assigned');
        }

        $files = ReportEvidenceSubmission::query()
            ->where('report_id', $case->report_id)
            ->latest('uploaded_at')
            ->latest('id')
            ->get();

        $case->loadMissing('report:id,report_type');
        $this->maskAnonymousInternalFilenames($files, $case->report);

        return $files;
    }

    /**
     * @return Collection<int, ReportEvidenceSubmission>
     */
    public function listForOversightReport(User $actor, Report $report): Collection
    {
        if (! $this->campusScope->canSensitiveOversight($actor)) {
            throw $this->forbidden();
        }

        $files = $report->evidenceSubmissions()
            ->latest('uploaded_at')
            ->latest('id')
            ->get();

        $this->maskAnonymousInternalFilenames($files, $report);

        return $files;
    }

    public function downloadForAssignedSatgas(User $actor, string $uuid): StreamedResponse
    {
        if ($this->campusScope->canSensitiveOversight($actor)) {
            return $this->download(
                $this->oversightSubmissionOrFail($uuid),
                $actor,
                AuditAction::ReporterEvidenceDownloadedByOversight,
            );
        }

        $this->authorizeSatgasIdentity($actor, 'reporter_evidence.download.assigned');

        return $this->download(
            $this->assignedSubmissionOrFail($actor, $uuid),
            $actor,
            AuditAction::ReporterEvidenceDownloadedBySatgas,
        );
    }

    public function previewForAssignedSatgas(User $actor, string $uuid): StreamedResponse
    {
        if ($this->campusScope->canSensitiveOversight($actor)) {
            return $this->preview(
                $this->oversightSubmissionOrFail($uuid),
                $actor,
                AuditAction::ReporterEvidencePreviewedByOversight,
            );
        }

        $this->authorizeSatgasIdentity($actor, 'reporter_evidence.download.assigned');

        return $this->preview(
            $this->assignedSubmissionOrFail($actor, $uuid),
            $actor,
            AuditAction::ReporterEvidencePreviewedBySatgas,
        );
    }

    private function download(
        ReportEvidenceSubmission $submission,
        User $actor,
        AuditAction $action,
    ): StreamedResponse {
        return $this->respondWithFile($submission, $actor, $action, 'attachment');
    }

    private function preview(
        ReportEvidenceSubmission $submission,
        User $actor,
        AuditAction $action,
    ): StreamedResponse {
        return $this->respondWithFile($submission, $actor, $action, 'inline');
    }

    private function respondWithFile(
        ReportEvidenceSubmission $submission,
        User $actor,
        AuditAction $action,
        string $disposition,
    ): StreamedResponse {
        $preview = $disposition === 'inline';
        $trustedMime = is_string($submission->mime_type)
            && isset(self::EXTENSION_BY_MIME[$submission->mime_type]);

        if ($preview && (! $trustedMime || ! is_int($submission->file_size) || $submission->file_size < 1)) {
            throw $this->unprocessable('Supporting file cannot be previewed');
        }

        if (
            $submission->storage_disk !== self::FILE_DISK
            || ! is_string($submission->storage_path)
            || ! $this->isExpectedStoragePath($submission)
            || ! Storage::disk(self::FILE_DISK)->exists($submission->storage_path)
        ) {
            throw $this->notFound();
        }

        $stream = Storage::disk(self::FILE_DISK)->readStream($submission->storage_path);

        if ($stream === false) {
            throw $this->notFound();
        }

        $extension = self::EXTENSION_BY_MIME[$submission->mime_type] ?? 'bin';
        $submission->loadMissing('report:id,report_type');
        $filename = $submission->report?->report_type === 'anonymous'
            && ! $actor->hasRole('reporter')
                ? $this->anonymousInternalFilename($submission->mime_type)
                : $this->sanitizeOriginalFilename($submission->original_filename, $extension);
        $mimeType = isset(self::EXTENSION_BY_MIME[$submission->mime_type])
            ? $submission->mime_type
            : 'application/octet-stream';

        try {
            $response = $this->streamSubmissionFile(
                $stream,
                $filename,
                $mimeType,
                (int) $submission->file_size,
                $disposition,
                afterStream: $preview
                    ? function () use ($action, $submission, $actor): void {
                        DB::transaction(fn () => $this->recordAudit(
                            $action,
                            $submission,
                            $actor,
                        ));
                    }
                : null,
            );
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $exception;
        }

        if ($preview) {
            return $response;
        }

        try {
            DB::transaction(fn () => $this->recordAudit(
                $action,
                $submission,
                $actor,
            ));
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $exception;
        }

        return $response;
    }

    /** @param resource $stream */
    private function streamSubmissionFile(
        mixed $stream,
        string $filename,
        string $mimeType,
        int $fileSize,
        string $disposition,
        ?callable $afterStream = null,
    ): StreamedResponse {
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $fileSize,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];

        if ($disposition === 'inline') {
            $headers['Cross-Origin-Resource-Policy'] = 'same-origin';
        }

        try {
            return response()->streamDownload(function () use ($stream, $afterStream): void {
                try {
                    if (fpassthru($stream) === false) {
                        throw new \RuntimeException('Supporting file stream failed');
                    }

                    if ($afterStream !== null) {
                        $afterStream();
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, $filename, $headers, $disposition);
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $exception;
        }
    }

    private function reporterSubmissionOrFail(User $actor, string $uuid): ReportEvidenceSubmission
    {
        return ReportEvidenceSubmission::query()
            ->where('uuid', $uuid)
            ->whereHas('report', fn ($query) => $query
                ->where('reporter_id', $actor->id)
                ->whereNotNull('reporter_id'))
            ->first() ?? throw $this->notFound();
    }

    private function assignedSubmissionOrFail(User $actor, string $uuid): ReportEvidenceSubmission
    {
        return ReportEvidenceSubmission::query()
            ->where('uuid', $uuid)
            ->whereHas('report.case.activeAssignments', fn ($query) => $query
                ->where('satgas_id', $actor->id))
            ->first() ?? throw $this->notFound();
    }

    private function oversightSubmissionOrFail(string $uuid): ReportEvidenceSubmission
    {
        return ReportEvidenceSubmission::query()
            ->with('report:id,report_type,registration_number')
            ->where('uuid', $uuid)
            ->first() ?? throw $this->notFound();
    }

    /**
     * @param  Collection<int, ReportEvidenceSubmission>  $files
     */
    private function maskAnonymousInternalFilenames(Collection $files, ?Report $report): void
    {
        if ($report?->report_type !== 'anonymous') {
            return;
        }

        $files->each(fn (ReportEvidenceSubmission $submission) => $submission->setAttribute(
            'safe_filename',
            $this->anonymousInternalFilename($submission->mime_type),
        ));
    }

    private function anonymousInternalFilename(?string $mimeType): string
    {
        return 'supporting-file.'.(self::EXTENSION_BY_MIME[$mimeType] ?? 'bin');
    }

    private function ownedReportOrFail(User $actor, string $registrationNumber): Report
    {
        return Report::query()
            ->with('case.status')
            ->where('registration_number', $registrationNumber)
            ->where('reporter_id', $actor->id)
            ->whereNotNull('reporter_id')
            ->first() ?? throw $this->notFound();
    }

    private function authorizeReporter(User $actor, string $permission): void
    {
        if (! $actor->is_active || ! $actor->hasRole('reporter') || ! $actor->hasPermission($permission)) {
            throw $this->forbidden();
        }
    }

    private function authorizeSatgasIdentity(User $actor, string $permission): void
    {
        if (! $actor->is_active || ! $actor->hasRole('satgas_ppks') || ! $actor->hasPermission($permission)) {
            throw $this->forbidden();
        }
    }

    private function authorizeAssignedSatgasForCase(User $actor, CaseRecord $case, string $permission): void
    {
        $this->authorizeSatgasIdentity($actor, $permission);

        if (! $case->activeAssignments()->where('satgas_id', $actor->id)->exists()) {
            throw $this->forbidden();
        }
    }

    private function canUploadToReport(User $actor, Report $report): bool
    {
        return $actor->is_active
            && $actor->hasRole('reporter')
            && $actor->hasPermission('reporter_evidence.upload.own')
            && $this->reportAcceptsUpload($report);
    }

    private function ensureUploadEligible(Report $report): void
    {
        if (! $this->reportAcceptsUpload($report)) {
            throw $this->conflict('This complaint cannot accept supporting files');
        }
    }

    private function reportAcceptsUpload(Report $report): bool
    {
        if (! in_array($report->status, self::UPLOADABLE_REPORT_STATUSES, true)) {
            return false;
        }

        $case = $report->case;

        if ($case === null) {
            return true;
        }

        return ! $case->isOperationallyTerminal();
    }

    /**
     * @return array{temporary_path: string, mime_type: string, extension: string, file_size: int, checksum: string, original_filename: string}
     */
    private function validatedFileData(UploadedFile $file): array
    {
        $temporaryPath = $file->getRealPath();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $clientName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $clientExtension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        $extension = is_string($mimeType) ? (self::EXTENSION_BY_MIME[$mimeType] ?? null) : null;

        if (
            ! $file->isValid()
            || ! is_string($temporaryPath)
            || $temporaryPath === ''
            || ! is_string($mimeType)
            || $extension === null
            || ! in_array($clientExtension, self::CLIENT_EXTENSIONS_BY_MIME[$mimeType] ?? [], true)
            || substr_count($clientName, '.') !== 1
            || $fileSize === false
            || $fileSize < 1
            || $fileSize > self::MAX_FILE_SIZE
        ) {
            throw $this->unprocessable('Invalid supporting file');
        }

        $checksum = hash_file('sha256', $temporaryPath);

        if ($checksum === false) {
            throw $this->serverError('The supporting file could not be processed');
        }

        return [
            'temporary_path' => $temporaryPath,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'file_size' => $fileSize,
            'checksum' => $checksum,
            'original_filename' => $this->sanitizeOriginalFilename($clientName, $extension),
        ];
    }

    private function sanitizeOriginalFilename(string $filename, string $fallbackExtension): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        if ($filename === '') {
            return 'supporting-file.'.$fallbackExtension;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $extension = $fallbackExtension;
        }

        $suffix = '.'.$extension;
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = mb_strimwidth($stem, 0, max(1, 255 - mb_strwidth($suffix)), '');
        $stem = rtrim($stem, " .\t\n\r\0\x0B");

        return ($stem !== '' ? $stem : 'supporting-file').$suffix;
    }

    private function isExpectedStoragePath(ReportEvidenceSubmission $submission): bool
    {
        $extension = self::EXTENSION_BY_MIME[$submission->mime_type] ?? null;

        if ($extension === null || ! is_string($submission->storage_path)) {
            return false;
        }

        $pattern = sprintf(
            '#^reports/%d/reporter-submissions/%s\.%s$#D',
            $submission->report_id,
            preg_quote($submission->uuid, '#'),
            preg_quote($extension, '#'),
        );

        return preg_match($pattern, $submission->storage_path) === 1;
    }

    private function recordAudit(
        AuditAction $action,
        ReportEvidenceSubmission $submission,
        User $actor,
    ): void {
        $submission->loadMissing('report');
        $isOversight = $this->campusScope->canSensitiveOversight($actor);
        $metadata = ['attachment_uuid' => $submission->uuid];

        if ($isOversight) {
            $metadata['registration_number'] = $submission->report?->registration_number;
            $metadata['cross_campus_read'] = true;
        }

        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Evidence,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $submission,
            metadata: $metadata,
            isElevatedAccess: $isOversight,
        );
    }

    private function forbidden(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action',
            'errors' => null,
        ], 403));
    }

    private function notFound(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Supporting file not found',
            'errors' => null,
        ], 404));
    }

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }

    private function conflict(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 409));
    }

    private function serverError(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 500));
    }
}
