<?php

namespace App\Services;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\ApiErrorCode;

class CaseWorkflowContextService
{
    /**
     * @return array<string, mixed>
     */
    public function forCase(CaseRecord $case, User $actor): array
    {
        $case->loadMissing([
            'status',
            'activeAssignments',
            'investigation.status',
        ]);

        $caseStatus = $case->status?->name;
        $investigation = $case->investigation;
        $investigationStatus = $investigation?->status?->name;
        $recommendationExists = $case->recommendation()->exists();
        $assessmentComplete = filled($case->risk_level_code) && filled($case->priority_code);
        $isClosed = $case->closed_at !== null || $caseStatus === CaseStatusEnum::Closed->value;
        $activeAssignment = $case->activeAssignments->firstWhere('satgas_id', $actor->id);
        $isAssigned = $actor->is_active && $actor->hasRole('satgas_ppks') && $activeAssignment !== null;
        $isLead = $isAssigned && (bool) $activeAssignment?->is_lead;
        $canInvestigate = $isAssigned && $actor->hasPermission('cases.investigate');
        $currentStageActivityCount = $investigation
            ? $investigation->activities()
                ->where('investigation_stage_code', $investigation->status_code)
                ->count()
            : 0;

        $updateCaseStatus = $this->action($isAssigned && ! $isClosed, $isAssigned ? 'case_closed' : 'read_only_no_assignment');
        if ($updateCaseStatus['allowed'] && $caseStatus === CaseStatusEnum::Assessment->value && ! $assessmentComplete) {
            $updateCaseStatus = $this->action(false, ApiErrorCode::CaseAssessmentRequired);
        }
        if (
            $updateCaseStatus['allowed']
            && $caseStatus === CaseStatusEnum::Investigation->value
            && $investigationStatus !== InvestigationStatusEnum::Completed->value
        ) {
            $updateCaseStatus = $this->action(false, ApiErrorCode::CaseInvestigationCompletionRequired);
        }

        $createInvestigation = $this->action(
            $canInvestigate
                && ! $isClosed
                && $caseStatus === CaseStatusEnum::Investigation->value
                && $investigation === null
                && $isLead,
            ! $isAssigned
                ? 'read_only_no_assignment'
                : (! $canInvestigate
                    ? 'permission_missing'
                    : (! $isLead
                        ? 'investigation_lead_required'
                        : ($investigation !== null ? 'investigation_exists' : 'case_not_investigation'))),
        );

        $addActivity = $this->action(
            $canInvestigate
                && ! $isClosed
                && $caseStatus === CaseStatusEnum::Investigation->value
                && $investigation !== null
                && $investigationStatus !== InvestigationStatusEnum::Completed->value,
            $this->investigationUnavailableReason($isAssigned, $canInvestigate, $isClosed, $caseStatus, $investigation !== null, $investigationStatus),
        );

        $updateInvestigationStatus = $addActivity;
        if ($updateInvestigationStatus['allowed'] && $currentStageActivityCount === 0) {
            $updateInvestigationStatus = $this->action(false, ApiErrorCode::InvestigationStageActivityRequired);
        }

        $canAddEvidence = $isAssigned
            && $actor->hasPermission('evidence.view.case')
            && $actor->hasPermission('evidence.upload')
            && ! $isClosed
            && $caseStatus === CaseStatusEnum::Investigation->value
            && $investigation !== null
            && $investigationStatus !== InvestigationStatusEnum::Completed->value;
        $addEvidence = $this->action(
            $canAddEvidence,
            $this->evidenceUnavailableReason($actor, $isAssigned, $isClosed, $caseStatus, $investigation !== null, $investigationStatus),
        );

        $createRecommendation = $this->action(
            $isAssigned
                && $actor->hasPermission('cases.recommend')
                && ! $isClosed
                && $caseStatus === CaseStatusEnum::Recommendation->value
                && $investigationStatus === InvestigationStatusEnum::Completed->value
                && ! $recommendationExists,
            ! $isAssigned
                ? 'read_only_no_assignment'
                : (! $actor->hasPermission('cases.recommend')
                    ? 'permission_missing'
                    : ($recommendationExists
                        ? 'recommendation_exists'
                        : ($investigationStatus !== InvestigationStatusEnum::Completed->value
                            ? ApiErrorCode::CaseInvestigationCompletionRequired
                            : 'case_not_recommendation'))),
        );

        return [
            'facts' => [
                'assessment_complete' => $assessmentComplete,
                'investigation_exists' => $investigation !== null,
                'investigation_status' => $investigationStatus,
                'investigation_status_code' => $investigation?->status_code,
                'current_stage_activity_count' => $currentStageActivityCount,
                'recommendation_exists' => $recommendationExists,
                'active_assignment' => $isAssigned,
                'active_lead_assignment' => $isLead,
            ],
            'actions' => [
                'update_case_status' => $updateCaseStatus,
                'create_investigation' => $createInvestigation,
                'add_activity' => $addActivity,
                'update_investigation_status' => $updateInvestigationStatus,
                'add_evidence' => $addEvidence,
                'create_recommendation' => $createRecommendation,
            ],
            'primary_tip_code' => $this->primaryTip(
                $caseStatus,
                $isAssigned,
                $assessmentComplete,
                $investigation !== null,
                $investigationStatus,
                $currentStageActivityCount,
                $recommendationExists,
                $isLead,
            ),
            'primary_tip_params' => [
                'stage' => $investigationStatus,
            ],
        ];
    }

    /** @return array{allowed: bool, reason_code: string|null} */
    private function action(bool $allowed, ?string $reasonCode): array
    {
        return ['allowed' => $allowed, 'reason_code' => $allowed ? null : $reasonCode];
    }

    private function investigationUnavailableReason(
        bool $isAssigned,
        bool $canInvestigate,
        bool $isClosed,
        ?string $caseStatus,
        bool $investigationExists,
        ?string $investigationStatus,
    ): string {
        return match (true) {
            ! $isAssigned => 'read_only_no_assignment',
            ! $canInvestigate => 'permission_missing',
            $isClosed => 'case_closed',
            $caseStatus !== CaseStatusEnum::Investigation->value => 'case_not_investigation',
            ! $investigationExists => 'investigation_missing',
            $investigationStatus === InvestigationStatusEnum::Completed->value => 'investigation_completed',
            default => 'action_unavailable',
        };
    }

    private function evidenceUnavailableReason(
        User $actor,
        bool $isAssigned,
        bool $isClosed,
        ?string $caseStatus,
        bool $investigationExists,
        ?string $investigationStatus,
    ): string {
        return match (true) {
            ! $actor->hasRole('satgas_ppks') => 'not_satgas',
            ! $isAssigned => 'read_only_no_assignment',
            ! $actor->hasPermission('evidence.view.case') || ! $actor->hasPermission('evidence.upload') => 'permission_missing',
            $isClosed => 'case_closed',
            ! $investigationExists => 'investigation_missing',
            $caseStatus !== CaseStatusEnum::Investigation->value => 'case_not_investigation',
            $investigationStatus === InvestigationStatusEnum::Completed->value => 'investigation_completed',
            default => 'action_unavailable',
        };
    }

    private function primaryTip(
        ?string $caseStatus,
        bool $isAssigned,
        bool $assessmentComplete,
        bool $investigationExists,
        ?string $investigationStatus,
        int $currentStageActivityCount,
        bool $recommendationExists,
        bool $isLead,
    ): string {
        if (! $isAssigned) {
            return 'read_only_no_assignment';
        }

        return match (true) {
            $caseStatus === CaseStatusEnum::Forwarded->value => 'start_assessment',
            $caseStatus === CaseStatusEnum::Assessment->value && ! $assessmentComplete => ApiErrorCode::CaseAssessmentRequired,
            $caseStatus === CaseStatusEnum::Assessment->value => 'assessment_completed',
            $caseStatus === CaseStatusEnum::Investigation->value && ! $investigationExists && ! $isLead => 'investigation_lead_required',
            $caseStatus === CaseStatusEnum::Investigation->value && ! $investigationExists => 'create_investigation',
            $caseStatus === CaseStatusEnum::Investigation->value && $investigationStatus !== InvestigationStatusEnum::Completed->value && $currentStageActivityCount === 0 => ApiErrorCode::InvestigationStageActivityRequired,
            $caseStatus === CaseStatusEnum::Investigation->value && $investigationStatus !== InvestigationStatusEnum::Completed->value => 'current_stage_activity_completed',
            $caseStatus === CaseStatusEnum::Investigation->value => 'move_case_after_investigation',
            $caseStatus === CaseStatusEnum::Recommendation->value && ! $recommendationExists => 'create_recommendation',
            default => 'follow_available_case_action',
        };
    }
}
