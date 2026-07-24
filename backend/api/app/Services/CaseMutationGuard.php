<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Support\ApiErrorCode;
use Illuminate\Http\Exceptions\HttpResponseException;

class CaseMutationGuard
{
    public function lockAndAssertMutable(CaseRecord|int $case): CaseRecord
    {
        $caseId = $case instanceof CaseRecord ? $case->id : $case;
        $lockedCase = CaseRecord::query()
            ->with(['status', 'activeAssignments.satgas'])
            ->whereKey($caseId)
            ->lockForUpdate()
            ->firstOrFail();

        $this->assertMutable($lockedCase);

        return $lockedCase;
    }

    public function assertMutable(CaseRecord $case): void
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
}
