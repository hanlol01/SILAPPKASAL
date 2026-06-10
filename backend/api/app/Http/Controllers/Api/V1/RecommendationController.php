<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecommendationRequest;
use App\Http\Requests\UpdateRecommendationRequest;
use App\Http\Requests\UpdateRecommendationStatusRequest;
use App\Http\Resources\RecommendationDetailResource;
use App\Http\Resources\RecommendationMetadataResource;
use App\Models\CaseRecord;
use App\Models\Recommendation;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RecommendationController extends Controller
{
    public function __construct(private readonly RecommendationService $recommendationService)
    {
    }

    public function storeForCase(StoreRecommendationRequest $request, CaseRecord $case): JsonResponse
    {
        $recommendation = $this->recommendationService->createForCase($case, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recommendation created successfully',
            'data' => new RecommendationDetailResource($recommendation),
        ], 201);
    }

    public function indexForCase(Request $request, CaseRecord $case): JsonResponse
    {
        $recommendations = $this->recommendationService->listForCase($case, $request->user());
        $resource = $recommendations->map(function (Recommendation $recommendation) use ($request) {
            $recommendation = $this->recommendationService->loadForUser($recommendation, $request->user());

            return $this->recommendationService->canReadSensitive($recommendation, $request->user())
                ? new RecommendationDetailResource($recommendation)
                : new RecommendationMetadataResource($recommendation);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Recommendations retrieved successfully',
            'data' => $resource,
        ]);
    }

    public function show(Request $request, Recommendation $recommendation): JsonResponse
    {
        Gate::authorize('view', $recommendation);

        $recommendation = $this->recommendationService->loadForUser($recommendation, $request->user());
        $resource = $this->recommendationService->canReadSensitive($recommendation, $request->user())
            ? new RecommendationDetailResource($recommendation)
            : new RecommendationMetadataResource($recommendation);

        return response()->json([
            'success' => true,
            'message' => 'Recommendation retrieved successfully',
            'data' => $resource,
        ]);
    }

    public function update(UpdateRecommendationRequest $request, Recommendation $recommendation): JsonResponse
    {
        Gate::authorize('update', $recommendation);

        $recommendation = $this->recommendationService->update($recommendation, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recommendation updated successfully',
            'data' => new RecommendationDetailResource($recommendation),
        ]);
    }

    public function updateStatus(UpdateRecommendationStatusRequest $request, Recommendation $recommendation): JsonResponse
    {
        Gate::authorize('updateStatus', $recommendation);

        $recommendation = $this->recommendationService->updateStatus($recommendation, $request->user(), $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Recommendation status updated successfully',
            'data' => new RecommendationDetailResource($recommendation),
        ]);
    }
}
