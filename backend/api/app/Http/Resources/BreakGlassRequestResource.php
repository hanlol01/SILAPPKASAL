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
        $expiresAt = $this->viewed_at?->copy()->addHours(8);

        return [
            'id' => $this->id,
            'requestor' => $this->whenLoaded('requestor', fn (): ?array => $this->requestor ? [
                'id' => $this->requestor->id,
                'name' => $this->requestor->name,
                'role' => $this->requestor->role ? [
                    'code' => $this->requestor->role->code,
                    'name' => $this->requestor->role->name,
                ] : null,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn (): ?array => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'role' => $this->approver->role ? [
                    'code' => $this->approver->role->code,
                    'name' => $this->approver->role->name,
                ] : null,
            ] : null),
            'report' => $this->whenLoaded('report', fn (): ?array => $this->report ? [
                'id' => $this->report->id,
                'registration_number' => $this->report->registration_number,
                'report_type' => $this->report->report_type,
            ] : null),
            'reason_category' => $this->reason_category,
            'reason' => $this->reason,
            'status' => $this->status,
            'denial_reason' => $this->denial_reason,
            'requested_at' => $this->requested_at?->toJSON(),
            'approved_at' => $this->approved_at?->toJSON(),
            'denied_at' => $this->denied_at?->toJSON(),
            'viewed_at' => $this->viewed_at?->toJSON(),
            'is_viewable' => $this->isViewable(),
            'expires_at' => $expiresAt?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
