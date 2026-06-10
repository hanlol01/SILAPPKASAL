<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class ReportService
{
    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data, ?string $bearerToken = null): Report
    {
        $reportType = (string) $data['report_type'];
        $user = $reportType === 'anonymous' ? null : $this->resolveUserFromBearerToken($bearerToken);

        if ($reportType !== 'anonymous' && ! $user) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => null,
            ], 401));
        }

        if ($user && ! $user->is_active) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi admin.',
                'errors' => null,
            ], 403));
        }

        if ($user && ! $user->hasPermission('reports.create')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
                'errors' => null,
            ], 403));
        }

        return $this->createReportWithUniqueIdentifiers($data, $user);
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Report>
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $canReadAll = $this->canReadAllReports($user);
        $canReadOwn = $user->hasPermission('reports.read.own');

        if (! $canReadAll && ! $canReadOwn) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
                'errors' => null,
            ], 403));
        }

        $query = Report::query()
            ->with(['category', 'priorityLevel'])
            ->latest('submitted_at');

        if (! $canReadAll) {
            $query
                ->whereNotNull('reporter_id')
                ->where('reporter_id', $user->id)
                ->where('report_type', '!=', 'anonymous');
        }

        foreach (['status', 'category_code', 'report_type'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    private function canReadAllReports(User $user): bool
    {
        return $user->hasPermission('reports.read.all')
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }

    private function resolveUserFromBearerToken(?string $bearerToken): ?User
    {
        if (! $bearerToken) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);
        $tokenable = $accessToken?->tokenable;

        if (! $tokenable instanceof User) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        return $tokenable->load('role.permissions');
    }

    public function findByTrackingCode(string $trackingCode): ?Report
    {
        $normalized = $this->normalizeTrackingCode($trackingCode);

        if ($normalized === null) {
            return null;
        }

        return Report::query()
            ->with('category')
            ->where('tracking_code', $normalized)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createReportWithUniqueIdentifiers(array $data, ?User $user): Report
    {
        $attempts = 0;

        beginning:
        $attempts++;

        try {
            return DB::transaction(function () use ($data, $user): Report {
                $submittedAt = now();
                $reportType = (string) $data['report_type'];

                $report = Report::query()->create([
                    'reporter_id' => $reportType === 'anonymous' ? null : $user?->id,
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
