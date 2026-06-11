<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Resources\DashboardAnalyticsResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function summary(DashboardFilterRequest $request): JsonResponse
    {
        Gate::authorize('viewDashboard');

        return $this->respond(
            'Dashboard summary retrieved successfully',
            $this->dashboardService->summary($request->user(), $request->dashboardFilters())
        );
    }

    public function reports(DashboardFilterRequest $request): JsonResponse
    {
        Gate::authorize('viewDashboard');

        return $this->respond(
            'Report analytics retrieved successfully',
            $this->dashboardService->reports($request->user(), $request->dashboardFilters())
        );
    }

    public function cases(DashboardFilterRequest $request): JsonResponse
    {
        Gate::authorize('viewDashboard');

        return $this->respond(
            'Case analytics retrieved successfully',
            $this->dashboardService->cases($request->user(), $request->dashboardFilters())
        );
    }

    public function workflow(DashboardFilterRequest $request): JsonResponse
    {
        Gate::authorize('viewDashboard');

        return $this->respond(
            'Workflow analytics retrieved successfully',
            $this->dashboardService->workflow($request->user(), $request->dashboardFilters())
        );
    }

    public function evidence(DashboardFilterRequest $request): JsonResponse
    {
        Gate::authorize('viewDashboard');

        return $this->respond(
            'Evidence metadata analytics retrieved successfully',
            $this->dashboardService->evidence($request->user(), $request->dashboardFilters())
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(string $message, array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new DashboardAnalyticsResource($data),
        ]);
    }
}
