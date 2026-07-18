<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Exceptions\AuditExportLimitExceeded;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuditSensitiveAuthorizationDenials;
use App\Http\Requests\AuditExportRequest;
use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Requests\AuditOversightRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditExportService;
use App\Services\AuditLogQuery;
use App\Services\AuditLogService;
use App\Services\AuditLogVisibilityScope;
use App\Services\OversightProjection;
use App\Support\ApiErrorCode;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Throwable;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogVisibilityScope $visibility,
        private readonly AuditLogQuery $auditQuery,
        private readonly OversightProjection $oversightProjection,
        private readonly AuditExportService $exportService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        [$from, $to] = $request->resolvedAuditDateRange();
        $filters = [
            ...$request->safe()->except(['page', 'per_page', 'cutoff', 'date_from', 'date_to']),
            'date_from' => $from,
            'date_to' => $to,
        ];
        $cutoff = $request->cutoff();
        $perPage = (int) $request->integer('per_page', 25);
        $logs = $this->auditQuery->build($request->user(), $filters, $cutoff)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => AuditLogResource::collection($logs)->response()->getData(true),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'cutoff' => $cutoff->toJSON(),
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
        ]);
    }

    public function summary(AuditOversightRequest $request): JsonResponse
    {
        Gate::authorize('oversight', AuditLog::class);
        $cutoff = $request->cutoff();

        return response()->json([
            'success' => true,
            'message' => 'Operational oversight summary retrieved successfully',
            'data' => $this->oversightProjection->summary(
                $request->user(),
                $cutoff,
                $request->validated('urgency'),
            ),
        ]);
    }

    public function oversight(AuditOversightRequest $request): JsonResponse
    {
        Gate::authorize('oversight', AuditLog::class);
        $result = $this->oversightProjection->paginate(
            user: $request->user(),
            cutoff: $request->cutoff(),
            queue: $request->validated('queue'),
            urgency: $request->validated('urgency'),
            page: max(1, (int) $request->integer('page', 1)),
            perPage: (int) $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'Operational oversight items retrieved successfully',
            'data' => $result['data'],
            'meta' => [
                ...$result['meta'],
                'cutoff' => $result['cutoff'],
            ],
        ]);
    }

    public function export(AuditExportRequest $request): Response|JsonResponse
    {
        $this->authorizeExport($request);
        [$from, $to] = $request->resolvedAuditDateRange();
        $cutoff = $request->cutoff();
        $filters = [
            ...$request->safe()->except(['date_from', 'date_to', 'cutoff']),
            'date_from' => $from,
            'date_to' => $to,
        ];

        try {
            $export = $this->exportService->create($request->user(), $filters, $cutoff);
            $this->recordExportResult(
                $request->user(),
                AuditResult::Succeeded,
                $from,
                $to,
                rowCount: $export['row_count'],
            );

            return response($export['content'], 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
                'Cache-Control' => 'no-store, private',
            ]);
        } catch (AuditExportLimitExceeded $exception) {
            $this->recordExportResult(
                $request->user(),
                AuditResult::Failed,
                $from,
                $to,
                rowCount: $exception->rowCount,
                failureCode: ApiErrorCode::AuditExportTooManyRows,
            );

            return response()->json([
                'success' => false,
                'message' => __('api.errors.'.ApiErrorCode::AuditExportTooManyRows),
                'error_code' => ApiErrorCode::AuditExportTooManyRows,
                'errors' => null,
            ], 422);
        } catch (Throwable $exception) {
            $this->recordExportResult(
                $request->user(),
                AuditResult::Failed,
                $from,
                $to,
                failureCode: 'audit_export.failed',
            );

            throw $exception;
        }
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $auditLog = $this->visibility->query(request()->user())
            ->whereKey($auditLog->getKey())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Audit log retrieved successfully',
            'data' => new AuditLogResource($auditLog),
        ]);
    }

    private function authorizeExport(Request $request): void
    {
        $request->attributes->set(AuditSensitiveAuthorizationDenials::HANDLED_ATTRIBUTE, true);

        if (Gate::forUser($request->user())->allows('export', AuditLog::class)) {
            $request->attributes->remove(AuditSensitiveAuthorizationDenials::HANDLED_ATTRIBUTE);

            return;
        }

        try {
            $this->auditLogService->record(
                action: AuditAction::AuditExport,
                category: AuditCategory::System,
                severity: AuditSeverity::Warning,
                actor: $request->user(),
                metadata: [
                    'format' => 'csv',
                    'failure_code' => 'authorization_denied',
                ],
                result: AuditResult::Denied,
            );
        } catch (Throwable $exception) {
            $request->attributes->remove(AuditSensitiveAuthorizationDenials::HANDLED_ATTRIBUTE);
            report($exception);
        }

        throw new AuthorizationException();
    }

    private function recordExportResult(
        User $actor,
        AuditResult $result,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $rowCount = null,
        ?string $failureCode = null,
    ): void {
        $this->auditLogService->record(
            action: AuditAction::AuditExport,
            category: AuditCategory::System,
            severity: $result === AuditResult::Succeeded ? AuditSeverity::Info : AuditSeverity::Warning,
            actor: $actor,
            metadata: [
                'row_count' => $rowCount,
                'format' => 'csv',
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'failure_code' => $failureCode,
            ],
            result: $result,
        );
    }
}
