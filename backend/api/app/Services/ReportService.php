<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CaseCampusScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CaseCampusScope $campusScope,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data): Report
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.unauthenticated'),
                'error_code' => ApiErrorCode::Unauthenticated,
                'errors' => null,
            ], 401));
        }

        $user->loadMissing('role.permissions');

        if (! $user->is_active) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.account_inactive'),
                'error_code' => ApiErrorCode::AccountInactive,
                'errors' => null,
            ], 403));
        }

        if (! $user->hasPermission('reports.create')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.forbidden'),
                'error_code' => ApiErrorCode::Forbidden,
                'errors' => null,
            ], 403));
        }

        return $this->createReportWithUniqueIdentifiers($data, $user);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Report>
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $canReadAll = $this->canReadAllReports($user);
        $canReadOwn = $user->hasPermission('reports.read.own');

        if (! $canReadAll && ! $canReadOwn) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('api.errors.forbidden'),
                'error_code' => ApiErrorCode::Forbidden,
                'errors' => null,
            ], 403));
        }

        $query = Report::query()
            ->with(['category', 'case.priorityLevel'])
            ->latest('submitted_at')
            ->latest('id');

        if (! $canReadAll) {
            $query
                ->whereNotNull('reporter_id')
                ->where('reporter_id', $user->id)
                ->where('report_type', '!=', 'anonymous');
        } elseif ($user->hasRole('admin')) {
            $this->campusScope->scopeReports($query, $user);
        }

        foreach (['status', 'category_code', 'report_type'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['satgas_id'])) {
            $query->whereHas(
                'case.activeAssignments',
                fn (Builder $assignment): Builder => $assignment
                    ->where('satgas_id', $filters['satgas_id'])
                    ->where('is_active', true),
            );
        }

        if (($filters['assignment_status'] ?? null) === 'unassigned') {
            $query->whereDoesntHave('case.activeAssignments');
        }

        if (! empty($filters['university_id'])) {
            $query->whereHas(
                'reporter',
                fn (Builder $reporter): Builder => $reporter
                    ->where('university_id', $filters['university_id']),
            );
        }

        $reports = $query->paginate((int) ($filters['per_page'] ?? 15));

        $reports->getCollection()
            ->each(fn (Report $report): Report => $report->setAttribute('include_case_context', false))
            ->filter(fn (Report $report): bool => $report->report_type !== 'anonymous')
            ->load('reporter');

        return $reports;
    }

    private function canReadAllReports(User $user): bool
    {
        return $user->hasPermission('reports.read.all')
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }

    public function canReadSubmittedDetails(User $user, Report $report): bool
    {
        return $this->campusScope->canSensitiveOversight($user)
            || ($user->hasRole('admin')
                && $user->hasPermission('reports.read.all')
                && $this->campusScope->sameCampus($user, $report));
    }

    public function findByTrackingCode(string $trackingCode): ?Report
    {
        $normalized = $this->normalizeTrackingCode($trackingCode);

        if ($normalized === null) {
            return null;
        }

        return Report::query()
            ->with(['category', 'case.status'])
            ->where('tracking_code', $normalized)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createReportWithUniqueIdentifiers(array $data, User $user): Report
    {
        $attempts = 0;

        beginning:
        $attempts++;

        try {
            return DB::transaction(function () use ($data, $user): Report {
                $submittedAt = now();
                $reportType = (string) $data['report_type'];

                $report = Report::query()->create([
                    'reporter_id' => $user->id,
                    'registration_number' => $this->generateRegistrationNumber($submittedAt),
                    'tracking_code' => $reportType === 'anonymous' ? $this->generateTrackingCode() : null,
                    'report_type' => $reportType,
                    'category_code' => $data['category_code'],
                    'chronology' => $data['chronology'],
                    'incident_date' => $data['incident_date'],
                    'incident_time' => $data['incident_time'] ?? null,
                    'incident_location' => $data['incident_location'],
                    'location_type' => $data['location_type'] ?? null,
                    'respondent_name' => $data['respondent_name'] ?? null,
                    'respondent_campus_status' => $data['respondent_campus_status'] ?? null,
                    'respondent_relation' => $data['respondent_relation'] ?? null,
                    'respondent_details' => $data['respondent_details'] ?? null,
                    'witness_info' => $data['witness_info'] ?? null,
                    'reporter_phone_encrypted' => $reportType === 'confidential'
                        ? ($data['reporter_phone'] ?? null)
                        : null,
                    'status' => ReportStatus::Submitted->value,
                    'priority' => null,
                    'submitted_at' => $submittedAt,
                ]);

                $this->auditLogService->record(
                    action: AuditAction::ReportCreated,
                    category: AuditCategory::Report,
                    severity: AuditSeverity::Info,
                    actor: $user,
                    subject: $report,
                    metadata: [
                        'registration_number' => $report->registration_number,
                        'report_type' => $report->report_type,
                        'category_code' => $report->category_code,
                        'status' => $report->status,
                    ],
                    afterChanges: ['status' => $report->status],
                );

                return $report->load('category');
            });
        } catch (QueryException $exception) {
            if ($attempts < 5 && $this->isUniqueConstraintFailure($exception)) {
                goto beginning;
            }

            throw $exception;
        }
    }

    private function generateRegistrationNumber(\DateTimeInterface $submittedAt): string
    {
        $date = $submittedAt->format('Y-md');
        $prefix = "SLP-{$date}-";
        $nextNumber = Report::query()
            ->where('registration_number', 'like', "{$prefix}%")
            ->count() + 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function generateTrackingCode(): string
    {
        do {
            $characters = strtoupper(Str::random(16));
            $trackingCode = implode('-', str_split($characters, 4));
        } while (Report::query()->where('tracking_code', $trackingCode)->exists());

        return $trackingCode;
    }

    private function normalizeTrackingCode(string $trackingCode): ?string
    {
        $characters = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $trackingCode) ?? '');

        if (strlen($characters) < 16) {
            return null;
        }

        return implode('-', str_split(substr($characters, 0, 16), 4));
    }

    private function isUniqueConstraintFailure(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
