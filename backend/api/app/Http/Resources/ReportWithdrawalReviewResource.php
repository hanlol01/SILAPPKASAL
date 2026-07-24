<?php

namespace App\Http\Resources;

use App\Models\ReportWithdrawal;
use Illuminate\Http\Request;

class ReportWithdrawalReviewResource extends ReportWithdrawalReviewListResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportWithdrawal $withdrawal */
        $withdrawal = $this->resource;
        $isSuperAdmin = $request->user()?->hasRole('super_admin') === true;
        $base = parent::toArray($request);
        $history = array_values(array_filter([
            $withdrawal->submitted_at ? ['status' => 'pending_review', 'occurred_at' => $withdrawal->submitted_at->toJSON()] : null,
            $withdrawal->approved_at ? ['status' => 'approved', 'occurred_at' => $withdrawal->approved_at->toJSON()] : null,
            $withdrawal->rejected_at ? ['status' => 'rejected', 'occurred_at' => $withdrawal->rejected_at->toJSON()] : null,
            $withdrawal->cancelled_at ? ['status' => 'cancelled', 'occurred_at' => $withdrawal->cancelled_at->toJSON()] : null,
        ]));

        if ($isSuperAdmin) {
            return $base + [
                'approved_at' => $withdrawal->approved_at?->toJSON(),
                'rejected_at' => $withdrawal->rejected_at?->toJSON(),
                'cancelled_at' => $withdrawal->cancelled_at?->toJSON(),
                'capabilities' => [
                    'can_review' => false,
                    'can_approve' => false,
                    'can_reject' => false,
                    'can_view_signed_document' => false,
                ],
                'history' => $history,
            ];
        }

        return $base + [
            'created_at' => $withdrawal->created_at?->toJSON(),
            'approved_at' => $withdrawal->approved_at?->toJSON(),
            'rejected_at' => $withdrawal->rejected_at?->toJSON(),
            'cancelled_at' => $withdrawal->cancelled_at?->toJSON(),
            'lock_version' => $withdrawal->lock_version,
            'report_status' => $withdrawal->report?->status,
            'case_status' => $withdrawal->case?->status?->name,
            'capabilities' => $withdrawal->getAttribute('review_capabilities') ?? [
                'can_review' => false,
                'can_approve' => false,
                'can_reject' => false,
                'can_view_signed_document' => false,
            ],
            'history' => array_values(array_filter([
                $withdrawal->created_at ? ['status' => 'draft', 'occurred_at' => $withdrawal->created_at->toJSON()] : null,
                ...$history,
            ])),
            'reason' => $withdrawal->reason,
            'rejection_reason' => $withdrawal->rejection_reason,
            'resubmission_allowed' => $withdrawal->resubmission_allowed,
            'attachments' => ReportWithdrawalAttachmentResource::collection(
                $withdrawal->relationLoaded('attachments')
                    ? $withdrawal->attachments
                    : collect(),
            ),
        ];
    }
}
