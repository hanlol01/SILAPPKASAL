<?php

namespace App\Support;

final class ApiErrorCode
{
    public const ValidationFailed = 'validation_failed';

    public const Unauthenticated = 'unauthenticated';

    public const Forbidden = 'forbidden';

    public const TooManyRequests = 'too_many_requests';

    public const InvalidCredentials = 'invalid_credentials';

    public const AccountInactive = 'account_inactive';

    public const CurrentPasswordIncorrect = 'current_password_incorrect';

    public const RegistrationDuplicateActive = 'registration_duplicate_active';

    public const RegistrationDuplicatePending = 'registration_duplicate_pending';

    public const RegistrationInvalidCredentials = 'registration_invalid_credentials';

    public const RegistrationPasswordUnavailable = 'registration_password_unavailable';

    public const RegistrationNotPending = 'registration_not_pending';

    public const RegistrationNumberUnavailable = 'registration_number_unavailable';

    public const TrackingNotFound = 'tracking_not_found';

    public const PortalReportNotFound = 'portal_report_not_found';

    public const ReportCancellationFeatureDisabled = 'report_cancellation_feature_disabled';

    public const ReportCancellationConflict = 'report_cancellation_conflict';

    public const ReportWithdrawalConflict = 'report_withdrawal_conflict';

    public const ReportWithdrawalDocumentInvalid = 'report_withdrawal_document_invalid';

    public const ReportWithdrawalStorageFailed = 'report_withdrawal_storage_failed';

    public const ReportWithdrawalNotFound = 'report_withdrawal_not_found';

    public const AuditExportTooManyRows = 'audit_export.too_many_rows';

    public const CaseAssessmentRequired = 'case_assessment_required';

    public const CaseOperationallyTerminal = 'case_operationally_terminal';

    public const CaseAssignmentStale = 'case_assignment_stale';

    public const CaseAssignmentUnavailable = 'case_assignment_unavailable';

    public const CaseAssignmentUnchanged = 'case_assignment_unchanged';

    public const CaseAssignmentReadOnly = 'case_assignment_read_only';

    public const WithdrawalPendingReview = 'withdrawal_pending_review';

    public const DecisionNumberSequenceExhausted = 'decision_number_sequence_exhausted';

    public const DecisionNumberConflict = 'decision_number_conflict';

    public const CaseInvestigationCompletionRequired = 'case_investigation_completion_required';

    public const InvestigationStageActivityRequired = 'investigation_stage_activity_required';

    public const InvestigationActivityStageIncompatible = 'investigation_activity_stage_incompatible';

    public const ContentArchived = 'content_archived';

    public const ContentActiveAuthoringVersion = 'content_active_authoring_version';

    public const ContentStaleVersion = 'content_stale_version';

    public const ContentStaleReview = 'content_stale_review';

    public const ContentInvalidLifecycleTransition = 'content_invalid_lifecycle_transition';

    public const ContentFeaturedStale = 'content_featured_stale';

    public const ContentFeaturedConflict = 'content_featured_conflict';

    public const ContentAttachmentDeletionFailed = 'content_attachment_deletion_failed';

    public const ContentCategoryInUse = 'content_category_in_use';

    public const RecoveryMonitoringRequired = 'recovery_monitoring_required';

    public const CaseRecoveryCompletionRequired = 'case_recovery_completion_required';

    public const RecoveryDiscontinuationReasonRequired = 'recovery_discontinuation_reason_required';

    public const CaseGenericClosureForbidden = 'case_generic_closure_forbidden';

    public const CaseClosureStageInvalid = 'case_closure_stage_invalid';

    public const CaseClosureRecoveryRequired = 'case_closure_recovery_required';

    public const CaseClosureSummaryRequired = 'case_closure_summary_required';

    public const CaseClosureMonitoringRequired = 'case_closure_monitoring_required';

    public const FinalSummaryImmutable = 'final_summary_immutable';

    public const FinalSummaryPublicationRequired = 'final_summary_publication_required';

    public const FinalOutcomeIncompatible = 'final_outcome_incompatible';

    public const FinalSummaryAnonymousIdentityDetected = 'final_summary_anonymous_identity_detected';

    private function __construct() {}
}
