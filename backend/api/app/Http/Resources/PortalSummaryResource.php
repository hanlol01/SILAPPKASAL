<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_reports' => $this->resource['total_reports'],
            'active_reports' => $this->resource['active_reports'],
            'completed_reports' => $this->resource['completed_reports'],
            'unread_notifications' => $this->resource['unread_notifications'],
        ];
    }
}
