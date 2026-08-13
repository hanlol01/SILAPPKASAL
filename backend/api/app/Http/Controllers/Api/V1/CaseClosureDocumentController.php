<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueCaseClosureDocumentRequest;
use App\Http\Resources\CaseClosureDocumentResource;
use App\Models\CaseClosureDocument;
use App\Models\CaseRecord;
use App\Services\CaseClosureDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseClosureDocumentController extends Controller
{
    public function __construct(private readonly CaseClosureDocumentService $service) {}

    public function showForCase(Request $request, CaseRecord $case): JsonResponse
    {
        Gate::authorize('view', $case);
        $details = $this->service->details($case, $request->user());

        return response()->json(['success' => true, 'message' => 'Case closure document retrieved successfully', 'data' => [
            'document' => $details['document'] ? new CaseClosureDocumentResource($details['document']) : null,
            'capabilities' => $details['capabilities'],
            'signer_options' => $details['signer_options'],
        ]]);
    }

    public function issue(IssueCaseClosureDocumentRequest $request, CaseRecord $case): JsonResponse
    {
        $document = $this->service->issue($case, $request->user(), $request->validated('signer_id'));
        return response()->json(['success' => true, 'message' => 'Case closure document issued successfully', 'data' => new CaseClosureDocumentResource($document)], 201);
    }

    public function download(Request $request, CaseClosureDocument $caseClosureDocument): StreamedResponse
    {
        return $this->service->download($caseClosureDocument, $request->user());
    }

    public function preview(Request $request, CaseClosureDocument $caseClosureDocument): StreamedResponse
    {
        return $this->service->download($caseClosureDocument, $request->user(), true);
    }

    public function downloadForReporter(Request $request, string $registrationNumber): StreamedResponse
    {
        return $this->service->downloadForReporter($registrationNumber, $request->user());
    }

    public function previewForReporter(Request $request, string $registrationNumber): StreamedResponse
    {
        return $this->service->downloadForReporter($registrationNumber, $request->user(), true);
    }
}
