<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvestigationActivityRequest;
use App\Http\Requests\StoreInvestigationRequest;
use App\Http\Requests\UpdateInvestigationStatusRequest;
use App\Http\Resources\InvestigationActivityResource;
use App\Http\Resources\InvestigationDetailResource;
use App\Http\Resources\InvestigationMetadataResource;
use App\Models\CaseRecord;
use App\Models\Investigation;
use App\Services\InvestigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvestigationController extends Controller
{
    public function __construct(private readonly InvestigationService $investigationService)
    {
    }

    public function storeForCase(StoreInvestigationRequest $request, CaseRecord $case): JsonResponse
    {
        $investigation = $this->investigationService->createForCase($case, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Investigation created successfully',
            'data' => new InvestigationDetailResource($investigation),
        ], 201);
    }

    public function indexForCase(Request $request, CaseRecord $case): JsonResponse
    {
        $investigations = $this->investigationService->listForCase($case, $request->user());
        $resource = $investigations->map(function (Investigation $investigation) use ($request) {
            $investigation = $this->investigationService->loadForUser($investigation, $request->user());

            return $this->investigationService->canReadSensitive($investigation, $request->user())
                ? new InvestigationDetailResource($investigation)
                : new InvestigationMetadataResource($investigation);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Investigations retrieved successfully',
            'data' => $resource,
        ]);
    }

    public function show(Request $request, Investigation $investigation): JsonResponse
    {
        Gate::authorize('view', $investigation);

        $investigation = $this->investigationService->loadForUser($investigation, $request->user());
        $resource = $this->investigationService->canReadSensitive($investigation, $request->user())
            ? new InvestigationDetailResource($investigation)
            : new InvestigationMetadataResource($investigation);

        return response()->json([
            'success' => true,
            'message' => 'Investigation retrieved successfully',
            'data' => $resource,
        ]);
    }

    public function statusOptions(Investigation $investigation): JsonResponse
    {
        Gate::authorize('view', $investigation);

        return response()->json([
            'success' => true,
            'message' => 'Investigation status options retrieved successfully',
            'data' => $this->investigationService->statusOptions($investigation),
        ]);
    }

    public function updateStatus(UpdateInvestigationStatusRequest $request, Investigation $investigation): JsonResponse
    {
        Gate::authorize('updateStatus', $investigation);

        $investigation = $this->investigationService->updateStatus($investigation, $request->user(), $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Investigation status updated successfully',
            'data' => new InvestigationDetailResource($investigation),
        ]);
    }

    public function storeActivity(StoreInvestigationActivityRequest $request, Investigation $investigation): JsonResponse
    {
        Gate::authorize('addActivity', $investigation);

        $activity = $this->investigationService->addActivity($investigation, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Investigation activity created successfully',
            'data' => new InvestigationActivityResource($activity),
        ], 201);
    }
}
