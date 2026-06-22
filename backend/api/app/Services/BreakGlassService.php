<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\BreakGlassRequest;
use App\Models\Report;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class BreakGlassService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function request(array $data, User $requestor): BreakGlassRequest
    {
        return DB::transaction(function () use ($data, $requestor): BreakGlassRequest {
            $report = Report::query()
                ->whereKey((int) $data['report_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($report->report_type !== 'anonymous') {
                throw $this->unprocessable('Break-glass requests are only allowed for anonymous reports');
            }

            $this->ensureNoPendingRequestForReport($report);

            $breakGlassRequest = BreakGlassRequest::query()->create([
                'requestor_id' => $requestor->id,
                'report_id' => $report->id,
                'reason_category' => $data['reason_category'],
                'reason' => $data['reason'],
                'status' => BreakGlassRequest::STATUS_PENDING,
                'requested_at' => now(),
            ]);

            $this->recordAudit(
                AuditAction::BreakGlassRequested,
                $requestor,
                $breakGlassRequest,
                [
                    'report_id' => $report->id,
                    'registration_number' => $report->registration_number,
                    'reason_category' => $breakGlassRequest->reason_category,
                ],
                afterChanges: ['status' => BreakGlassRequest::STATUS_PENDING],
            );

            $this->notifySuperAdminsOfRequest($breakGlassRequest->load('report', 'requestor'));

            return $breakGlassRequest->load(['requestor.role', 'approver.role', 'report']);
        });
    }

    /**
     * @return LengthAwarePaginator<int, BreakGlassRequest>
     */
    public function pending(int $perPage = 15): LengthAwarePaginator
    {
        return BreakGlassRequest::query()
            ->with(['requestor.role', 'approver.role', 'report'])
            ->where('status', BreakGlassRequest::STATUS_PENDING)
            ->oldest('requested_at')
            ->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, BreakGlassRequest>
     */
    public function history(int $perPage = 15): LengthAwarePaginator
    {
        return BreakGlassRequest::query()
            ->with(['requestor.role', 'approver.role', 'report'])
            ->latest('requested_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function approve(BreakGlassRequest $breakGlassRequest, User $approver): BreakGlassRequest
    {
        return DB::transaction(function () use ($breakGlassRequest, $approver): BreakGlassRequest {
            $breakGlassRequest = BreakGlassRequest::query()
                ->whereKey($breakGlassRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $breakGlassRequest->isPending()) {
                throw $this->unprocessable('Only pending break-glass requests can be approved');
            }

            $before = ['status' => $breakGlassRequest->status];

            $breakGlassRequest->forceFill([
                'approver_id' => $approver->id,
                'status' => BreakGlassRequest::STATUS_APPROVED,
                'approved_at' => now(),
            ])->save();

            $breakGlassRequest->load(['requestor.role', 'approver.role', 'report.reporter']);

            $this->recordAudit(
                AuditAction::BreakGlassApproved,
                $approver,
                $breakGlassRequest,
                [
                    'report_id' => $breakGlassRequest->report_id,
                    'registration_number' => $breakGlassRequest->report?->registration_number,
                    'reason_category' => $breakGlassRequest->reason_category,
                ],
                beforeChanges: $before,
                afterChanges: ['status' => BreakGlassRequest::STATUS_APPROVED],
            );

            $this->notifyRequestorResolved($breakGlassRequest, approved: true);
            $this->notifyReporterApproved($breakGlassRequest);

            return $breakGlassRequest;
        });
    }

    public function deny(BreakGlassRequest $breakGlassRequest, User $approver, string $denialReason): BreakGlassRequest
    {
        return DB::transaction(function () use ($breakGlassRequest, $approver, $denialReason): BreakGlassRequest {
            $breakGlassRequest = BreakGlassRequest::query()
                ->whereKey($breakGlassRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $breakGlassRequest->isPending()) {
                throw $this->unprocessable('Only pending break-glass requests can be denied');
            }

            $before = ['status' => $breakGlassRequest->status];

            $breakGlassRequest->forceFill([
                'approver_id' => $approver->id,
                'status' => BreakGlassRequest::STATUS_DENIED,
                'denied_at' => now(),
                'denial_reason' => $denialReason,
            ])->save();

            $breakGlassRequest->load(['requestor.role', 'approver.role', 'report']);

            $this->recordAudit(
                AuditAction::BreakGlassDenied,
                $approver,
                $breakGlassRequest,
                [
                    'report_id' => $breakGlassRequest->report_id,
                    'registration_number' => $breakGlassRequest->report?->registration_number,
                    'reason_category' => $breakGlassRequest->reason_category,
                ],
                beforeChanges: $before,
                afterChanges: ['status' => BreakGlassRequest::STATUS_DENIED],
            );

            $this->notifyRequestorResolved($breakGlassRequest, approved: false);

            return $breakGlassRequest;
        });
    }

    /**
     * @return array{name: string|null, email: string|null, break_glass_reference: int, valid_until: string|null}
     */
    public function reveal(BreakGlassRequest $breakGlassRequest, User $viewer): array
    {
        return DB::transaction(function () use ($breakGlassRequest, $viewer): array {
            $breakGlassRequest = BreakGlassRequest::query()
                ->with('report.reporter')
                ->whereKey($breakGlassRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $breakGlassRequest->isViewable()) {
                throw $this->forbidden('Break-glass access has expired or is not approved');
            }

            $before = [
                'status' => $breakGlassRequest->status,
                'viewed_at' => $breakGlassRequest->viewed_at?->toJSON(),
            ];

            if ($breakGlassRequest->viewed_at === null) {
                $breakGlassRequest->forceFill([
                    'status' => BreakGlassRequest::STATUS_VIEWED,
                    'viewed_at' => now(),
                ])->save();
            }

            $breakGlassRequest->refresh()->load('report.reporter');
            $reporter = $breakGlassRequest->report?->reporter;

            $this->recordAudit(
                AuditAction::BreakGlassIdentityViewed,
                $viewer,
                $breakGlassRequest,
                [
                    'report_id' => $breakGlassRequest->report_id,
                    'registration_number' => $breakGlassRequest->report?->registration_number,
                ],
                beforeChanges: $before,
                afterChanges: [
                    'status' => $breakGlassRequest->status,
                    'viewed_at' => $breakGlassRequest->viewed_at?->toJSON(),
                ],
            );

            return [
                'name' => $reporter?->name,
                'email' => $reporter?->email,
                'break_glass_reference' => $breakGlassRequest->id,
                'valid_until' => $breakGlassRequest->viewed_at?->copy()->addHours(8)->toJSON(),
            ];
        });
    }

    private function ensureNoPendingRequestForReport(Report $report): void
    {
        $hasPendingRequest = BreakGlassRequest::query()
            ->where('report_id', $report->id)
            ->where('status', BreakGlassRequest::STATUS_PENDING)
            ->exists();

        if ($hasPendingRequest) {
            throw $this->unprocessable('A pending break-glass request already exists for this report');
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $beforeChanges
     * @param array<string, mixed> $afterChanges
     */
    private function recordAudit(
        AuditAction $action,
        User $actor,
        BreakGlassRequest $subject,
        array $metadata = [],
        array $beforeChanges = [],
        array $afterChanges = [],
    ): void {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::Privacy,
            severity: AuditSeverity::Critical,
            actor: $actor,
            subject: $subject,
            metadata: $metadata,
            beforeChanges: $beforeChanges,
            afterChanges: $afterChanges,
            isElevatedAccess: true,
        );
    }

    private function notifySuperAdminsOfRequest(BreakGlassRequest $breakGlassRequest): void
    {
        User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'super_admin'))
            ->get()
            ->each(fn (User $superAdmin) => $superAdmin->notify(new WorkflowDatabaseNotification([
                'notification_type_code' => 'break_glass_request',
                'event' => 'break_glass_request',
                'title' => 'Permintaan Break-Glass Baru',
                'body' => 'Permintaan break-glass baru membutuhkan peninjauan.',
                'break_glass_request_id' => $breakGlassRequest->id,
                'report_id' => $breakGlassRequest->report_id,
                'registration_number' => $breakGlassRequest->report?->registration_number,
                'reason_category' => $breakGlassRequest->reason_category,
            ])));
    }

    private function notifyRequestorResolved(BreakGlassRequest $breakGlassRequest, bool $approved): void
    {
        $breakGlassRequest->requestor?->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => $approved ? 'break_glass_approved' : 'break_glass_denied',
            'event' => $approved ? 'break_glass_approved' : 'break_glass_denied',
            'title' => $approved ? 'Permintaan Break-Glass Disetujui' : 'Permintaan Break-Glass Ditolak',
            'body' => $approved
                ? 'Permintaan break-glass Anda telah disetujui. Akses berlaku selama 8 jam sejak pertama dibuka.'
                : 'Permintaan break-glass Anda ditolak.',
            'break_glass_request_id' => $breakGlassRequest->id,
            'report_id' => $breakGlassRequest->report_id,
            'registration_number' => $breakGlassRequest->report?->registration_number,
        ]));
    }

    private function notifyReporterApproved(BreakGlassRequest $breakGlassRequest): void
    {
        $report = $breakGlassRequest->report;
        $reporter = $report?->reporter;

        if (! $reporter) {
            return;
        }

        $reporter->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => 'privacy_notice',
            'event' => 'break_glass_approved',
            'title' => 'Pemberitahuan Privasi',
            'body' => 'Identitas Anda pada laporan '.$report->registration_number.' telah diungkapkan melalui prosedur break-glass sesuai kebijakan privasi SILAPPKASAL.',
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'break_glass_request_id' => $breakGlassRequest->id,
        ]));
    }

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }

    private function forbidden(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 403));
    }
}
