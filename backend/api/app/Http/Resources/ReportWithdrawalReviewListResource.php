<?php

namespace App\Http\Resources;

use App\Models\ReportWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportWithdrawalReviewListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportWithdrawal $withdrawal */
        $withdrawal = $this->resource;
        $report = $withdrawal->report;
        $isSuperAdmin = $request->user()?->hasRole('super_admin') === true;

        return [
            'withdrawal_reference' => $withdrawal->public_id,
            'registration_number' => $withdrawal->registration_number_snapshot,
            'status' => $withdrawal->status->value,
            'submitted_at' => $withdrawal->submitted_at?->toJSON(),
            'reviewed_at' => $withdrawal->reviewed_at?->toJSON(),
            'elapsed_waiting_seconds' => $withdrawal->submitted_at
                ? max(0, $withdrawal->submitted_at->diffInSeconds(now()))
                : null,
            'campus' => $report?->reporter?->university ? [
                'code' => $report->reporter->university->code,
                'name' => $report->reporter->university->name,
            ] : null,
            'reporter_display_name' => $this->when(
                ! $isSuperAdmin,
                $report?->report_type === 'anonymous'
                    ? 'Pelapor Anonim'
                    : $withdrawal->requester_display_name_snapshot,
            ),
        ];
    }
}
