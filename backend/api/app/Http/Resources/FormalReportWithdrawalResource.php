<?php

namespace App\Http\Resources;

use App\Models\ReportWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormalReportWithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReportWithdrawal $withdrawal */
        $withdrawal = $this->resource['withdrawal'];
        $attachment = $withdrawal->currentSignedAttachment();

        return [
            'withdrawal_reference' => $withdrawal->public_id,
            'request_type' => $withdrawal->request_type->value,
            'status' => $withdrawal->status->value,
            'lock_version' => $withdrawal->lock_version,
            'reason' => $withdrawal->reason,
            'created_at' => $withdrawal->created_at?->toJSON(),
            'draft_document_viewed_at' => $withdrawal->draft_document_viewed_at?->toJSON(),
            'submitted_at' => $withdrawal->submitted_at?->toJSON(),
            'cancelled_at' => $withdrawal->cancelled_at?->toJSON(),
            'has_signed_document' => $attachment !== null,
            'latest_attachment' => $attachment
                ? new ReportWithdrawalAttachmentResource($attachment)
                : null,
            'capabilities' => $this->resource['capabilities'],
        ];
    }
}
