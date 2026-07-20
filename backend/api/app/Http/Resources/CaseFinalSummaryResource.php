<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseFinalSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'outcome_code' => $this->outcome_code?->value,
            'outcome_label' => $this->outcome_code?->label(app()->getLocale()),
            'completion_date' => $this->completion_date?->toDateString(),
            'official_statement' => $this->official_statement,
            'investigation_summary' => $this->investigation_summary,
            'recommendation_result' => $this->recommendation_result,
            'decision_result' => $this->decision_result,
            'recovery_result' => $this->recovery_result,
            'actions_completed' => $this->actions_completed,
            'actions_uncompleted' => $this->actions_uncompleted,
            'follow_up_or_referral' => $this->follow_up_or_referral,
            'closing_explanation' => $this->closing_explanation,
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
