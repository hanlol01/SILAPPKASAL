<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\EvidenceClassification;
use App\Enums\EvidenceCustodyEventType;
use App\Enums\EvidenceStatus;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\Investigation;
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

class EvidenceService
{
    private const FILE_DISK = 'evidence';

    /** @var array<string, string> */
    private const EXTENSION_BY_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForInvestigation(Investigation $investigation, User $actor, array $data): Evidence
    {
        $this->authorizeAssignedSatgas($investigation, $actor);
        $this->ensureInvestigationCanAcceptEvidence($investigation);

        return DB::transaction(function () use ($investigation, $actor, $data): Evidence {
            $investigation = Investigation::query()->with(['case.status', 'status'])->whereKey($investigation->id)->lockForUpdate()->firstOrFail();
            $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();

            $this->authorizeAssignedSatgas($investigation, $actor, lockAssignment: true);
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
        $this->authorizeEvidenceRead($investigation, $user, 'evidence.view.case');

        return Evidence::query()
            ->where('investigation_id', $investigation->id)
            ->with($this->summaryRelations())
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    public function loadForUser(Evidence $evidence, User $user): Evidence
    {
        $evidence->loadMissing('investigation.case');
        $this->authorizeEvidenceRead($evidence->investigation, $user, 'evidence.view.case');

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
            ->oldest('created_at')
            ->oldest('id')
            ->get();
    }

    public function uploadFile(Evidence $evidence, User $actor, UploadedFile $file): Evidence
    {
        $evidence->loadMissing('investigation.case.status');
        $this->authorizeAssignedSatgas($evidence->investigation, $actor, 'evidence.upload');
        $this->ensureEvidenceOpen($evidence);
        $this->ensureInvestigationCanAcceptEvidence($evidence->investigation);

        $mimeType = $file->getMimeType();
        $extension = $mimeType ? (self::EXTENSION_BY_MIME[$mimeType] ?? null) : null;
        $temporaryPath = $file->getRealPath();
        $fileSize = $file->getSize();

        if (! $extension || ! $temporaryPath || $fileSize === false || $fileSize < 1) {
            throw $this->unprocessable('Invalid evidence file');
        }

        $checksum = hash_file('sha256', $temporaryPath);

        if ($checksum === false) {
            throw $this->serverError('Evidence file could not be processed');
        }

        $originalFilename = $this->sanitizeOriginalFilename($file->getClientOriginalName(), $extension);
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $evidence,
                $actor,
                $temporaryPath,
                $mimeType,
                $extension,
                $fileSize,
                $checksum,
                $originalFilename,
                &$storedPath,
            ): Evidence {
                $lockedEvidence = Evidence::query()
                    ->with('investigation.case.status')
                    ->whereKey($evidence->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeAssignedSatgas($lockedEvidence->investigation, $actor, 'evidence.upload');
                $this->ensureEvidenceOpen($lockedEvidence);
                $this->ensureInvestigationCanAcceptEvidence($lockedEvidence->investigation);

                if ($lockedEvidence->storage_path !== null) {
                    throw $this->conflict('An evidence file is already attached');
                }

                $storedPath = sprintf(
                    'cases/%d/evidences/%d/%s.%s',
                    $lockedEvidence->investigation->case_id,
                    $lockedEvidence->id,
                    Str::uuid()->toString(),
                    $extension,
                );
                $source = fopen($temporaryPath, 'rb');

                if ($source === false) {
                    throw $this->serverError('Evidence file could not be processed');
                }

                try {
                    $stored = Storage::disk(self::FILE_DISK)->writeStream($storedPath, $source, [
                        'visibility' => 'private',
                    ]);
                } finally {
                    fclose($source);
                }

                if (! $stored) {
                    throw $this->serverError('Evidence file could not be stored');
                }

                $lockedEvidence->forceFill([
                    'original_filename' => $originalFilename,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'checksum_sha256' => $checksum,
                    'storage_disk' => self::FILE_DISK,
                    'storage_path' => $storedPath,
                    'file_uploaded_by' => $actor->id,
                    'file_uploaded_at' => now(),
                ])->save();

                $this->recordCustodyEvent($lockedEvidence, EvidenceCustodyEventType::FileUploaded, $actor, [
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
                $this->recordFileAudit(AuditAction::EvidenceFileUploaded, $lockedEvidence, $actor);

                return $lockedEvidence->load($this->detailRelations());
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk(self::FILE_DISK)->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function downloadFile(Evidence $evidence, User $actor): StreamedResponse
    {
        $evidence->loadMissing('investigation.case');
        $this->authorizeEvidenceRead($evidence->investigation, $actor, 'evidence.download');

        $file = $this->openEvidenceFile($evidence);
        $response = $this->streamEvidenceFile(
            $file['stream'],
            $file['filename'],
            $file['mime_type'],
            $file['file_size'],
            'attachment',
        );

        try {
            DB::transaction(function () use ($evidence, $actor): void {
                $this->recordCustodyEvent($evidence, EvidenceCustodyEventType::FileDownloaded, $actor, [
                    'mime_type' => $evidence->mime_type,
                    'file_size' => $evidence->file_size,
                ]);
                $this->recordFileAudit(
                    $this->campusScope->canSensitiveOversight($actor)
                        ? AuditAction::EvidenceFileDownloadedByOversight
                        : AuditAction::EvidenceFileDownloaded,
                    $evidence,
                    $actor,
                );
            });
        } catch (Throwable $exception) {
            if (is_resource($file['stream'])) {
                fclose($file['stream']);
            }

            throw $exception;
        }

        return $response;
    }

    public function previewFile(Evidence $evidence, User $actor): StreamedResponse
    {
        $evidence->loadMissing('investigation.case');
        $this->authorizeEvidenceRead($evidence->investigation, $actor, 'evidence.download');

        $file = $this->openEvidenceFile($evidence, preview: true);
        return $this->streamEvidenceFile(
            $file['stream'],
            $file['filename'],
            $file['mime_type'],
            $file['file_size'],
            'inline',
            sameOrigin: true,
            afterStream: function () use ($evidence, $actor): void {
                DB::transaction(function () use ($evidence, $actor): void {
                    $this->recordCustodyEvent($evidence, EvidenceCustodyEventType::FilePreviewed, $actor);
                    $this->recordFileAudit(
                        $this->campusScope->canSensitiveOversight($actor)
                            ? AuditAction::EvidenceFilePreviewedByOversight
                            : AuditAction::EvidenceFilePreviewed,
                        $evidence,
                        $actor,
                    );
                });
            },
        );
    }

    /**
     * @return array{stream: resource, filename: string, mime_type: string, file_size: int}
     */
    private function openEvidenceFile(Evidence $evidence, bool $preview = false): array
    {
        if (
            $evidence->storage_disk !== self::FILE_DISK
            || ! is_string($evidence->storage_path)
        ) {
            throw $this->notFound();
        }

        $trustedMime = is_string($evidence->mime_type)
            && isset(self::EXTENSION_BY_MIME[$evidence->mime_type]);

        if ($preview && (! $trustedMime || ! is_int($evidence->file_size) || $evidence->file_size < 1)) {
            throw $this->unprocessable('Evidence file cannot be previewed');
        }

        if (
            ! $this->isExpectedStoragePath($evidence)
            || ! Storage::disk(self::FILE_DISK)->exists($evidence->storage_path)
        ) {
            throw $this->notFound();
        }

        $stream = Storage::disk(self::FILE_DISK)->readStream($evidence->storage_path);

        if ($stream === false) {
            throw $this->notFound();
        }

        $filename = $this->sanitizeOriginalFilename(
            $evidence->original_filename ?: 'evidence-'.$evidence->id,
            self::EXTENSION_BY_MIME[$evidence->mime_type] ?? 'bin',
        );
        $mimeType = isset(self::EXTENSION_BY_MIME[$evidence->mime_type])
            ? $evidence->mime_type
            : 'application/octet-stream';

        return [
            'stream' => $stream,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'file_size' => (int) $evidence->file_size,
        ];
    }

    /** @param resource $stream */
    private function streamEvidenceFile(
        mixed $stream,
        string $filename,
        string $mimeType,
        int $fileSize,
        string $disposition,
        bool $sameOrigin = false,
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

        if ($sameOrigin) {
            $headers['Cross-Origin-Resource-Policy'] = 'same-origin';
        }

        try {
            return response()->streamDownload(function () use ($stream, $afterStream): void {
                try {
                    if (fpassthru($stream) === false) {
                        throw new \RuntimeException('Evidence file stream failed');
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

    private function ensureInvestigationCanAcceptEvidence(Investigation $investigation): void
    {
        $investigation->loadMissing(['case.status', 'status']);

        if (
            $investigation->case?->closed_at !== null
            || $investigation->case?->status?->name === CaseStatusEnum::Closed->value
        ) {
            throw $this->unprocessable('Evidence cannot be added to a closed case');
        }

        if ($investigation->case?->status?->name !== CaseStatusEnum::Investigation->value) {
            throw $this->unprocessable('Evidence can only be added while the case is in investigation');
        }

        if ($investigation->status?->name === InvestigationStatusEnum::Completed->value) {
            throw $this->unprocessable('Evidence cannot be added to a completed investigation');
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

    private function authorizeAssignedSatgas(
        Investigation $investigation,
        User $actor,
        string $capability = 'evidence.upload',
        bool $lockAssignment = false,
    ): void
    {
        if (! $actor->is_active || ! $actor->hasPermission($capability) || ! $actor->hasPermission('evidence.view.case') || ! $actor->hasRole('satgas_ppks')) {
            throw $this->forbidden();
        }

        $assignmentQuery = \App\Models\CaseAssignment::query()
            ->where('case_id', $investigation->case_id)
            ->where('satgas_id', $actor->id)
            ->where('is_active', true);
        $isAssigned = $lockAssignment
            ? $assignmentQuery->lockForUpdate()->first() !== null
            : $assignmentQuery->exists();

        if (! $isAssigned) {
            throw $this->forbidden();
        }
    }

    private function authorizeEvidenceRead(Investigation $investigation, User $actor, string $capability): void
    {
        if ($this->campusScope->canSensitiveOversight($actor)) {
            return;
        }

        $this->authorizeAssignedSatgas($investigation, $actor, $capability);
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
            'fileUploader',
        ];
    }

    private function sanitizeOriginalFilename(string $filename, string $fallbackExtension): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        if ($filename === '') {
            return 'evidence.'.$fallbackExtension;
        }

        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.'.$fallbackExtension;
        }

        return mb_strimwidth($filename, 0, 255, '');
    }

    private function isExpectedStoragePath(Evidence $evidence): bool
    {
        $extension = self::EXTENSION_BY_MIME[$evidence->mime_type] ?? null;

        if ($extension === null) {
            return false;
        }

        $pattern = sprintf(
            '#^cases/%d/evidences/%d/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.%s$#D',
            $evidence->investigation->case_id,
            $evidence->id,
            preg_quote($extension, '#'),
        );

        return preg_match($pattern, $evidence->storage_path) === 1;
    }

    private function recordFileAudit(AuditAction $action, Evidence $evidence, User $actor): void
    {
        $isOversight = $this->campusScope->canSensitiveOversight($actor);

        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Evidence,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $evidence,
            metadata: [
                'evidence_id' => $evidence->id,
                'case_id' => $evidence->investigation->case_id,
                'case_number' => $evidence->investigation?->case?->case_number,
                'cross_campus_read' => $isOversight,
            ],
            isElevatedAccess: $isOversight,
        );
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

    private function conflict(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 409));
    }

    private function notFound(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Evidence file not found',
            'errors' => null,
        ], 404));
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
