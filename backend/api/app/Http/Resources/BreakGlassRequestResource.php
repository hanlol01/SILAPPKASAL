<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakGlassRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requestor' => $this->whenLoaded('requestor', fn (): ?array => $this->requestor ? [
                'name' => $this->requestor->name,
                'role' => $this->requestor->role ? [
                    'code' => $this->requestor->role->code,
                    'name' => $this->requestor->role->name,
                ] : null,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn (): ?array => $this->approver ? [
                'name' => $this->approver->name,
                'role' => $this->approver->role ? [
                    'code' => $this->approver->role->code,
                    'name' => $this->approver->role->name,
                ] : null,
            ] : null),
            'report' => $this->whenLoaded('report', fn (): ?array => $this->report ? [
                'registration_number' => $this->report->registration_number,
                'report_type' => $this->report->report_type,
            ] : null),
            'case' => $this->when(
                $this->resource->relationLoaded('report') && $this->report?->relationLoaded('case'),
                fn (): ?array => $this->report?->case ? [
                    'case_number' => $this->report->case->case_number,
                ] : null,
            ),
            'reason_category' => $this->reason_category,
            'reason' => $this->reason,
            'requested_duration_minutes' => (int) $this->requested_duration_minutes,
            'status' => $this->resource->getAttribute('effective_status') ?? $this->effectiveStatus(),
            'denial_reason' => $this->denial_reason,
            'revocation_reason' => $this->revocation_reason,
            'requested_at' => $this->requested_at?->toJSON(),
            'approved_at' => $this->approved_at?->toJSON(),
            'grant_starts_at' => $this->grant_starts_at?->toJSON(),
            'expires_at' => $this->expires_at?->toJSON(),
            'revoked_at' => $this->revoked_at?->toJSON(),
            'denied_at' => $this->denied_at?->toJSON(),
            'viewed_at' => $this->viewed_at?->toJSON(),
            'view_count' => (int) $this->view_count,
            'last_viewed_at' => $this->last_viewed_at?->toJSON(),
            'can_reveal' => $this->resource->getAttribute('can_reveal') === true,
            'can_revoke' => $this->resource->getAttribute('can_revoke') === true,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
