<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportEvidenceSubmissionRequest;
use App\Http\Resources\ReportEvidenceSubmissionResource;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Services\ReportEvidenceSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportEvidenceSubmissionController extends Controller
{
    public function __construct(private readonly ReportEvidenceSubmissionService $service)
    {
    }

    public function indexForReporter(Request $request, string $registrationNumber): JsonResponse
    {
        $result = $this->service->listForReporter($request->user(), $registrationNumber);

        return response()->json([
            'success' => true,
            'message' => 'Supporting files retrieved successfully',
            'data' => ReportEvidenceSubmissionResource::collection($result['files']),
            'meta' => [
                'upload_allowed' => $result['upload_allowed'],
                'max_files' => ReportEvidenceSubmissionService::MAX_FILES_PER_REPORT,
                'remaining_slots' => $result['remaining_slots'],
            ],
        ]);
    }

    public function storeForReporter(
        StoreReportEvidenceSubmissionRequest $request,
        string $registrationNumber,
    ): JsonResponse {
        $submission = $this->service->uploadForReporter(
            $request->user(),
            $registrationNumber,
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Supporting file uploaded successfully',
            'data' => new ReportEvidenceSubmissionResource($submission),
        ], 201);
    }

    public function downloadForReporter(Request $request, string $uuid): StreamedResponse
    {
        return $this->service->downloadForReporter($request->user(), $uuid);
    }

    public function previewForReporter(Request $request, string $uuid): StreamedResponse
    {
        return $this->service->previewForReporter($request->user(), $uuid);
    }

    public function indexForCase(Request $request, CaseRecord $case): JsonResponse
    {
        $files = $this->service->listForAssignedSatgas($request->user(), $case);

        return response()->json([
            'success' => true,
            'message' => 'Reporter supporting files retrieved successfully',
            'data' => ReportEvidenceSubmissionResource::collection($files),
        ]);
    }

    public function indexForOversightReport(Request $request, Report $report): JsonResponse
    {
        $files = $this->service->listForOversightReport($request->user(), $report);

        return response()->json([
            'success' => true,
            'message' => 'Reporter supporting files retrieved successfully',
            'data' => ReportEvidenceSubmissionResource::collection($files),
        ]);
    }

    public function downloadForSatgas(Request $request, string $uuid): StreamedResponse
    {
        return $this->service->downloadForAssignedSatgas($request->user(), $uuid);
    }

    public function previewForSatgas(Request $request, string $uuid): StreamedResponse
    {
        return $this->service->previewForAssignedSatgas($request->user(), $uuid);
    }
}
