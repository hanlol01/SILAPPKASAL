<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseFinalSummaryRequest;
use App\Http\Requests\UpdateCaseFinalSummaryRequest;
use App\Http\Resources\CaseFinalSummaryResource;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Services\CaseFinalSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CaseFinalSummaryController extends Controller
{
    public function __construct(private readonly CaseFinalSummaryService $service)
    {
    }

    public function show(Request $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('view', $case);
        $summary = $this->service->findForCase($case);

        if ($summary) {
            Gate::authorize('view', $summary);
        }

        return response()->json([
            'success' => true,
            'message' => 'Case final summary retrieved successfully',
            'data' => [
                'summary' => $summary ? new CaseFinalSummaryResource($summary) : null,
                'outcome_options' => $this->service->outcomeOptions($case),
            ],
        ]);
    }

    public function store(StoreCaseFinalSummaryRequest $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('create', [CaseFinalSummary::class, $case]);
        $summary = $this->service->create($case, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Case final summary created successfully',
            'data' => new CaseFinalSummaryResource($summary),
        ], 201);
    }

    public function update(UpdateCaseFinalSummaryRequest $request, CaseRecord $case): JsonResponse
    {
        $summary = $this->service->findForCase($case) ?? abort(404);
        Gate::authorize('update', $summary);
        $summary = $this->service->update($summary, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Case final summary updated successfully',
            'data' => new CaseFinalSummaryResource($summary),
        ]);
    }

    public function publish(Request $request, CaseRecord $case): JsonResponse
    {
        $summary = $this->service->findForCase($case) ?? abort(404);
        Gate::authorize('publish', $summary);
        $summary = $this->service->publish($summary, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Case final summary published successfully',
            'data' => new CaseFinalSummaryResource($summary),
        ]);
    }
}
