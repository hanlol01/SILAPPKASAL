<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelReportRequest;
use App\Http\Requests\MutateFormalReportWithdrawalRequest;
use App\Http\Requests\ResubmitFormalReportWithdrawalRequest;
use App\Http\Requests\StoreFormalReportWithdrawalRequest;
use App\Http\Requests\UploadReportWithdrawalDocumentRequest;
use App\Http\Resources\DirectReportCancellationResource;
use App\Http\Resources\FormalReportWithdrawalResource;
use App\Services\FormalReportWithdrawalService;
use App\Services\ReportWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportWithdrawalController extends Controller
{
    public function __construct(
        private readonly ReportWithdrawalService $withdrawalService,
        private readonly FormalReportWithdrawalService $formalWithdrawalService,
    ) {}

    public function cancel(CancelReportRequest $request, string $registrationNumber): JsonResponse
    {
        $result = $this->withdrawalService->cancelDirectly(
            $request->user(),
            $registrationNumber,
            (string) $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_cancelled'),
            'data' => new DirectReportCancellationResource($result),
        ]);
    }

    public function current(Request $request, string $registrationNumber): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_withdrawal_loaded'),
            'data' => new FormalReportWithdrawalResource(
                $this->formalWithdrawalService->current($request->user(), $registrationNumber),
            ),
        ]);
    }

    public function store(
        StoreFormalReportWithdrawalRequest $request,
        string $registrationNumber,
    ): JsonResponse {
        $result = $this->formalWithdrawalService->create(
            $request->user(),
            $registrationNumber,
            (string) $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_withdrawal_created'),
            'data' => new FormalReportWithdrawalResource($result),
        ], 201);
    }

    public function draftDocument(Request $request, string $publicId): Response
    {
        return $this->formalWithdrawalService->draftDocument($request->user(), $publicId);
    }

    public function downloadDraftDocument(Request $request, string $publicId): StreamedResponse
    {
        return $this->formalWithdrawalService->downloadDraftDocument($request->user(), $publicId);
    }

    public function draftDocumentExample(Request $request, string $publicId): BinaryFileResponse
    {
        return $this->formalWithdrawalService->draftDocumentExample($request->user(), $publicId);
    }

    public function uploadSignedDocument(
        UploadReportWithdrawalDocumentRequest $request,
        string $publicId,
    ): JsonResponse {
        $result = $this->formalWithdrawalService->uploadSignedDocument(
            $request->user(),
            $publicId,
            $request->file('file'),
            (int) $request->validated('lock_version'),
        );

        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_withdrawal_document_uploaded'),
            'data' => new FormalReportWithdrawalResource($result),
        ], 201);
    }

    public function downloadSignedDocument(
        Request $request,
        string $publicId,
        string $attachmentPublicId,
    ): StreamedResponse {
        return $this->formalWithdrawalService->downloadSignedDocument(
            $request->user(),
            $publicId,
            $attachmentPublicId,
        );
    }

    public function submit(MutateFormalReportWithdrawalRequest $request, string $publicId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_withdrawal_submitted'),
            'data' => new FormalReportWithdrawalResource(
                $this->formalWithdrawalService->submit(
                    $request->user(),
                    $publicId,
                    (int) $request->validated('lock_version'),
                ),
            ),
        ]);
    }

    public function cancelFormal(MutateFormalReportWithdrawalRequest $request, string $publicId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_withdrawal_cancelled'),
            'data' => new FormalReportWithdrawalResource(
                $this->formalWithdrawalService->cancel(
                    $request->user(),
                    $publicId,
                    (int) $request->validated('lock_version'),
                ),
            ),
        ]);
    }

    public function resubmit(ResubmitFormalReportWithdrawalRequest $request, string $publicId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_withdrawal_created'),
            'data' => new FormalReportWithdrawalResource(
                $this->formalWithdrawalService->resubmit(
                    $request->user(),
                    $publicId,
                    (string) $request->validated('reason'),
                    (int) $request->validated('lock_version'),
                ),
            ),
        ], 201);
    }
}
