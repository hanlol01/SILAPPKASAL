<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalReportHandlingProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'registration_number' => $this->resource['registration_number'],
            'case' => $this->resource['case'],
            'investigation' => $this->resource['investigation'],
            'recommendation' => $this->resource['recommendation'],
            'decision' => $this->resource['decision'],
            'recovery' => $this->resource['recovery'],
            'monitoring' => $this->resource['monitoring'],
            'evidence' => $this->resource['evidence'],
            'final_summary' => $this->resource['final_summary'],
            'closure_document' => $this->resource['closure_document'],
        ];
    }
}
