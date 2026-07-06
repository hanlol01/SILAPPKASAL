<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reporter-safe report progress timeline.
 *
 * Exposes only safe stage codes, safe timestamps, and the completion state.
 * Internal workflow status codes, staff identities, assignments, and
 * narrative content must never be added to this resource.
 */
class PortalReportTimelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'registration_number' => $this->resource['registration_number'],
            'portal_status' => $this->resource['portal_status'],
            'is_completed' => $this->resource['is_completed'],
            'events' => $this->resource['events'],
        ];
    }
}
