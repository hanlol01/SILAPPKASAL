<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\CaseAssignment;
use App\Models\CaseClosureDocument;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Models\User;
use App\Policies\CaseClosureDocumentPolicy;
use App\Support\ApiErrorCode;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseClosureDocumentService
{
    public const FILE_DISK = 'case_documents';
    public const FILENAME = 'Berita Acara Hasil Pelaporan Kekerasan Seksual.pdf';

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseClosureDocumentPolicy $policy,
    ) {}

    /**
     * @return array{
     *     document: ?CaseClosureDocument,
     *     capabilities: array{manage: bool, issue: bool, preview: bool, download: bool},
     *     signer_options: array{selection_required: bool, eligible_signers: list<array{id: int, name: string, identity_number: string}>}
     * }
     */
    public function details(CaseRecord $case, User $actor): array
    {
        $case->loadMissing([
            'closureDocument.case.report.reporter',
            'status',
            'finalSummary',
            'report.reporter.university',
            'activeAssignments.satgas',
        ]);
        $document = $case->closureDocument;
        $canRead = $document !== null && $this->policy->view($actor, $document);
        $canManage = $this->policy->issue($actor, $case);
        $eligibleSigners = $canManage && $document === null
            ? $this->eligibleSigners($case)
            : collect();

        return [
            'document' => $canRead ? $document : null,
            'capabilities' => [
                'manage' => $canManage,
                'issue' => $canManage && $this->canIssue($case, $eligibleSigners->isNotEmpty()),
                'preview' => $canRead,
                'download' => $canRead,
            ],
            'signer_options' => [
                'selection_required' => $eligibleSigners->count() > 1,
                'eligible_signers' => $eligibleSigners
                    ->map(fn (User $signer): array => [
                        'id' => $signer->id,
                        'name' => $signer->name,
                        'identity_number' => $this->identityNumber($signer),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function issue(CaseRecord $case, User $actor, ?int $signerId = null): CaseClosureDocument
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($case, $actor, $signerId, &$storedPath): CaseClosureDocument {
                $case = CaseRecord::query()
                    ->with([
                        'status',
                        'report.reporter.university',
                        'finalSummary',
                        'activeAssignments.satgas',
                    ])
                    ->whereKey($case->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $actor = User::query()->with('role.permissions')->whereKey($actor->id)->firstOrFail();

                if (! $this->policy->issue($actor, $case)) throw $this->forbidden();
                if (CaseClosureDocument::query()->where('case_id', $case->id)->exists()) {
                    return CaseClosureDocument::query()->where('case_id', $case->id)->firstOrFail();
                }
                $this->assertIssuable($case);
                $signer = $this->resolveSigner($case, $signerId);
                $signerIdentityNumber = $this->identityNumber($signer);

                $issuedAt = now('Asia/Jakarta');
                $documentNumber = sprintf('BAHPKS/%s/%s', $issuedAt->format('Y'), $case->case_number);
                $pdf = $this->renderPdf($case, $signer->name, $signerIdentityNumber, $issuedAt, $documentNumber);
                $storedPath = sprintf('%s/%s.pdf', $case->id, (string) \Illuminate\Support\Str::uuid());

                if (! Storage::disk(self::FILE_DISK)->put($storedPath, $pdf, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Case closure document could not be stored.');
                }

                $document = CaseClosureDocument::query()->create([
                    'case_id' => $case->id,
                    'final_summary_id' => $case->finalSummary->id,
                    'signer_id' => $signer->id,
                    'signer_name' => $signer->name,
                    'signer_identity_number' => $signerIdentityNumber,
                    'document_number' => $documentNumber,
                    'storage_disk' => self::FILE_DISK,
                    'storage_path' => $storedPath,
                    'checksum_sha256' => hash('sha256', $pdf),
                    'file_size' => strlen($pdf),
                    'issued_by' => $actor->id,
                    'issued_at' => $issuedAt,
                ]);

                $this->audit(AuditAction::CaseClosureDocumentIssued, $document, $case, $actor, 'issued');

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) Storage::disk(self::FILE_DISK)->delete($storedPath);
            throw $exception;
        }
    }

    public function download(CaseClosureDocument $document, User $actor, bool $preview = false): StreamedResponse
    {
        $document->loadMissing('case.report.reporter');
        if (! $this->policy->view($actor, $document)) throw $this->notFound();

        return $this->stream($document, $actor, $preview);
    }

    public function downloadForReporter(string $registrationNumber, User $actor, bool $preview = false): StreamedResponse
    {
        $report = Report::query()
            ->with('case.closureDocument.case.report.reporter')
            ->where('registration_number', $registrationNumber)
            ->where('reporter_id', $actor->id)
            ->first();
        $document = $report?->case?->closureDocument;
        if ($document === null || ! $this->policy->view($actor, $document)) throw $this->notFound();

        return $this->stream($document, $actor, $preview);
    }

    private function canIssue(CaseRecord $case, ?bool $hasEligibleSigner = null): bool
    {
        $case->loadMissing(['status', 'finalSummary', 'report.reporter.university', 'activeAssignments.satgas']);

        return $this->hasIssuancePrerequisites($case)
            && ($hasEligibleSigner ?? $this->eligibleSigners($case)->isNotEmpty());
    }

    private function assertIssuable(CaseRecord $case): void
    {
        if (! $this->hasIssuancePrerequisites($case)) {
            throw $this->unprocessableCode(ApiErrorCode::CaseClosureDocumentPrerequisitesMissing);
        }
    }

    private function hasIssuancePrerequisites(CaseRecord $case): bool
    {
        return $case->closureDocument === null
            && $case->isClosed()
            && $case->finalSummary?->isPublished() === true
            && filled($case->report?->reporter?->university?->address);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function eligibleSigners(CaseRecord $case): \Illuminate\Database\Eloquent\Collection
    {
        $case->loadMissing('activeAssignments.satgas');

        return $case->activeAssignments
            ->map(fn (CaseAssignment $assignment): ?User => $assignment->satgas)
            ->filter(fn (?User $satgas): bool => $satgas?->is_active === true
                && $satgas->hasRole('satgas_ppks')
                && filled($this->identityNumber($satgas)))
            ->unique('id')
            ->sortBy(fn (User $satgas): string => sprintf('%s-%020d', mb_strtolower($satgas->name), $satgas->id))
            ->values();
    }

    private function resolveSigner(CaseRecord $case, ?int $signerId): User
    {
        $eligibleSigners = $this->eligibleSigners($case);
        if ($eligibleSigners->isEmpty()) {
            throw $this->unprocessableCode(ApiErrorCode::CaseClosureDocumentSignerMissing);
        }

        if ($signerId === null) {
            if ($eligibleSigners->count() > 1) {
                throw $this->unprocessableCode(ApiErrorCode::CaseClosureDocumentSignerSelectionRequired);
            }

            return $eligibleSigners->firstOrFail();
        }

        $signer = $eligibleSigners->first(fn (User $candidate): bool => $candidate->id === $signerId);
        if ($signer === null) {
            throw $this->unprocessableCode(ApiErrorCode::CaseClosureDocumentSignerInvalid);
        }

        return $signer;
    }

    private function identityNumber(User $signer): string
    {
        return trim((string) $signer->nip);
    }

    private function renderPdf(
        CaseRecord $case,
        string $signerName,
        string $signerIdentityNumber,
        \Carbon\CarbonInterface $issuedAt,
        string $documentNumber,
    ): string
    {
        $university = $case->report?->reporter?->university;
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Times-Roman');
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('pdf.case-closure-document', [
            'universityName' => $university->name,
            'universityAddress' => $university->address,
            'registrationNumber' => $case->registration_number,
            'documentNumber' => $documentNumber,
            'receivedDate' => $case->report?->submitted_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y'),
            'issuedDay' => $issuedAt->copy()->locale('id')->translatedFormat('l'),
            'issuedDateNumber' => $issuedAt->format('d'),
            'issuedMonth' => $issuedAt->copy()->locale('id')->translatedFormat('F'),
            'issuedYear' => $issuedAt->format('Y'),
            'issuedDateLong' => $issuedAt->copy()->locale('id')->translatedFormat('l, d F Y'),
            'caseStatus' => 'Ditutup',
            'signerName' => $signerName,
            'signerIdentityNumber' => $signerIdentityNumber,
        ])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }

    private function stream(CaseClosureDocument $document, User $actor, bool $preview): StreamedResponse
    {
        if ($document->storage_disk !== self::FILE_DISK || ! Storage::disk(self::FILE_DISK)->exists($document->storage_path)) {
            throw $this->notFound();
        }
        $stream = Storage::disk(self::FILE_DISK)->readStream($document->storage_path);
        if ($stream === false) throw $this->notFound();
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) $document->file_size,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];
        if ($preview) $headers['Cross-Origin-Resource-Policy'] = 'same-origin';
        $action = $preview ? AuditAction::CaseClosureDocumentPreviewed : AuditAction::CaseClosureDocumentDownloaded;

        return response()->streamDownload(function () use ($stream, $action, $document, $actor, $preview): void {
            try {
                if (fpassthru($stream) === false) throw new \RuntimeException('Case closure document stream failed.');
                DB::transaction(fn () => $this->audit($action, $document, $document->case, $actor, $preview ? 'previewed' : 'downloaded'));
            } finally {
                if (is_resource($stream)) fclose($stream);
            }
        }, self::FILENAME, $headers, $preview ? 'inline' : 'attachment');
    }

    private function audit(AuditAction $action, CaseClosureDocument $document, ?CaseRecord $case, User $actor, string $result): void
    {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Case,
            severity: AuditSeverity::Info,
            actor: $actor,
            subject: $document,
            metadata: [
                'case_number' => $case?->case_number,
                'document_public_id' => $document->public_id,
                'document_number' => $document->document_number,
                'access_scope' => $actor->role?->code,
                'result' => $result,
            ],
        );
    }

    private function notFound(): ModelNotFoundException { return (new ModelNotFoundException)->setModel(CaseClosureDocument::class); }
    private function forbidden(): HttpResponseException { return new HttpResponseException(response()->json(['success' => false, 'message' => __('api.errors.forbidden'), 'error_code' => ApiErrorCode::Forbidden, 'errors' => null], 403)); }
    private function unprocessableCode(string $code): HttpResponseException { return new HttpResponseException(response()->json(['success' => false, 'message' => __("api.errors.{$code}"), 'error_code' => $code, 'errors' => null], 422)); }
}
