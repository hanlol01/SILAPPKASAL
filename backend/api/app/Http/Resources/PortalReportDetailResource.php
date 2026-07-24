<?php

namespace App\Http\Resources;

use App\Support\ReportInputProjection;
use Illuminate\Http\Request;

class PortalReportDetailResource extends PortalReportResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'submitted_details' => ReportInputProjection::make($this->resource, true),
            'withdrawal_capabilities' => $this->withdrawal_capabilities,
        ];
    }
}
