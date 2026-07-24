<?php

namespace App\Services;

use App\Enums\ReportWithdrawalRequestType;
use App\Enums\ReportWithdrawalStatus;
use App\Models\CaseRecord;
use App\Models\Report;
use App\Models\ReportWithdrawal;
use App\Support\ApiErrorCode;
use Illuminate\Http\Exceptions\HttpResponseException;

class CaseMutationGuard
{
    public function lockAndAssertMutable(CaseRecord|int $case): CaseRecord
    {
        $caseId = $case instanceof CaseRecord ? $case->id : $case;
        $reportId = $case instanceof CaseRecord
            ? $case->report_id
            : CaseRecord::query()->whereKey($caseId)->value('report_id');

        Report::query()
            ->whereKey($reportId)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedCase = CaseRecord::query()
            ->with(['status', 'activeAssignments.satgas'])
            ->whereKey($caseId)
            ->lockForUpdate()
            ->firstOrFail();

        $this->assertTerminalState($lockedCase);

        $pendingWithdrawal = ReportWithdrawal::query()
            ->where('case_id', $lockedCase->id)
            ->where('request_type', ReportWithdrawalRequestType::FormalWithdrawal->value)
            ->where('status', ReportWithdrawalStatus::PendingReview->value)
            ->lockForUpdate()
            ->first();

        if ($pendingWithdrawal !== null) {
            $this->throwPaused();
        }

        return $lockedCase;
    }

    public function assertMutable(CaseRecord $case): void
    {
        $this->assertTerminalState($case);

        if ($case->pendingFormalWithdrawal()->exists()) {
            $this->throwPaused();
        }
    }

    private function assertTerminalState(CaseRecord $case): void
    {
        if (! $case->isOperationallyTerminal()) {
            return;
        }

        $isWithdrawn = $case->isWithdrawn();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $isWithdrawn
                ? __('api.errors.'.ApiErrorCode::CaseOperationallyTerminal)
                : 'Closed cases cannot accept operational changes',
            'error_code' => $isWithdrawn ? ApiErrorCode::CaseOperationallyTerminal : null,
            'errors' => null,
        ], $isWithdrawn ? 409 : 422));
    }

    private function throwPaused(): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('api.errors.'.ApiErrorCode::WithdrawalPendingReview),
            'error_code' => ApiErrorCode::WithdrawalPendingReview,
            'errors' => null,
        ], 409));
    }
}
