<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationIndexRequest;
use App\Http\Requests\PortalReportIndexRequest;
use App\Http\Resources\PortalNotificationResource;
use App\Http\Resources\PortalReportDetailResource;
use App\Http\Resources\PortalReportHandlingProgressResource;
use App\Http\Resources\PortalReportResource;
use App\Http\Resources\PortalReportTimelineResource;
use App\Http\Resources\PortalSummaryResource;
use App\Services\ReporterPortalService;
use App\Support\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PortalController extends Controller
{
    public function __construct(private readonly ReporterPortalService $portalService) {}

    public function summary(NotificationIndexRequest $request): JsonResponse
    {
        Gate::authorize('accessReporterPortal');

        return response()->json([
            'success' => true,
            'message' => 'Portal summary retrieved successfully',
            'data' => new PortalSummaryResource($this->portalService->summary($request->user())),
        ]);
    }

    public function reports(PortalReportIndexRequest $request): JsonResponse
    {
        Gate::authorize('accessReporterPortal');

        $reports = $this->portalService->reports(
            $request->user(),
            (int) $request->validated('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'message' => 'Portal complaints retrieved successfully',
            'data' => PortalReportResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    public function report(NotificationIndexRequest $request, string $registrationNumber): JsonResponse
    {
        Gate::authorize('accessReporterPortal');

        $report = $this->portalService->findReport($request->user(), $registrationNumber);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.portal_report_not_found'),
                'error_code' => ApiErrorCode::PortalReportNotFound,
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Portal complaint retrieved successfully',
            'data' => new PortalReportDetailResource($report),
        ]);
    }

    public function reportHandlingProgress(NotificationIndexRequest $request, string $registrationNumber): JsonResponse
    {
        Gate::authorize('accessReporterPortal');

        $progress = $this->portalService->handlingProgress($request->user(), $registrationNumber);

        if (! $progress) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.portal_report_not_found'),
                'error_code' => ApiErrorCode::PortalReportNotFound,
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Portal complaint handling progress retrieved successfully',
            'data' => new PortalReportHandlingProgressResource($progress),
        ]);
    }

    public function reportTimeline(NotificationIndexRequest $request, string $registrationNumber): JsonResponse
    {
        Gate::authorize('accessReporterPortal');

        $timeline = $this->portalService->reportTimeline($request->user(), $registrationNumber);

        if (! $timeline) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.portal_report_not_found'),
                'error_code' => ApiErrorCode::PortalReportNotFound,
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Portal complaint timeline retrieved successfully',
            'data' => new PortalReportTimelineResource($timeline),
        ]);
    }

    public function notifications(NotificationIndexRequest $request): JsonResponse
    {
        Gate::authorize('accessReporterPortal');

        $query = $request->user()
            ->notifications()
            ->latest();

        $query->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'));

        $notifications = $query->paginate((int) $request->validated('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Portal notifications retrieved successfully',
            'data' => PortalNotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }
}
