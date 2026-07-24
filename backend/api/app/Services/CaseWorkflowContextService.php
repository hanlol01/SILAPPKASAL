<?php

namespace App\Services;

use App\Enums\CaseFinalOutcome;
use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\RecoveryStatus as RecoveryStatusEnum;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\CaseCampusScope;

class CaseWorkflowContextService
{
    public function __construct(private readonly CaseCampusScope $campusScope) {}

    /**
     * @return array<string, mixed>
     */
    public function forCase(CaseRecord $case, User $actor): array
    {
        $case->loadMissing([
            'status',
            'activeAssignments',
            'investigation.status',
            'recommendation.status',
            'recommendation.decision.status',
            'recommendation.decision.recoveries.status',
            'finalSummary',
        ]);

        $caseStatus = $case->status?->name;
        $investigation = $case->investigation;
        $investigationStatus = $investigation?->status?->name;
        $recommendation = $case->recommendation;
        $recommendationExists = $recommendation !== null;
        $recommendationStatus = $recommendation?->status?->name;
        $decision = $recommendation?->decision;
        $decisionStatus = $decision?->status?->name;
        $recovery = $decision?->recoveries->sortByDesc('id')->first();
        $recoveryStatus = $recovery?->status?->name;
        $monitoringCount = $recovery?->monitorings()->count() ?? 0;
        $finalSummary = $case->finalSummary;
        $finalSummaryExists = $finalSummary !== null;
        $finalSummaryPublished = $finalSummary?->isPublished() ?? false;
        $recoveryStatusEnum = $recoveryStatus ? RecoveryStatusEnum::tryFrom($recoveryStatus) : null;
        $finalOutcome = $finalSummary?->outcome_code;
        $finalOutcomeCompatible = $finalOutcome instanceof CaseFinalOutcome
            && $recoveryStatusEnum instanceof RecoveryStatusEnum
            && $finalOutcome->isCompatibleWithRecovery($recoveryStatusEnum);
        $assessmentComplete = filled($case->risk_level_code) && filled($case->priority_code);
        $isOperationallyTerminal = $case->isOperationallyTerminal();
        $activeAssignment = $case->activeAssignments->firstWhere('satgas_id', $actor->id);
        $isAssigned = $actor->is_active && $actor->hasRole('satgas_ppks') && $activeAssignment !== null;
        $isLead = $isAssigned && (bool) $activeAssignment?->is_lead;
        $canUpdateCaseStatus = $isAssigned && $actor->hasPermission('cases.read.assigned');
        $canInvestigate = $isAssigned && $actor->hasPermission('cases.investigate');
        $canRecommend = $isAssigned && $actor->hasPermission('cases.recommend');
        $isSameCampusAdmin = $actor->hasRole('admin') && $this->campusScope->sameCampus($actor, $case);
        $isOversight = $actor->hasRole('super_admin');
        $isLifecycleControlled = in_array($caseStatus, [
            CaseStatusEnum::Recommendation->value,
            CaseStatusEnum::Decision->value,
        ], true);
        $currentStageActivityCount = $investigation
            ? $investigation->activities()
                ->where('investigation_stage_code', $investigation->status_code)
                ->count()
            : 0;

        $updateCaseStatus = $this->action(
            $canUpdateCaseStatus && ! $isOperationallyTerminal && ! $isLifecycleControlled,
            match (true) {
                ! $isAssigned => 'read_only_no_assignment',
                ! $canUpdateCaseStatus => 'permission_missing',
                $isOperationallyTerminal => 'case_terminal',
                $isLifecycleControlled => 'lifecycle_controlled',
                default => 'action_unavailable',
            },
        );
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
        if ($updateCaseStatus['allowed'] && $caseStatus === CaseStatusEnum::Monitoring->value) {
            $updateCaseStatus = $this->action(false, ApiErrorCode::CaseGenericClosureForbidden);
        }
        if (
            $updateCaseStatus['allowed']
            && $caseStatus === CaseStatusEnum::Recovery->value
            && ($recoveryStatus !== RecoveryStatusEnum::Completed->value || $monitoringCount === 0)
        ) {
            $updateCaseStatus = $this->action(false, ApiErrorCode::CaseRecoveryCompletionRequired);
        }

        $createInvestigation = $this->action(
            $canInvestigate
                && ! $isOperationallyTerminal
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
                && ! $isOperationallyTerminal
                && $caseStatus === CaseStatusEnum::Investigation->value
                && $investigation !== null
                && $investigationStatus !== InvestigationStatusEnum::Completed->value,
            $this->investigationUnavailableReason($isAssigned, $canInvestigate, $isOperationallyTerminal, $caseStatus, $investigation !== null, $investigationStatus),
        );

        $updateInvestigationStatus = $addActivity;
        if ($updateInvestigationStatus['allowed'] && $currentStageActivityCount === 0) {
            $updateInvestigationStatus = $this->action(false, ApiErrorCode::InvestigationStageActivityRequired);
        }

        $canAddEvidence = $isAssigned
            && $actor->hasPermission('evidence.view.case')
            && $actor->hasPermission('evidence.upload')
            && ! $isOperationallyTerminal
            && $caseStatus === CaseStatusEnum::Investigation->value
            && $investigation !== null
            && $investigationStatus !== InvestigationStatusEnum::Completed->value;
        $addEvidence = $this->action(
            $canAddEvidence,
            $this->evidenceUnavailableReason($actor, $isAssigned, $isOperationallyTerminal, $caseStatus, $investigation !== null, $investigationStatus),
        );

        $createRecommendation = $this->action(
            $canRecommend
                && ! $isOperationallyTerminal
                && $caseStatus === CaseStatusEnum::Recommendation->value
                && $investigationStatus === InvestigationStatusEnum::Completed->value
                && ! $recommendationExists,
            ! $isAssigned
                ? 'read_only_no_assignment'
                : (! $canRecommend
                    ? 'permission_missing'
                    : ($recommendationExists
                        ? 'recommendation_exists'
                        : ($investigationStatus !== InvestigationStatusEnum::Completed->value
                            ? ApiErrorCode::CaseInvestigationCompletionRequired
                            : 'case_not_recommendation'))),
        );

        $reviewRecommendation = $this->action(
            $isSameCampusAdmin
                && $actor->hasPermission('cases.review_recommendation')
                && ! $isOperationallyTerminal
                && in_array($recommendationStatus, RecommendationStatusEnum::submittedReviewValues(), true),
            $isOversight ? 'oversight_read_only' : (! $isSameCampusAdmin ? 'campus_access_denied' : 'action_unavailable'),
        );
        $createDecision = $this->action(
            $isSameCampusAdmin
                && $actor->hasPermission('cases.record_decision')
                && ! $isOperationallyTerminal
                && $caseStatus === CaseStatusEnum::Decision->value
                && $recommendationStatus === RecommendationStatusEnum::Accepted->value
                && $decision === null,
            $isOversight ? 'oversight_read_only' : (! $isSameCampusAdmin ? 'campus_access_denied' : 'action_unavailable'),
        );
        $manageRecovery = $this->action(
            $isSameCampusAdmin
                && $actor->hasPermission('cases.monitor')
                && ! $isOperationallyTerminal
                && $caseStatus === CaseStatusEnum::Recovery->value
                && $decisionStatus === DecisionStatusEnum::Finalized->value,
            $isOversight ? 'oversight_read_only' : (! $isSameCampusAdmin ? 'campus_access_denied' : 'action_unavailable'),
        );
        $addMonitoring = $this->action(
            $isAssigned
                && $actor->hasPermission('cases.monitor')
                && ! $isOperationallyTerminal
                && $recoveryStatus === RecoveryStatusEnum::Ongoing->value,
            ! $isAssigned ? 'read_only_no_assignment' : 'recovery_not_ongoing',
        );

        $canManageFinalSummary = $isSameCampusAdmin
            && $actor->hasPermission('cases.monitor')
            && ! $isOperationallyTerminal
            && in_array($caseStatus, [CaseStatusEnum::Recovery->value, CaseStatusEnum::Monitoring->value], true);
        $createFinalSummary = $this->action(
            $canManageFinalSummary && ! $finalSummaryExists,
            $isOversight
                ? 'oversight_read_only'
                : (! $isSameCampusAdmin
                    ? 'campus_access_denied'
                    : ($finalSummaryExists ? 'final_summary_exists' : 'final_summary_stage_unavailable')),
        );
        $updateFinalSummary = $this->action(
            $canManageFinalSummary && $finalSummaryExists && ! $finalSummaryPublished,
            $isOversight
                ? 'oversight_read_only'
                : (! $isSameCampusAdmin
                    ? 'campus_access_denied'
                    : ($finalSummaryPublished ? ApiErrorCode::FinalSummaryImmutable : 'final_summary_missing')),
        );
        $summaryPublicationReady = $finalSummaryExists
            && ! $finalSummaryPublished
            && filled($finalSummary?->outcome_code)
            && filled($finalSummary?->completion_date)
            && filled($finalSummary?->official_statement)
            && filled($finalSummary?->closing_explanation)
            && $finalOutcomeCompatible;
        $publishFinalSummary = $this->action(
            $canManageFinalSummary && $summaryPublicationReady,
            $isOversight
                ? 'oversight_read_only'
                : (! $isSameCampusAdmin
                    ? 'campus_access_denied'
                    : (! $finalSummaryExists
                        ? 'final_summary_missing'
                        : ($finalSummaryPublished
                            ? ApiErrorCode::FinalSummaryImmutable
                            : (! $finalOutcomeCompatible
                                ? ApiErrorCode::FinalOutcomeIncompatible
                                : ApiErrorCode::FinalSummaryPublicationRequired)))),
        );
        $completeRecovery = $this->action(
            $isSameCampusAdmin
                && $actor->hasPermission('cases.monitor')
                && ! $isOperationallyTerminal
                && $recoveryStatus === RecoveryStatusEnum::Ongoing->value
                && $monitoringCount > 0,
            $isOversight
                ? 'oversight_read_only'
                : (! $isSameCampusAdmin
                    ? 'monitoring_satgas_owned'
                    : ($monitoringCount === 0 ? ApiErrorCode::RecoveryMonitoringRequired : 'recovery_not_ongoing')),
        );
        $discontinueRecovery = $this->action(
            $isSameCampusAdmin
                && $actor->hasPermission('cases.monitor')
                && ! $isOperationallyTerminal
                && in_array($recoveryStatus, [RecoveryStatusEnum::Planned->value, RecoveryStatusEnum::Ongoing->value], true),
            $isOversight ? 'oversight_read_only' : (! $isSameCampusAdmin ? 'campus_access_denied' : 'recovery_terminal'),
        );
        $finalizeClosureReason = match (true) {
            ! $isAssigned => 'read_only_no_assignment',
            ! $actor->hasPermission('cases.close') => 'permission_missing',
            ! in_array($recoveryStatus, [RecoveryStatusEnum::Completed->value, RecoveryStatusEnum::Discontinued->value], true) => ApiErrorCode::CaseClosureRecoveryRequired,
            $recoveryStatus === RecoveryStatusEnum::Completed->value && $caseStatus !== CaseStatusEnum::Monitoring->value => ApiErrorCode::CaseClosureStageInvalid,
            $recoveryStatus === RecoveryStatusEnum::Completed->value && $monitoringCount === 0 => ApiErrorCode::CaseClosureMonitoringRequired,
            $recoveryStatus === RecoveryStatusEnum::Discontinued->value && $caseStatus !== CaseStatusEnum::Recovery->value => ApiErrorCode::CaseClosureStageInvalid,
            $recoveryStatus === RecoveryStatusEnum::Discontinued->value && blank($recovery?->discontinuation_reason) => ApiErrorCode::RecoveryDiscontinuationReasonRequired,
            ! $finalSummaryPublished => ApiErrorCode::CaseClosureSummaryRequired,
            ! $finalOutcomeCompatible => ApiErrorCode::FinalOutcomeIncompatible,
            default => 'action_unavailable',
        };
        $finalizeClosure = $this->action(
            ! $isOperationallyTerminal
                && $isAssigned
                && $actor->hasPermission('cases.close')
                && $finalSummaryPublished
                && $finalOutcomeCompatible
                && (
                    ($recoveryStatus === RecoveryStatusEnum::Completed->value && $caseStatus === CaseStatusEnum::Monitoring->value && $monitoringCount > 0)
                    || ($recoveryStatus === RecoveryStatusEnum::Discontinued->value && $caseStatus === CaseStatusEnum::Recovery->value && filled($recovery?->discontinuation_reason))
                ),
            $finalizeClosureReason,
        );

        $primaryTip = $this->m3PrimaryTip(
            $actor,
            $isSameCampusAdmin,
            $isOversight,
            $caseStatus,
            $recommendationStatus,
            $decisionStatus,
            $recoveryStatus,
            $monitoringCount,
        ) ?? $this->primaryTip(
            $caseStatus,
            $isAssigned,
            $assessmentComplete,
            $investigation !== null,
            $investigationStatus,
            $currentStageActivityCount,
            $recommendationExists,
            $isLead,
            $canInvestigate,
            $canRecommend,
        );

        $primaryTip = match (true) {
            $caseStatus === CaseStatusEnum::Withdrawn->value => 'case_withdrawn',
            $caseStatus === CaseStatusEnum::Closed->value && ! $finalSummaryExists => 'legacy_completion',
            $caseStatus === CaseStatusEnum::Closed->value => 'case_closed',
            $recoveryStatus === RecoveryStatusEnum::Discontinued->value && ! $finalSummaryExists && $isSameCampusAdmin => 'create_final_summary',
            $recoveryStatus === RecoveryStatusEnum::Discontinued->value && ! $finalSummaryPublished && $isSameCampusAdmin => 'publish_final_summary',
            $recoveryStatus === RecoveryStatusEnum::Discontinued->value && $finalizeClosure['allowed'] => 'finalize_case_closure',
            $recoveryStatus === RecoveryStatusEnum::Discontinued->value && $isAssigned => 'wait_for_final_summary',
            $caseStatus === CaseStatusEnum::Monitoring->value && ! $finalSummaryExists && $isSameCampusAdmin => 'create_final_summary',
            $caseStatus === CaseStatusEnum::Monitoring->value && ! $finalSummaryPublished && $isSameCampusAdmin => 'publish_final_summary',
            $caseStatus === CaseStatusEnum::Monitoring->value && $finalizeClosure['allowed'] => 'finalize_case_closure',
            $caseStatus === CaseStatusEnum::Monitoring->value && $isAssigned => 'wait_for_final_summary',
            default => $primaryTip,
        };

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
                'recommendation_status' => $recommendationStatus,
                'decision_exists' => $decision !== null,
                'decision_status' => $decisionStatus,
                'recovery_exists' => $recovery !== null,
                'recovery_status' => $recoveryStatus,
                'monitoring_count' => $monitoringCount,
                'same_campus_admin' => $isSameCampusAdmin,
                'oversight_read_only' => $isOversight,
                'sensitive_oversight_enabled' => $this->campusScope->canSensitiveOversight($actor),
                'final_summary_exists' => $finalSummaryExists,
                'final_summary_published' => $finalSummaryPublished,
                'final_outcome_code' => $finalOutcome?->value,
                'final_outcome_compatible' => $finalOutcomeCompatible,
                'finalization_path' => $isOperationallyTerminal && ! $finalSummaryExists
                    ? 'legacy_completion'
                    : ($recoveryStatusEnum && in_array($recoveryStatusEnum, [RecoveryStatusEnum::Completed, RecoveryStatusEnum::Discontinued], true)
                        ? $recoveryStatusEnum->value
                        : null),
            ],
            'actions' => [
                'update_case_status' => $updateCaseStatus,
                'create_investigation' => $createInvestigation,
                'add_activity' => $addActivity,
                'update_investigation_status' => $updateInvestigationStatus,
                'add_evidence' => $addEvidence,
                'create_recommendation' => $createRecommendation,
                'review_recommendation' => $reviewRecommendation,
                'create_decision' => $createDecision,
                'manage_recovery' => $manageRecovery,
                'add_monitoring' => $addMonitoring,
                'complete_recovery' => $completeRecovery,
                'discontinue_recovery' => $discontinueRecovery,
                'create_final_summary' => $createFinalSummary,
                'update_final_summary' => $updateFinalSummary,
                'publish_final_summary' => $publishFinalSummary,
                'finalize_closure' => $finalizeClosure,
            ],
            'primary_tip_code' => $primaryTip,
            'primary_tip_params' => [
                'stage' => $investigationStatus,
            ],
        ];
    }

    private function m3PrimaryTip(
        User $actor,
        bool $isSameCampusAdmin,
        bool $isOversight,
        ?string $caseStatus,
        ?string $recommendationStatus,
        ?string $decisionStatus,
        ?string $recoveryStatus,
        int $monitoringCount,
    ): ?string {
        if ($isOversight) {
            return 'oversight_read_only';
        }

        if ($actor->hasRole('admin') && ! $isSameCampusAdmin) {
            return 'campus_access_denied';
        }

        $isAdmin = $actor->hasRole('admin');
        $isSatgas = $actor->hasRole('satgas_ppks');
        $tip = match (true) {
            in_array($recommendationStatus, RecommendationStatusEnum::submittedReviewValues(), true) && $isSatgas => 'recommendation_waiting_campus_admin',
            $recommendationStatus === RecommendationStatusEnum::Revised->value && $isSatgas => 'recommendation_returned',
            $recommendationStatus === RecommendationStatusEnum::Revised->value && $isAdmin => 'recommendation_waiting_satgas_revision',
            in_array($recommendationStatus, RecommendationStatusEnum::submittedReviewValues(), true) && $isSameCampusAdmin => 'review_recommendation',
            $recommendationStatus === RecommendationStatusEnum::Accepted->value && $decisionStatus === null && $isAdmin => 'recommendation_approved_create_decision',
            $recommendationStatus === RecommendationStatusEnum::Accepted->value && $decisionStatus === null && $isSatgas => 'wait_for_campus_admin_decision',
            $decisionStatus === DecisionStatusEnum::Draft->value && $isAdmin => 'decision_draft',
            $decisionStatus === DecisionStatusEnum::Recorded->value && $isAdmin => 'decision_recorded',
            in_array($decisionStatus, [DecisionStatusEnum::Draft->value, DecisionStatusEnum::Recorded->value], true) && $isSatgas => 'wait_for_campus_admin_decision',
            $decisionStatus === DecisionStatusEnum::Finalized->value && $caseStatus === CaseStatusEnum::Decided->value && $isSatgas => 'advance_case_to_recovery',
            $decisionStatus === DecisionStatusEnum::Finalized->value && $caseStatus === CaseStatusEnum::Decided->value && $isAdmin => 'wait_for_satgas_recovery_stage',
            $caseStatus === CaseStatusEnum::Recovery->value && $recoveryStatus === null && $isAdmin => 'create_recovery',
            $caseStatus === CaseStatusEnum::Recovery->value && $recoveryStatus === null && $isSatgas => 'wait_for_campus_admin_recovery',
            $recoveryStatus === RecoveryStatusEnum::Ongoing->value && $monitoringCount === 0 && $isSatgas => 'recovery_needs_monitoring',
            $recoveryStatus === RecoveryStatusEnum::Ongoing->value && $monitoringCount === 0 && $isAdmin => 'wait_for_satgas_monitoring',
            $recoveryStatus === RecoveryStatusEnum::Ongoing->value && $isAdmin => 'recovery_can_complete',
            $recoveryStatus === RecoveryStatusEnum::Ongoing->value && $isSatgas => 'wait_for_campus_admin_recovery_completion',
            $recoveryStatus === RecoveryStatusEnum::Completed->value && $caseStatus === CaseStatusEnum::Recovery->value && $isSatgas => 'recovery_completed_advance_monitoring',
            $recoveryStatus === RecoveryStatusEnum::Completed->value && $caseStatus === CaseStatusEnum::Recovery->value && $isAdmin => 'wait_for_satgas_monitoring_stage',
            $caseStatus === CaseStatusEnum::Monitoring->value && $isSatgas => 'case_monitoring_close',
            $caseStatus === CaseStatusEnum::Monitoring->value && $isAdmin => 'wait_for_satgas_case_closure',
            $caseStatus === CaseStatusEnum::Closed->value => 'case_closed',
            default => null,
        };

        return $tip ?? ($actor->hasRole('admin') ? 'follow_available_case_action' : null);
    }

    /** @return array{allowed: bool, reason_code: string|null} */
    private function action(bool $allowed, ?string $reasonCode): array
    {
        return ['allowed' => $allowed, 'reason_code' => $allowed ? null : $reasonCode];
    }

    private function investigationUnavailableReason(
        bool $isAssigned,
        bool $canInvestigate,
        bool $isOperationallyTerminal,
        ?string $caseStatus,
        bool $investigationExists,
        ?string $investigationStatus,
    ): string {
        return match (true) {
            ! $isAssigned => 'read_only_no_assignment',
            ! $canInvestigate => 'permission_missing',
            $isOperationallyTerminal => 'case_terminal',
            $caseStatus !== CaseStatusEnum::Investigation->value => 'case_not_investigation',
            ! $investigationExists => 'investigation_missing',
            $investigationStatus === InvestigationStatusEnum::Completed->value => 'investigation_completed',
            default => 'action_unavailable',
        };
    }

    private function evidenceUnavailableReason(
        User $actor,
        bool $isAssigned,
        bool $isOperationallyTerminal,
        ?string $caseStatus,
        bool $investigationExists,
        ?string $investigationStatus,
    ): string {
        return match (true) {
            ! $actor->hasRole('satgas_ppks') => 'not_satgas',
            ! $isAssigned => 'read_only_no_assignment',
            ! $actor->hasPermission('evidence.view.case') || ! $actor->hasPermission('evidence.upload') => 'permission_missing',
            $isOperationallyTerminal => 'case_terminal',
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
        bool $canInvestigate,
        bool $canRecommend,
    ): string {
        if (! $isAssigned) {
            return 'read_only_no_assignment';
        }

        return match (true) {
            $caseStatus === CaseStatusEnum::Forwarded->value => 'start_assessment',
            $caseStatus === CaseStatusEnum::Assessment->value && ! $assessmentComplete => ApiErrorCode::CaseAssessmentRequired,
            $caseStatus === CaseStatusEnum::Assessment->value => 'assessment_completed',
            $caseStatus === CaseStatusEnum::Investigation->value && ! $canInvestigate => 'permission_missing',
            $caseStatus === CaseStatusEnum::Investigation->value && ! $investigationExists && ! $isLead => 'investigation_lead_required',
            $caseStatus === CaseStatusEnum::Investigation->value && ! $investigationExists => 'create_investigation',
            $caseStatus === CaseStatusEnum::Investigation->value && $investigationStatus !== InvestigationStatusEnum::Completed->value && $currentStageActivityCount === 0 => ApiErrorCode::InvestigationStageActivityRequired,
            $caseStatus === CaseStatusEnum::Investigation->value && $investigationStatus !== InvestigationStatusEnum::Completed->value => 'current_stage_activity_completed',
            $caseStatus === CaseStatusEnum::Investigation->value => 'move_case_after_investigation',
            $caseStatus === CaseStatusEnum::Recommendation->value && ! $canRecommend => 'permission_missing',
            $caseStatus === CaseStatusEnum::Recommendation->value && ! $recommendationExists => 'create_recommendation',
            default => 'follow_available_case_action',
        };
    }
}
