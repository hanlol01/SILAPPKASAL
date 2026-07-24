<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForwardReportToCaseRequest;
use App\Http\Requests\ReportIndexRequest;
use App\Http\Requests\ReportStoreRequest;
use App\Http\Requests\ReportTrackingRequest;
use App\Http\Resources\ForwardReportToCaseResource;
use App\Http\Resources\ReportMetadataResource;
use App\Http\Resources\ReportSubmissionResource;
use App\Http\Resources\ReportTrackingResource;
use App\Models\Report;
use App\Services\CaseService;
use App\Services\ReportService;
use App\Support\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly CaseService $caseService,
    ) {}

    public function store(ReportStoreRequest $request): JsonResponse
    {
        $report = $this->reportService->submit(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Complaint submitted successfully',
            'data' => new ReportSubmissionResource($report),
        ], 201);
    }

    public function index(ReportIndexRequest $request): JsonResponse
    {
        $reports = $this->reportService->listForUser($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Complaints retrieved successfully',
            'data' => ReportMetadataResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('view', $report);

        $canReadSubmittedDetails = $this->reportService->canReadSubmittedDetails($request->user(), $report);
        $relations = [
            'category',
            'case.priorityLevel',
            'case.activeAssignments.satgas',
        ];

        if ($canReadSubmittedDetails) {
            array_push($relations, 'locationType', 'campusStatus', 'relation');
        }

        if ($report->report_type !== 'anonymous') {
            $relations[] = $canReadSubmittedDetails ? 'reporter.faculty' : 'reporter';

            if ($canReadSubmittedDetails) {
                $relations[] = 'reporter.studyProgram';
            }
        }

        $report->load($relations);

        $report->setAttribute(
            'sensitive_oversight',
            $canReadSubmittedDetails,
        );
        $report->setAttribute('include_case_context', true);

        return response()->json([
            'success' => true,
            'message' => 'Complaint retrieved successfully',
            'data' => new ReportMetadataResource($report),
        ]);
    }

    public function track(ReportTrackingRequest $request, string $trackingCode): JsonResponse
    {
        $report = $this->reportService->findByTrackingCode($trackingCode);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.tracking_not_found'),
                'error_code' => ApiErrorCode::TrackingNotFound,
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Complaint tracking retrieved successfully',
            'data' => new ReportTrackingResource($report),
        ]);
    }

    public function forwardToCase(ForwardReportToCaseRequest $request, Report $report): JsonResponse
    {
        Gate::authorize('forward', $report);

        $case = $this->caseService->forwardReport($report, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Complaint forwarded to Satgas. Case created.',
            'data' => new ForwardReportToCaseResource($case),
        ]);
    }
}
