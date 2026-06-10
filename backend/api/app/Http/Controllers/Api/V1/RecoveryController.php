<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecoveryMonitoringRequest;
use App\Http\Requests\StoreRecoveryRequest;
use App\Http\Requests\UpdateRecoveryRequest;
use App\Http\Requests\UpdateRecoveryStatusRequest;
use App\Http\Resources\RecoveryMonitoringResource;
use App\Http\Resources\RecoveryResource;
use App\Models\Decision;
use App\Models\Recovery;
use App\Services\RecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RecoveryController extends Controller
{
    public function __construct(private readonly RecoveryService $recoveryService)
    {
    }

    public function storeForDecision(StoreRecoveryRequest $request, Decision $decision): JsonResponse
    {
        Gate::authorize('create', [Recovery::class, $decision]);

        $recovery = $this->recoveryService->createForDecision($decision, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recovery created successfully',
            'data' => new RecoveryResource($recovery),
        ], 201);
    }

    public function indexForDecision(Request $request, Decision $decision): JsonResponse
    {
        $recoveries = $this->recoveryService->listForDecision($decision, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recoveries retrieved successfully',
            'data' => RecoveryResource::collection($recoveries),
        ]);
    }

    public function show(Request $request, Recovery $recovery): JsonResponse
    {
        Gate::authorize('view', $recovery);

        return response()->json([
            'success' => true,
            'message' => 'Recovery retrieved successfully',
            'data' => new RecoveryResource($this->recoveryService->loadForUser($recovery, $request->user())),
        ]);
    }

    public function update(UpdateRecoveryRequest $request, Recovery $recovery): JsonResponse
    {
        Gate::authorize('update', $recovery);

        $recovery = $this->recoveryService->update($recovery, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recovery updated successfully',
            'data' => new RecoveryResource($recovery),
        ]);
    }

    public function updateStatus(UpdateRecoveryStatusRequest $request, Recovery $recovery): JsonResponse
    {
        Gate::authorize('updateStatus', $recovery);

        $recovery = $this->recoveryService->updateStatus($recovery, $request->user(), $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Recovery status updated successfully',
            'data' => new RecoveryResource($recovery),
        ]);
    }

    public function storeMonitoring(StoreRecoveryMonitoringRequest $request, Recovery $recovery): JsonResponse
    {
        Gate::authorize('createMonitoring', $recovery);

        $monitoring = $this->recoveryService->createMonitoring($recovery, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Recovery monitoring created successfully',
            'data' => new RecoveryMonitoringResource($monitoring),
        ], 201);
    }

    public function indexMonitoring(Request $request, Recovery $recovery): JsonResponse
    {
        Gate::authorize('view', $recovery);

        $monitorings = $this->recoveryService->listMonitoring($recovery, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recovery monitoring retrieved successfully',
            'data' => RecoveryMonitoringResource::collection($monitorings),
        ]);
    }
}
