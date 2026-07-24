<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveReportWithdrawalRequest;
use App\Http\Requests\RejectReportWithdrawalRequest;
use App\Http\Requests\ReportWithdrawalReviewIndexRequest;
use App\Http\Resources\ReportWithdrawalReviewListResource;
use App\Http\Resources\ReportWithdrawalReviewResource;
use App\Services\ReportWithdrawalReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportWithdrawalReviewController extends Controller
{
    public function __construct(private readonly ReportWithdrawalReviewService $service) {}

    public function index(ReportWithdrawalReviewIndexRequest $request): JsonResponse
    {
        return ReportWithdrawalReviewListResource::collection(
            $this->service->index($request->user(), $request->validated()),
        )->additional([
            'success' => true,
            'message' => 'Antrean pencabutan berhasil dimuat.',
        ])->response();
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail pencabutan berhasil dimuat.',
            'data' => new ReportWithdrawalReviewResource(
                $this->service->detail($request->user(), $publicId),
            ),
        ]);
    }

    public function signedDocument(
        Request $request,
        string $publicId,
        string $attachmentPublicId,
    ): StreamedResponse {
        return $this->service->signedDocument(
            $request->user(),
            $publicId,
            $attachmentPublicId,
        );
    }

    public function approve(ApproveReportWithdrawalRequest $request, string $publicId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permohonan pencabutan telah disetujui.',
            'data' => new ReportWithdrawalReviewResource(
                $this->service->approve(
                    $request->user(),
                    $publicId,
                    (int) $request->validated('lock_version'),
                ),
            ),
        ]);
    }

    public function reject(RejectReportWithdrawalRequest $request, string $publicId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permohonan pencabutan telah ditolak.',
            'data' => new ReportWithdrawalReviewResource(
                $this->service->reject(
                    $request->user(),
                    $publicId,
                    (int) $request->validated('lock_version'),
                    (string) $request->validated('rejection_reason'),
                    (bool) $request->validated('resubmission_allowed'),
                ),
            ),
        ]);
    }
}
