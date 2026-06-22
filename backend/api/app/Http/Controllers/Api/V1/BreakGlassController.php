<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BreakGlassDenyRequest;
use App\Http\Requests\BreakGlassStoreRequest;
use App\Http\Resources\BreakGlassRequestResource;
use App\Models\BreakGlassRequest;
use App\Services\BreakGlassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BreakGlassController extends Controller
{
    public function __construct(private readonly BreakGlassService $breakGlassService)
    {
    }

    public function request(BreakGlassStoreRequest $request): JsonResponse
    {
        Gate::authorize('request', BreakGlassRequest::class);

        return response()->json([
            'success' => true,
            'message' => 'Break-glass request created successfully',
            'data' => new BreakGlassRequestResource($this->breakGlassService->request(
                $request->validated(),
                $request->user()
            )),
        ], 201);
    }

    public function pending(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BreakGlassRequest::class);

        $requests = $this->breakGlassService->pending((int) $request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Pending break-glass requests retrieved successfully',
            'data' => BreakGlassRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BreakGlassRequest::class);

        $requests = $this->breakGlassService->history((int) $request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Break-glass history retrieved successfully',
            'data' => BreakGlassRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function show(BreakGlassRequest $breakGlassRequest): JsonResponse
    {
        Gate::authorize('view', $breakGlassRequest);

        return response()->json([
            'success' => true,
            'message' => 'Break-glass request retrieved successfully',
            'data' => new BreakGlassRequestResource($breakGlassRequest->load(['requestor.role', 'approver.role', 'report'])),
        ]);
    }

    public function approve(BreakGlassRequest $breakGlassRequest, Request $request): JsonResponse
    {
        Gate::authorize('approve', $breakGlassRequest);

        return response()->json([
            'success' => true,
            'message' => 'Break-glass request approved successfully',
            'data' => new BreakGlassRequestResource($this->breakGlassService->approve($breakGlassRequest, $request->user())),
        ]);
    }

    public function deny(BreakGlassDenyRequest $request, BreakGlassRequest $breakGlassRequest): JsonResponse
    {
        Gate::authorize('deny', $breakGlassRequest);

        return response()->json([
            'success' => true,
            'message' => 'Break-glass request denied successfully',
            'data' => new BreakGlassRequestResource($this->breakGlassService->deny(
                $breakGlassRequest,
                $request->user(),
                (string) $request->validated('denial_reason')
            )),
        ]);
    }

    public function reveal(BreakGlassRequest $breakGlassRequest, Request $request): JsonResponse
    {
        Gate::authorize('reveal', $breakGlassRequest);

        $response = response()->json([
            'success' => true,
            'message' => 'Anonymous reporter identity revealed through approved break-glass access',
            'data' => $this->breakGlassService->reveal($breakGlassRequest, $request->user()),
        ]);

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
