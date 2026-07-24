<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ReportStatus;
use App\Models\BreakGlassRequest;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use App\Support\CaseCampusScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class BreakGlassService
{
    private const RELATIONS = [
        'requestor.role',
        'approver.role',
        'report.case.activeAssignments',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(array $data, User $requestor): BreakGlassRequest
    {
        try {
            return DB::transaction(function () use ($data, $requestor): BreakGlassRequest {
                $requestor = User::query()
                    ->with('role.permissions')
                    ->whereKey($requestor->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $case = CaseRecord::query()
                    ->with('report.reporter')
                    ->whereKey((int) $data['case_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertSatgasRequester($requestor);
                $report = $this->validAnonymousReportForCase($case);
                $this->assertNotWithdrawn($report, $case);
                $this->assertSatgasCampus($requestor, $report);
                $this->activeAssignmentOrFail($case, $requestor, lock: true);
                $this->normalizeExpiredPair($report->id, $requestor->id);
                $this->ensureNoPendingOrActiveRequest($report, $requestor);

                $breakGlassRequest = BreakGlassRequest::query()->create([
                    'requestor_id' => $requestor->id,
                    'report_id' => $report->id,
                    'reason_category' => $data['reason_category'],
                    'reason' => $data['reason'],
                    'requested_duration_minutes' => (int) $data['requested_duration_minutes'],
                    'status' => BreakGlassRequest::STATUS_PENDING,
                    'requested_at' => now(),
                ]);
                $breakGlassRequest->load(self::RELATIONS);

                $this->recordAudit(
                    AuditAction::BreakGlassRequested,
                    $requestor,
                    $breakGlassRequest,
                    afterChanges: ['status' => BreakGlassRequest::STATUS_PENDING],
                );
                $this->notifyCampusAdminsOfRequest($breakGlassRequest);

                return $this->decorate($breakGlassRequest, $requestor);
            });
        } catch (QueryException $exception) {
            $duplicateExists = BreakGlassRequest::query()
                ->where('report_id', Report::query()
                    ->whereHas('case', fn (Builder $query): Builder => $query->whereKey((int) $data['case_id']))
                    ->value('id'))
                ->where('requestor_id', $requestor->id)
                ->whereNull('revoked_at')
                ->whereIn('status', [
                    BreakGlassRequest::STATUS_PENDING,
                    BreakGlassRequest::STATUS_APPROVED,
                    BreakGlassRequest::STATUS_VIEWED,
                ])
                ->exists();

            if ($duplicateExists) {
                throw $this->unprocessable('A pending or active emergency-access request already exists for this case');
            }

            throw $exception;
        }
    }

    /** @return LengthAwarePaginator<int, BreakGlassRequest> */
    public function pending(User $actor, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertAdminReviewer($actor);

        $requests = $this->campusQuery($actor)
            ->where('status', BreakGlassRequest::STATUS_PENDING)
            ->oldest('requested_at')
            ->oldest('id')
            ->paginate($perPage);

        return $this->decoratePaginator($requests, $actor);
    }

    /** @return LengthAwarePaginator<int, BreakGlassRequest> */
    public function history(User $actor, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertAdminReviewer($actor);
        $this->normalizeExpiredForCampus($actor);

        $requests = $this->campusQuery($actor)
            ->latest('requested_at')
            ->latest('id')
            ->paginate($perPage);

        return $this->decoratePaginator($requests, $actor);
    }

    /** @return LengthAwarePaginator<int, BreakGlassRequest> */
    public function mine(User $actor, int $perPage = 15, ?int $caseId = null): LengthAwarePaginator
    {
        $this->assertSatgasRequester($actor);
        $this->normalizeExpiredForRequester($actor);

        $requests = BreakGlassRequest::query()
            ->with(self::RELATIONS)
            ->where('requestor_id', $actor->id)
            ->when($caseId !== null, fn (Builder $query): Builder => $query
                ->whereHas('report.case', fn (Builder $case): Builder => $case->whereKey($caseId)))
            ->latest('requested_at')
            ->latest('id')
            ->paginate($perPage);

        return $this->decoratePaginator($requests, $actor);
    }

    public function loadForUser(BreakGlassRequest $breakGlassRequest, User $actor): BreakGlassRequest
    {
        $this->normalizeExpiredById($breakGlassRequest->id);

        return $this->decorate(
            BreakGlassRequest::query()->with(self::RELATIONS)->findOrFail($breakGlassRequest->id),
            $actor,
        );
    }

    public function approve(BreakGlassRequest $breakGlassRequest, User $approver): BreakGlassRequest
    {
        return DB::transaction(function () use ($breakGlassRequest, $approver): BreakGlassRequest {
            $approver = $this->lockedActor($approver);
            $breakGlassRequest = $this->lockedRequest($breakGlassRequest);
            $this->assertAdminCanManage($approver, $breakGlassRequest);

            if (! $breakGlassRequest->isPending()) {
                throw $this->unprocessable('Only pending emergency-access requests can be approved');
            }

            $case = $this->validCaseForRequest($breakGlassRequest);
            $this->assertNotWithdrawn($breakGlassRequest->report, $case);
            $requestor = User::query()
                ->with('role.permissions')
                ->whereKey($breakGlassRequest->requestor_id)
                ->lockForUpdate()
                ->first();

            if (! $requestor instanceof User) {
                throw $this->unprocessable('The emergency-access requester is no longer available');
            }

            $this->assertSatgasRequester($requestor, requireRevealPermission: true);
            $this->assertSatgasCampus($requestor, $breakGlassRequest->report);
            $this->activeAssignmentOrFail($case, $requestor, lock: true);

            $duration = (int) $breakGlassRequest->requested_duration_minutes;

            if (! in_array($duration, BreakGlassRequest::ALLOWED_DURATIONS, true)) {
                throw $this->unprocessable('The requested emergency-access duration is invalid');
            }

            $startsAt = now();
            $breakGlassRequest->forceFill([
                'approver_id' => $approver->id,
                'status' => BreakGlassRequest::STATUS_APPROVED,
                'approved_at' => $startsAt,
                'grant_starts_at' => $startsAt,
                'expires_at' => $startsAt->copy()->addMinutes($duration),
                'revoked_at' => null,
                'revoked_by' => null,
                'revocation_reason' => null,
            ])->save();
            $breakGlassRequest->refresh()->load(self::RELATIONS);

            $this->recordAudit(
                AuditAction::BreakGlassApproved,
                $approver,
                $breakGlassRequest,
                beforeChanges: ['status' => BreakGlassRequest::STATUS_PENDING],
                afterChanges: [
                    'status' => BreakGlassRequest::STATUS_APPROVED,
                    'expires_at' => $breakGlassRequest->expires_at?->toJSON(),
                ],
            );
            $this->notifyRequestorResolved($breakGlassRequest, 'approved');
            $this->notifyReporterApproved($breakGlassRequest);

            return $this->decorate($breakGlassRequest, $approver);
        });
    }

    public function deny(BreakGlassRequest $breakGlassRequest, User $approver, string $denialReason): BreakGlassRequest
    {
        return DB::transaction(function () use ($breakGlassRequest, $approver, $denialReason): BreakGlassRequest {
            $approver = $this->lockedActor($approver);
            $breakGlassRequest = $this->lockedRequest($breakGlassRequest);
            $this->assertAdminCanManage($approver, $breakGlassRequest);

            if (! $breakGlassRequest->isPending()) {
                throw $this->unprocessable('Only pending emergency-access requests can be denied');
            }

            $breakGlassRequest->forceFill([
                'approver_id' => $approver->id,
                'status' => BreakGlassRequest::STATUS_DENIED,
                'denied_at' => now(),
                'denial_reason' => $denialReason,
            ])->save();
            $breakGlassRequest->refresh()->load(self::RELATIONS);

            $this->recordAudit(
                AuditAction::BreakGlassDenied,
                $approver,
                $breakGlassRequest,
                beforeChanges: ['status' => BreakGlassRequest::STATUS_PENDING],
                afterChanges: ['status' => BreakGlassRequest::STATUS_DENIED],
            );
            $this->notifyRequestorResolved($breakGlassRequest, 'denied');

            return $this->decorate($breakGlassRequest, $approver);
        });
    }

    public function revoke(BreakGlassRequest $breakGlassRequest, User $actor, string $reason): BreakGlassRequest
    {
        $result = DB::transaction(function () use ($breakGlassRequest, $actor, $reason): ?BreakGlassRequest {
            $actor = $this->lockedActor($actor);
            $breakGlassRequest = $this->lockedRequest($breakGlassRequest);
            $this->assertAdminCanManage($actor, $breakGlassRequest);

            if ($this->normalizeExpiredLocked($breakGlassRequest)) {
                return null;
            }

            if (! $breakGlassRequest->isGrantActive()) {
                throw $this->unprocessable('Only an active emergency-access grant can be revoked');
            }

            $previousStatus = $breakGlassRequest->status;
            $breakGlassRequest->forceFill([
                'status' => BreakGlassRequest::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => $reason,
            ])->save();
            $breakGlassRequest->refresh()->load(self::RELATIONS);

            $this->recordAudit(
                AuditAction::BreakGlassRevoked,
                $actor,
                $breakGlassRequest,
                beforeChanges: ['status' => $previousStatus],
                afterChanges: ['status' => BreakGlassRequest::STATUS_REVOKED, 'revoked' => true],
            );
            $this->notifyRequestorResolved($breakGlassRequest, 'revoked');

            return $this->decorate($breakGlassRequest, $actor);
        });

        if ($result === null) {
            throw $this->unprocessable('The emergency-access grant has expired');
        }

        return $result;
    }

    /**
     * Revoke grants for the exact Report while the caller owns the surrounding
     * withdrawal transaction. History remains immutable and unrelated grants
     * are never selected.
     */
    public function revokeActiveForReportWithdrawal(Report $report, User $actor): int
    {
        $requests = BreakGlassRequest::query()
            ->with(self::RELATIONS)
            ->where('report_id', $report->id)
            ->whereNull('revoked_at')
            ->whereNotNull('grant_starts_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereIn('status', [
                BreakGlassRequest::STATUS_APPROVED,
                BreakGlassRequest::STATUS_VIEWED,
            ])
            ->lockForUpdate()
            ->get();

        foreach ($requests as $request) {
            $previousStatus = $request->status;
            $request->forceFill([
                'status' => BreakGlassRequest::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'revocation_reason' => 'complaint_withdrawn',
            ])->save();
            $request->refresh()->load(self::RELATIONS);
            $this->recordAudit(
                AuditAction::BreakGlassRevoked,
                $actor,
                $request,
                beforeChanges: ['status' => $previousStatus],
                afterChanges: ['status' => BreakGlassRequest::STATUS_REVOKED, 'revoked' => true],
            );
            $this->notifyRequestorResolved($request, 'revoked');
        }

        return $requests->count();
    }

    /** @return array<string, mixed> */
    public function reveal(BreakGlassRequest $breakGlassRequest, User $viewer): array
    {
        $result = DB::transaction(function () use ($breakGlassRequest, $viewer): array {
            $viewer = $this->lockedActor($viewer);
            $breakGlassRequest = $this->lockedRequest($breakGlassRequest, includeReporterIdentity: true);

            if ($this->normalizeExpiredLocked($breakGlassRequest)) {
                return ['denied' => 'Emergency access has expired'];
            }

            if (
                ! $viewer->is_active
                || ! $viewer->hasRole('satgas_ppks')
                || ! $viewer->hasPermission('privacy.reveal_anonymous_identity')
                || (int) $viewer->id !== (int) $breakGlassRequest->requestor_id
            ) {
                return ['denied' => 'Only the requesting Satgas may reveal this identity'];
            }

            if (! $breakGlassRequest->isGrantActive()) {
                return ['denied' => 'Emergency access is not currently active'];
            }

            try {
                $case = $this->validCaseForRequest($breakGlassRequest);
                $this->assertSatgasCampus($viewer, $breakGlassRequest->report);
                $this->activeAssignmentOrFail($case, $viewer, lock: true);
            } catch (HttpResponseException) {
                return ['denied' => 'The requester is no longer actively assigned to this case'];
            }

            $reporter = $breakGlassRequest->report?->reporter;

            if (! $reporter instanceof User) {
                return ['denied' => 'Anonymous Reporter identity is unavailable'];
            }

            $viewedAt = now();
            $breakGlassRequest->forceFill([
                'viewed_at' => $breakGlassRequest->viewed_at ?? $viewedAt,
                'view_count' => (int) $breakGlassRequest->view_count + 1,
                'last_viewed_at' => $viewedAt,
            ])->save();
            $breakGlassRequest->refresh()->load(self::RELATIONS);

            $this->recordAudit(
                AuditAction::BreakGlassIdentityViewed,
                $viewer,
                $breakGlassRequest,
                beforeChanges: ['view_count' => (int) $breakGlassRequest->view_count - 1],
                afterChanges: ['view_count' => (int) $breakGlassRequest->view_count],
            );

            return ['identity' => [
                'name' => $reporter->name,
                'nim' => $reporter->nim,
                'email' => $reporter->email,
                'phone_number' => $reporter->phone_number,
                'faculty' => $this->reference($reporter->faculty),
                'study_program' => $this->reference($reporter->studyProgram),
                'university' => $this->reference($reporter->university),
            ]];
        });

        if (isset($result['denied'])) {
            throw $this->forbidden((string) $result['denied']);
        }

        /** @var array<string, mixed> $identity */
        $identity = $result['identity'];

        return $identity;
    }

    private function lockedActor(User $actor): User
    {
        return User::query()
            ->with('role.permissions')
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedRequest(
        BreakGlassRequest $request,
        bool $includeReporterIdentity = false,
    ): BreakGlassRequest {
        $relations = self::RELATIONS;

        if ($includeReporterIdentity) {
            $relations = [
                ...$relations,
                'report.reporter.faculty',
                'report.reporter.studyProgram',
                'report.reporter.university',
            ];
        }

        return BreakGlassRequest::query()
            ->with($relations)
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function validAnonymousReportForCase(CaseRecord $case): Report
    {
        $report = $case->report;

        if (
            ! $report instanceof Report
            || (int) $case->report_id !== (int) $report->id
            || (string) $case->registration_number !== (string) $report->registration_number
            || $report->report_type !== 'anonymous'
            || $report->reporter_id === null
        ) {
            throw $this->unprocessable('Emergency access requires a valid anonymous complaint linked to the case');
        }

        return $report;
    }

    private function validCaseForRequest(BreakGlassRequest $request): CaseRecord
    {
        $request->loadMissing('report.case');
        $report = $request->report;
        $case = $report?->case;

        if (! $report instanceof Report || ! $case instanceof CaseRecord) {
            throw $this->unprocessable('The emergency-access request is not linked to a valid Case');
        }

        $this->validAnonymousReportForCase($case);
        $this->assertNotWithdrawn($report, $case);

        return $case;
    }

    private function assertNotWithdrawn(Report $report, CaseRecord $case): void
    {
        $case->loadMissing('status');

        if ($report->status === ReportStatus::Withdrawn->value
            || $report->withdrawn_at !== null
            || $case->isWithdrawn()
            || $case->withdrawn_at !== null) {
            throw $this->unprocessable('Emergency access is unavailable for a withdrawn complaint');
        }
    }

    private function assertSatgasRequester(User $actor, bool $requireRevealPermission = false): void
    {
        if (
            ! $actor->is_active
            || ! $actor->hasRole('satgas_ppks')
            || ! $actor->hasPermission('privacy.request_break_glass')
            || ($requireRevealPermission && ! $actor->hasPermission('privacy.reveal_anonymous_identity'))
        ) {
            throw $this->forbidden('Only an active assigned Satgas may request emergency access');
        }
    }

    private function assertAdminReviewer(User $actor): void
    {
        if (
            ! $actor->is_active
            || ! $actor->hasRole('admin')
            || $actor->university_id === null
            || ! $actor->hasPermission('privacy.approve_break_glass')
        ) {
            throw $this->forbidden('Only an active campus Admin may review emergency-access requests');
        }
    }

    private function assertAdminCanManage(User $actor, BreakGlassRequest $request): void
    {
        $this->assertAdminReviewer($actor);
        $request->loadMissing('report');

        if (! $request->report instanceof Report || ! $this->campusScope->sameCampus($actor, $request->report)) {
            throw $this->notFound();
        }
    }

    private function assertSatgasCampus(User $actor, Report $report): void
    {
        $universityId = $this->campusScope->reportUniversityId($report);

        if ($actor->university_id === null || $universityId === null || (int) $actor->university_id !== $universityId) {
            throw $this->forbidden('The assigned Satgas and complaint must belong to the same campus');
        }
    }

    private function activeAssignmentOrFail(CaseRecord $case, User $actor, bool $lock): CaseAssignment
    {
        $query = CaseAssignment::query()
            ->where('case_id', $case->id)
            ->where('satgas_id', $actor->id)
            ->where('is_active', true);
        $assignment = $lock ? $query->lockForUpdate()->first() : $query->first();

        if (! $assignment instanceof CaseAssignment) {
            throw $this->forbidden('Only an active assigned Satgas may access anonymous Reporter identity');
        }

        return $assignment;
    }

    private function ensureNoPendingOrActiveRequest(Report $report, User $requestor): void
    {
        $exists = BreakGlassRequest::query()
            ->where('report_id', $report->id)
            ->where('requestor_id', $requestor->id)
            ->whereNull('revoked_at')
            ->whereIn('status', [
                BreakGlassRequest::STATUS_PENDING,
                BreakGlassRequest::STATUS_APPROVED,
                BreakGlassRequest::STATUS_VIEWED,
            ])
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw $this->unprocessable('A pending or active emergency-access request already exists for this case');
        }
    }

    /** @return Builder<BreakGlassRequest> */
    private function campusQuery(User $actor): Builder
    {
        return BreakGlassRequest::query()
            ->with(self::RELATIONS)
            ->whereHas('report.reporter', fn (Builder $reporter): Builder => $reporter
                ->where('university_id', $actor->university_id));
    }

    /** @param LengthAwarePaginator<int, BreakGlassRequest> $requests */
    private function decoratePaginator(LengthAwarePaginator $requests, User $actor): LengthAwarePaginator
    {
        $requests->getCollection()->transform(
            fn (BreakGlassRequest $request): BreakGlassRequest => $this->decorate($request, $actor),
        );

        return $requests;
    }

    private function decorate(BreakGlassRequest $request, User $actor): BreakGlassRequest
    {
        $request->loadMissing(self::RELATIONS);
        $case = $request->report?->case;
        $validCase = $case instanceof CaseRecord
            && (int) $case->report_id === (int) $request->report_id
            && (string) $case->registration_number === (string) $request->report?->registration_number;
        $assigned = $validCase && $case->activeAssignments->contains(
            fn (CaseAssignment $assignment): bool => (int) $assignment->satgas_id === (int) $actor->id,
        );
        $canReveal = $actor->is_active
            && $actor->hasRole('satgas_ppks')
            && $actor->hasPermission('privacy.reveal_anonymous_identity')
            && (int) $actor->id === (int) $request->requestor_id
            && $assigned
            && $request->report?->report_type === 'anonymous'
            && $request->isGrantActive();
        $canRevoke = $actor->is_active
            && $actor->hasRole('admin')
            && $actor->hasPermission('privacy.approve_break_glass')
            && $request->report instanceof Report
            && $this->campusScope->sameCampus($actor, $request->report)
            && $request->isGrantActive();

        $request->setAttribute('effective_status', $request->effectiveStatus());
        $request->setAttribute('can_reveal', $canReveal);
        $request->setAttribute('can_revoke', $canRevoke);

        return $request;
    }

    private function normalizeExpiredPair(int $reportId, int $requestorId): void
    {
        BreakGlassRequest::query()
            ->where('report_id', $reportId)
            ->where('requestor_id', $requestorId)
            ->whereIn('status', [BreakGlassRequest::STATUS_APPROVED, BreakGlassRequest::STATUS_VIEWED])
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get()
            ->each(fn (BreakGlassRequest $request) => $this->normalizeExpiredLocked($request));
    }

    private function normalizeExpiredForCampus(User $actor): void
    {
        $ids = BreakGlassRequest::query()
            ->whereIn('status', [BreakGlassRequest::STATUS_APPROVED, BreakGlassRequest::STATUS_VIEWED])
            ->where('expires_at', '<=', now())
            ->whereHas('report.reporter', fn (Builder $reporter): Builder => $reporter
                ->where('university_id', $actor->university_id))
            ->limit(100)
            ->pluck('id');

        $ids->each(fn (int $id) => $this->normalizeExpiredById($id));
    }

    private function normalizeExpiredForRequester(User $actor): void
    {
        BreakGlassRequest::query()
            ->where('requestor_id', $actor->id)
            ->whereIn('status', [BreakGlassRequest::STATUS_APPROVED, BreakGlassRequest::STATUS_VIEWED])
            ->where('expires_at', '<=', now())
            ->limit(100)
            ->pluck('id')
            ->each(fn (int $id) => $this->normalizeExpiredById($id));
    }

    private function normalizeExpiredById(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $request = BreakGlassRequest::query()
                ->with(self::RELATIONS)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if ($request instanceof BreakGlassRequest) {
                $this->normalizeExpiredLocked($request);
            }
        });
    }

    private function normalizeExpiredLocked(BreakGlassRequest $request): bool
    {
        if (
            ! in_array($request->status, [BreakGlassRequest::STATUS_APPROVED, BreakGlassRequest::STATUS_VIEWED], true)
            || $request->revoked_at !== null
            || $request->expires_at === null
            || now()->lt($request->expires_at)
        ) {
            return false;
        }

        $previousStatus = $request->status;
        $request->forceFill(['status' => BreakGlassRequest::STATUS_EXPIRED])->save();
        $request->refresh()->loadMissing(self::RELATIONS);
        $this->recordAudit(
            AuditAction::BreakGlassExpired,
            null,
            $request,
            beforeChanges: ['status' => $previousStatus],
            afterChanges: ['status' => BreakGlassRequest::STATUS_EXPIRED],
        );

        return true;
    }

    private function recordAudit(
        AuditAction $action,
        ?User $actor,
        BreakGlassRequest $subject,
        array $beforeChanges = [],
        array $afterChanges = [],
    ): void {
        $subject->loadMissing('report.case');
        $metadata = [
            'registration_number' => $subject->report?->registration_number,
            'case_number' => $subject->report?->case?->case_number,
            'reason_category' => $subject->reason_category,
            'duration_code' => 'duration_'.(int) $subject->requested_duration_minutes.'_minutes',
            'status' => $subject->effectiveStatus(),
            'expires_at' => $subject->expires_at?->toJSON(),
            'view_count' => (int) $subject->view_count,
            'result' => 'succeeded',
        ];

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

    private function notifyCampusAdminsOfRequest(BreakGlassRequest $request): void
    {
        $request->loadMissing('report.reporter');
        $universityId = $request->report?->reporter?->university_id;

        if ($universityId === null) {
            return;
        }

        User::query()
            ->where('is_active', true)
            ->where('university_id', $universityId)
            ->whereHas('role', fn (Builder $query): Builder => $query->where('code', 'admin'))
            ->get()
            ->each(fn (User $admin) => $admin->notify(new WorkflowDatabaseNotification([
                'notification_type_code' => 'break_glass_request',
                'event' => 'break_glass_request',
                'title' => 'Permintaan Akses Darurat Baru',
                'body' => 'Permintaan akses darurat baru membutuhkan peninjauan Admin Kampus.',
                'break_glass_request_id' => $request->id,
                'registration_number' => $request->report?->registration_number,
            ])));
    }

    private function notifyRequestorResolved(BreakGlassRequest $request, string $result): void
    {
        $messages = [
            'approved' => 'Permintaan akses darurat Anda telah disetujui hingga waktu kedaluwarsa yang tercantum.',
            'denied' => 'Permintaan akses darurat Anda ditolak.',
            'revoked' => 'Akses darurat Anda telah dicabut oleh Admin Kampus.',
        ];

        $request->requestor?->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => 'break_glass_'.$result,
            'event' => 'break_glass_'.$result,
            'title' => 'Pembaruan Akses Darurat',
            'body' => $messages[$result],
            'break_glass_request_id' => $request->id,
            'registration_number' => $request->report?->registration_number,
        ]));
    }

    private function notifyReporterApproved(BreakGlassRequest $request): void
    {
        $request->loadMissing('report.reporter');
        $report = $request->report;

        if (! $report?->reporter) {
            return;
        }

        $report->reporter->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => 'privacy_notice',
            'event' => 'break_glass_approved',
            'title' => 'Pemberitahuan Privasi',
            'body' => 'Permintaan akses darurat terhadap identitas pada pengaduan '.$report->registration_number.' telah disetujui sesuai kebijakan privasi SILAPPKASAL.',
            'registration_number' => $report->registration_number,
        ]));
    }

    /** @return array{code: string, name: string}|null */
    private function reference(?object $model): ?array
    {
        return $model === null ? null : [
            'code' => (string) $model->code,
            'name' => (string) $model->name,
        ];
    }

    private function forbidden(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 403));
    }

    private function notFound(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Emergency-access request not found',
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
}
