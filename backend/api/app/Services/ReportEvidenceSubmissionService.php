<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus as CaseStatusModel;
use App\Models\Report;
use App\Models\ReportEvidenceSubmission;
use App\Models\User;
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

    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @return array{files: Collection<int, ReportEvidenceSubmission>, upload_allowed: bool, remaining_slots: int}
     */
    public function listForReporter(User $actor, string $registrationNumber): array
    {
        $this->authorizeReporter($actor, 'reporter_evidence.read.own');
        $report = $this->ownedReportOrFail($actor, $registrationNumber);
        $files = $report->evidenceSubmissions()->latest('uploaded_at')->get();
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

        $submission = ReportEvidenceSubmission::query()
            ->where('uuid', $uuid)
            ->whereHas('report', fn ($query) => $query
                ->where('reporter_id', $actor->id)
                ->whereNotNull('reporter_id'))
            ->first() ?? throw $this->notFound();

        return $this->download(
            $submission,
            $actor,
            AuditAction::ReporterEvidenceDownloadedByReporter,
        );
    }

    /**
     * @return Collection<int, ReportEvidenceSubmission>
     */
    public function listForAssignedSatgas(User $actor, CaseRecord $case): Collection
    {
        $this->authorizeAssignedSatgasForCase($actor, $case, 'reporter_evidence.read.assigned');

        return ReportEvidenceSubmission::query()
            ->where('report_id', $case->report_id)
            ->latest('uploaded_at')
            ->get();
    }

    public function downloadForAssignedSatgas(User $actor, string $uuid): StreamedResponse
    {
        $this->authorizeSatgasIdentity($actor, 'reporter_evidence.download.assigned');

        $submission = ReportEvidenceSubmission::query()
            ->where('uuid', $uuid)
            ->whereHas('report.case.activeAssignments', fn ($query) => $query
                ->where('satgas_id', $actor->id))
            ->first() ?? throw $this->notFound();

        return $this->download(
            $submission,
            $actor,
            AuditAction::ReporterEvidenceDownloadedBySatgas,
        );
    }

    private function download(
        ReportEvidenceSubmission $submission,
        User $actor,
        AuditAction $action,
    ): StreamedResponse {
        if (
            $submission->storage_disk !== self::FILE_DISK
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
        $filename = $this->sanitizeOriginalFilename($submission->original_filename, $extension);
        $mimeType = isset(self::EXTENSION_BY_MIME[$submission->mime_type])
            ? $submission->mime_type
            : 'application/octet-stream';

        try {
            DB::transaction(fn () => $this->recordAudit(
                $action,
                $submission,
                $actor,
            ));
        } catch (Throwable $exception) {
            fclose($stream);
            throw $exception;
        }

        try {
            return response()->streamDownload(function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, $filename, [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $submission->file_size,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ], 'attachment');
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $exception;
        }
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
            throw $this->conflict('This report cannot accept supporting files');
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

        if ($case->closed_at !== null || $case->status?->name === CaseStatusEnum::Closed->value) {
            return false;
        }

        if ($case->status === null && $case->status_code !== null) {
            return ! CaseStatusModel::query()
                ->where('code', $case->status_code)
                ->where('name', CaseStatusEnum::Closed->value)
                ->exists();
        }

        return true;
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
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Evidence,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $submission,
            metadata: ['attachment_uuid' => $submission->uuid],
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
