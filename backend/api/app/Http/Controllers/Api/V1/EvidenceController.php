<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Requests\UpdateEvidenceRequest;
use App\Http\Requests\UpdateEvidenceStatusRequest;
use App\Http\Requests\UploadEvidenceFileRequest;
use App\Http\Resources\EvidenceCustodyEventResource;
use App\Http\Resources\EvidenceResource;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Services\EvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function __construct(private readonly EvidenceService $evidenceService)
    {
    }

    public function storeForInvestigation(StoreEvidenceRequest $request, Investigation $investigation): JsonResponse
    {
        Gate::authorize('create', [Evidence::class, $investigation]);

        $evidence = $this->evidenceService->createForInvestigation($investigation, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Evidence metadata created successfully',
            'data' => new EvidenceResource($evidence),
        ], 201);
    }

    public function indexForInvestigation(Request $request, Investigation $investigation): JsonResponse
    {
        $evidences = $this->evidenceService->listForInvestigation($investigation, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Evidence metadata retrieved successfully',
            'data' => EvidenceResource::collection($evidences),
        ]);
    }

    public function show(Request $request, Evidence $evidence): JsonResponse
    {
        Gate::authorize('view', $evidence);

        return response()->json([
            'success' => true,
            'message' => 'Evidence metadata retrieved successfully',
            'data' => new EvidenceResource($this->evidenceService->loadForUser($evidence, $request->user())),
        ]);
    }

    public function update(UpdateEvidenceRequest $request, Evidence $evidence): JsonResponse
    {
        Gate::authorize('update', $evidence);

        $evidence = $this->evidenceService->update($evidence, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Evidence metadata updated successfully',
            'data' => new EvidenceResource($evidence),
        ]);
    }

    public function updateStatus(UpdateEvidenceStatusRequest $request, Evidence $evidence): JsonResponse
    {
        Gate::authorize('updateStatus', $evidence);

        $evidence = $this->evidenceService->updateStatus($evidence, $request->user(), $request->validated('status'));

        return response()->json([
            'success' => true,
            'message' => 'Evidence status updated successfully',
            'data' => new EvidenceResource($evidence),
        ]);
    }

    public function custody(Request $request, Evidence $evidence): JsonResponse
    {
        Gate::authorize('viewCustody', $evidence);

        $events = $this->evidenceService->listCustodyEvents($evidence, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Evidence custody events retrieved successfully',
            'data' => EvidenceCustodyEventResource::collection($events),
        ]);
    }

    public function uploadFile(UploadEvidenceFileRequest $request, Evidence $evidence): JsonResponse
    {
        Gate::authorize('uploadFile', $evidence);

        $evidence = $this->evidenceService->uploadFile($evidence, $request->user(), $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Evidence file uploaded successfully',
            'data' => new EvidenceResource($evidence),
        ]);
    }

    public function downloadFile(Request $request, Evidence $evidence): StreamedResponse
    {
        Gate::authorize('downloadFile', $evidence);

        return $this->evidenceService->downloadFile($evidence, $request->user());
    }

    public function previewFile(Request $request, Evidence $evidence): StreamedResponse
    {
        Gate::authorize('previewFile', $evidence);

        return $this->evidenceService->previewFile($evidence, $request->user());
    }
}
