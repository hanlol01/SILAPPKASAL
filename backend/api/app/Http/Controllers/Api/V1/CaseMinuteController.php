<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCaseMinuteRevisionRequest;
use App\Http\Requests\FinalizeCaseMinuteRequest;
use App\Http\Requests\StoreCaseMinuteRequest;
use App\Http\Requests\UpdateCaseMinuteRequest;
use App\Http\Resources\CaseMinuteInternalResource;
use App\Http\Resources\CaseMinuteMetadataResource;
use App\Models\CaseMinute;
use App\Models\CaseRecord;
use App\Models\User;
use App\Services\CaseMinuteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class CaseMinuteController extends Controller
{
    public function __construct(private readonly CaseMinuteService $service) {}

    public function index(Request $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('view', $case);
        Gate::authorize('viewAny', CaseMinute::class);

        $actor = $request->user();
        $minutes = $this->service->listForCase($case);

        return response()->json([
            'success' => true,
            'message' => 'Case minutes retrieved successfully',
            'data' => [
                'projection' => $this->service->projectionFor($actor),
                'items' => $minutes->map(fn (CaseMinute $minute): JsonResource => $this->resource($minute, $actor))->values(),
                'capabilities' => $this->service->caseCapabilities($case, $actor),
            ],
        ]);
    }

    public function store(StoreCaseMinuteRequest $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('create', [CaseMinute::class, $case]);
        $minute = $this->service->create($case, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Case minute draft created successfully',
            'data' => $this->resource($minute, $request->user()),
        ], 201);
    }

    public function show(Request $request, CaseMinute $caseMinute): JsonResponse
    {
        Gate::authorize('view', $caseMinute);
        $minute = $this->service->find($caseMinute);

        return response()->json([
            'success' => true,
            'message' => 'Case minute retrieved successfully',
            'data' => $this->resource($minute, $request->user()),
        ]);
    }

    public function update(UpdateCaseMinuteRequest $request, CaseMinute $caseMinute): JsonResponse
    {
        Gate::authorize('update', $caseMinute);
        $minute = $this->service->update($caseMinute, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Case minute draft updated successfully',
            'data' => $this->resource($minute, $request->user()),
        ]);
    }

    public function createRevision(CreateCaseMinuteRevisionRequest $request, CaseMinute $caseMinute): JsonResponse
    {
        Gate::authorize('createRevision', $caseMinute);
        $minute = $this->service->createRevision($caseMinute, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Case minute revision draft created successfully',
            'data' => $this->resource($minute, $request->user()),
        ], 201);
    }

    public function finalize(FinalizeCaseMinuteRequest $request, CaseMinute $caseMinute): JsonResponse
    {
        Gate::authorize('finalize', $caseMinute);
        $minute = $this->service->finalize($caseMinute, $request->user(), (string) $request->validated('lock_version'));

        return response()->json([
            'success' => true,
            'message' => 'Case minute finalized successfully',
            'data' => $this->resource($minute, $request->user()),
        ]);
    }

    private function resource(CaseMinute $minute, User $actor): JsonResource
    {
        if ($this->service->projectionFor($actor) === 'metadata') {
            return new CaseMinuteMetadataResource($minute);
        }

        $minute->setAttribute('case_minute_capabilities', $this->service->minuteCapabilities($minute, $actor));

        return new CaseMinuteInternalResource($minute);
    }
}
