<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDecisionRequest;
use App\Http\Requests\UpdateDecisionRequest;
use App\Http\Requests\UpdateDecisionStatusRequest;
use App\Http\Resources\DecisionResource;
use App\Models\Decision;
use App\Models\Recommendation;
use App\Services\DecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DecisionController extends Controller
{
    public function __construct(private readonly DecisionService $decisionService)
    {
    }

    public function storeForRecommendation(StoreDecisionRequest $request, Recommendation $recommendation): JsonResponse
    {
        $decision = $this->decisionService->createForRecommendation($recommendation, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Decision created successfully',
            'data' => new DecisionResource($decision),
        ], 201);
    }

    public function indexForRecommendation(Request $request, Recommendation $recommendation): JsonResponse
    {
        $decisions = $this->decisionService->listForRecommendation($recommendation, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Decisions retrieved successfully',
            'data' => DecisionResource::collection($decisions),
        ]);
    }

    public function show(Request $request, Decision $decision): JsonResponse
    {
        Gate::authorize('view', $decision);

        return response()->json([
            'success' => true,
            'message' => 'Decision retrieved successfully',
            'data' => new DecisionResource($this->decisionService->loadForUser($decision, $request->user())),
        ]);
    }

    public function statusOptions(Request $request, Decision $decision): JsonResponse
    {
        Gate::authorize('view', $decision);

        return response()->json([
            'success' => true,
            'message' => 'Decision status options retrieved successfully',
            'data' => $this->decisionService->statusOptions($decision, $request->user()),
        ]);
    }

    public function update(UpdateDecisionRequest $request, Decision $decision): JsonResponse
    {
        Gate::authorize('update', $decision);

        $decision = $this->decisionService->update($decision, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Decision updated successfully',
            'data' => new DecisionResource($decision),
        ]);
    }

    public function updateStatus(UpdateDecisionStatusRequest $request, Decision $decision): JsonResponse
    {
        Gate::authorize('updateStatus', $decision);

        $decision = $this->decisionService->updateStatus($decision, $request->user(), $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Decision status updated successfully',
            'data' => new DecisionResource($decision),
        ]);
    }
}
