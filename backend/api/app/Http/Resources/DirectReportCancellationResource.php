<?php

namespace App\Http\Resources;

use App\Models\Report;
use App\Models\ReportWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DirectReportCancellationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReportWithdrawal $withdrawal */
        $withdrawal = $this->resource['withdrawal'];
        /** @var Report $report */
        $report = $this->resource['report'];

        return [
            'withdrawal_reference' => $withdrawal->public_id,
            'report_status' => $report->status,
            'portal_status' => $report->portal_status,
            'completed_at' => $withdrawal->completed_at?->toJSON(),
            'capabilities' => $this->resource['capabilities'],
        ];
    }
}
