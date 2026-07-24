<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelReportRequest;
use App\Http\Resources\DirectReportCancellationResource;
use App\Services\ReportWithdrawalService;
use Illuminate\Http\JsonResponse;

class ReportWithdrawalController extends Controller
{
    public function __construct(private readonly ReportWithdrawalService $withdrawalService) {}

    public function cancel(CancelReportRequest $request, string $registrationNumber): JsonResponse
    {
        $result = $this->withdrawalService->cancelDirectly(
            $request->user(),
            $registrationNumber,
            (string) $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => __('api.messages.report_cancelled'),
            'data' => new DirectReportCancellationResource($result),
        ]);
    }
}
