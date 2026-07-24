<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CaseAssessmentRequest;
use App\Http\Requests\CaseAssignRequest;
use App\Http\Requests\CaseIndexRequest;
use App\Http\Requests\CaseSelfAssignRequest;
use App\Http\Requests\CaseStatusUpdateRequest;
use App\Http\Resources\CaseResource;
use App\Models\CaseRecord;
use App\Services\CaseClosureService;
use App\Services\CaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly CaseClosureService $caseClosureService,
    ) {}

    public function index(CaseIndexRequest $request): JsonResponse
    {
        $cases = $this->caseService->listForUser($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cases retrieved successfully',
            'data' => CaseResource::collection($cases->items()),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'last_page' => $cases->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('view', $case);

        return response()->json([
            'success' => true,
            'message' => 'Case retrieved successfully',
            'data' => new CaseResource($this->caseService->loadForUser($case, $request->user())),
        ]);
    }

    public function assign(CaseAssignRequest $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('assign', $case);

        $case = $this->caseService->assignSatgas($case, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Case assignments updated successfully',
            'data' => new CaseResource($case),
        ]);
    }

    public function selfAssign(CaseSelfAssignRequest $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('selfAssign', $case);

        $case = $this->caseService->selfAssign($case, $request->user(), $request->validated('lock_version'));

        return response()->json([
            'success' => true,
            'message' => 'Case assignment claimed successfully',
            'data' => new CaseResource($case),
        ]);
    }

    public function updateStatus(CaseStatusUpdateRequest $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('updateStatus', $case);

        $case = $this->caseService->updateStatus($case, $request->user(), $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Case status updated successfully',
            'data' => new CaseResource($case),
        ]);
    }

    public function updateAssessment(CaseAssessmentRequest $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('recordAssessment', $case);

        $case = $this->caseService->recordAssessment($case, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Case assessment recorded successfully',
            'data' => new CaseResource($case),
        ]);
    }

    public function close(Request $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('finalizeClosure', $case);
        $case = $this->caseClosureService->close($case, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Case closed successfully',
            'data' => new CaseResource($case),
        ]);
    }
}
