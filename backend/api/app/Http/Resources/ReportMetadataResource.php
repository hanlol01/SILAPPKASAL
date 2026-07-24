<?php

namespace App\Http\Resources;

use App\Models\CaseRecord;
use App\Support\ReportInputProjection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportMetadataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAnonymous = $this->report_type === 'anonymous';
        $actor = $request->user();
        $maySeeWithdrawalWorkflow = ($actor?->hasRole('admin') === true
            && $actor->hasPermission('reports.withdraw.review.own_campus'))
            || ($actor?->hasRole('super_admin') === true
                && $actor->hasPermission('reports.read.all'));

        $data = [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'report_type' => $this->report_type,
            'is_anonymous' => $isAnonymous,
            'reporter' => $isAnonymous ? ['masked' => true] : $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
            ]),
            'category' => new MasterDataResource($this->whenLoaded('category')),
            'status' => $this->status,
            'priority' => $this->priorityProjection(),
            'case' => $this->when(
                $this->resource->getAttribute('include_case_context') === true,
                function () {
                    if ($this->case === null) {
                        return null;
                    }

                    return [
                        'id' => $this->case->id,
                        'case_number' => $this->case->case_number,
                        'active_assignments' => $this->case->relationLoaded('activeAssignments')
                            ? $this->case->activeAssignments->map(fn ($assignment) => [
                                'satgas_id' => $assignment->satgas_id,
                                'satgas_name' => $assignment->satgas?->name,
                                'is_lead' => $assignment->is_lead,
                                'is_active' => $assignment->is_active,
                            ])->values()
                            : [],
                    ];
                },
            ),
            'submitted_at' => $this->submitted_at?->toJSON(),
            'reviewed_at' => $this->reviewed_at?->toJSON(),
            'forwarded_at' => $this->forwarded_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'withdrawn_at' => $this->withdrawn_at?->toJSON(),
            'withdrawal_workflow' => $this->when(
                $maySeeWithdrawalWorkflow && $this->resource->relationLoaded('latestFormalWithdrawal'),
                function () {
                    $withdrawal = $this->latestFormalWithdrawal;

                    return $withdrawal ? [
                        'withdrawal_reference' => $withdrawal->public_id,
                        'status' => $withdrawal->status?->value,
                        'submitted_at' => $withdrawal->submitted_at?->toJSON(),
                        'reviewed_at' => $withdrawal->reviewed_at?->toJSON(),
                    ] : null;
                },
            ),
        ];

        if ($this->resource->getAttribute('sensitive_oversight') === true) {
            $data['submitted_details'] = ReportInputProjection::make($this->resource, ! $isAnonymous);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function priorityProjection(): array
    {
        $case = $this->whenLoaded('case');

        if (! $case instanceof CaseRecord) {
            return [
                'availability' => 'unavailable',
                'level' => null,
            ];
        }

        if ($case->priorityLevel === null) {
            return [
                'availability' => 'unassessed',
                'level' => null,
            ];
        }

        return [
            'availability' => 'assessed',
            'level' => new MasterDataResource($case->priorityLevel),
        ];
    }
}
