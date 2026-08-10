<?php

namespace App\Enums;

enum AuditAction: string
{
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthLogout = 'auth.logout';

    case ReporterRegistrationSubmitted = 'reporter_registration.submitted';
    case ReporterRegistrationApproved = 'reporter_registration.approved';
    case ReporterRegistrationRejected = 'reporter_registration.rejected';
    case ReporterRegistrationCorrected = 'reporter_registration.corrected';

    case ReporterSelfServiceProfileUpdated = 'reporter_self_service.profile_updated';
    case ReporterSelfServicePasswordChanged = 'reporter_self_service.password_changed';

    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserRoleChanged = 'user.role_changed';
    case UserReporterCreated = 'user.reporter_created';
    case UserStaffCreated = 'user.staff_created';
    case UserStaffUpdated = 'user.staff_updated';
    case UserPasswordReset = 'user.password_reset';

    case CampusUniversityCreated = 'university.created';
    case CampusUniversityUpdated = 'university.updated';
    case CampusUniversityActivated = 'university.activated';
    case CampusUniversityDeactivated = 'university.deactivated';
    case CampusFacultyCreated = 'faculty.created';
    case CampusFacultyUpdated = 'faculty.updated';
    case CampusFacultyActivated = 'faculty.activated';
    case CampusFacultyDeactivated = 'faculty.deactivated';
    case CampusStudyProgramCreated = 'study_program.created';
    case CampusStudyProgramUpdated = 'study_program.updated';
    case CampusStudyProgramActivated = 'study_program.activated';
    case CampusStudyProgramDeactivated = 'study_program.deactivated';

    case ReportCreated = 'report.created';
    case ReportForwarded = 'report.forwarded';
    case ReportDirectCancellationCompleted = 'report.direct_cancellation.completed';
    case ReportWithdrawalCreated = 'report.withdrawal.created';
    case ReportWithdrawalDraftDocumentPrepared = 'report.withdrawal.draft_document.prepared';
    case ReportWithdrawalDraftDocumentViewed = 'report.withdrawal.draft_document.viewed';
    case ReportWithdrawalDraftDocumentDownloaded = 'report.withdrawal.draft_document.downloaded';
    case ReportWithdrawalSignedDocumentUploaded = 'report.withdrawal.signed_document.uploaded';
    case ReportWithdrawalSignedDocumentDownloaded = 'report.withdrawal.signed_document.downloaded';
    case ReportWithdrawalSubmitted = 'report.withdrawal.submitted';
    case ReportWithdrawalCancelled = 'report.withdrawal.cancelled';
    case ReportWithdrawalReviewViewed = 'report.withdrawal.review.viewed';
    case ReportWithdrawalSignedDocumentReviewed = 'report.withdrawal.signed_document.reviewed';
    case ReportWithdrawalApproved = 'report.withdrawal.approved';
    case ReportWithdrawalRejected = 'report.withdrawal.rejected';
    case ReportWithdrawalResubmitted = 'report.withdrawal.resubmitted';
    case ReportMarkedWithdrawn = 'report.marked_withdrawn';

    case CaseCreated = 'case.created';
    case CaseAssigned = 'case.assigned';
    case CaseReassigned = 'case.reassigned';
    case CaseSelfAssigned = 'case.self_assigned';
    case CaseStatusChanged = 'case.status_changed';
    case CaseAssessmentRecorded = 'case.assessment_recorded';
    case CaseFinalSummaryCreated = 'case.final_summary_created';
    case CaseFinalSummaryUpdated = 'case.final_summary_updated';
    case CaseFinalSummaryPublished = 'case.final_summary_published';
    case CaseMinuteCreated = 'case_minutes.created';
    case CaseMinuteUpdated = 'case_minutes.updated';
    case CaseMinuteFinalized = 'case_minutes.finalized';
    case CaseMinuteRevisionCreated = 'case_minutes.revision_created';
    case CaseMinuteSuperseded = 'case_minutes.superseded';
    case CaseClosed = 'case.closed';
    case CaseClosureDocumentIssued = 'case_closure_document.issued';
    case CaseClosureDocumentDownloaded = 'case_closure_document.downloaded';
    case CaseClosureDocumentPreviewed = 'case_closure_document.previewed';
    case CaseMarkedWithdrawn = 'case.marked_withdrawn';

    case InvestigationCreated = 'investigation.created';
    case InvestigationActivityCreated = 'investigation.activity_created';
    case InvestigationStatusChanged = 'investigation.status_changed';

    case RecommendationCreated = 'recommendation.created';
    case RecommendationUpdated = 'recommendation.updated';
    case RecommendationStatusChanged = 'recommendation.status_changed';
    case RecommendationSubmitted = 'recommendation.submitted';
    case RecommendationReturnedForRevision = 'recommendation.returned_for_revision';
    case RecommendationApproved = 'recommendation.approved';

    case DecisionCreated = 'decision.created';
    case DecisionUpdated = 'decision.updated';
    case DecisionStatusChanged = 'decision.status_changed';

    case RecoveryCreated = 'recovery.created';
    case RecoveryUpdated = 'recovery.updated';
    case RecoveryStatusChanged = 'recovery.status_changed';
    case RecoveryDiscontinued = 'recovery.discontinued';
    case RecoveryMonitoringCreated = 'recovery.monitoring_created';

    case EvidenceMetadataCreated = 'evidence.metadata_created';
    case EvidenceMetadataUpdated = 'evidence.metadata_updated';
    case EvidenceStatusChanged = 'evidence.status_changed';
    case EvidenceFileUploaded = 'evidence.file_uploaded';
    case EvidenceFileDownloaded = 'evidence.file_downloaded';
    case EvidenceFilePreviewed = 'evidence.file_previewed';
    case EvidenceFileDownloadedByOversight = 'evidence.file_downloaded_by_oversight';
    case EvidenceFilePreviewedByOversight = 'evidence.file_previewed_by_oversight';

    case ReporterEvidenceUploaded = 'reporter_evidence.uploaded';
    case ReporterEvidenceDownloadedByReporter = 'reporter_evidence.downloaded_by_reporter';
    case ReporterEvidenceDownloadedBySatgas = 'reporter_evidence.downloaded_by_satgas';
    case ReporterEvidencePreviewedByReporter = 'reporter_evidence.previewed_by_reporter';
    case ReporterEvidencePreviewedBySatgas = 'reporter_evidence.previewed_by_satgas';
    case ReporterEvidenceDownloadedByOversight = 'reporter_evidence.downloaded_by_oversight';
    case ReporterEvidencePreviewedByOversight = 'reporter_evidence.previewed_by_oversight';

    case ContentItemCreated = 'content.item_created';
    case ContentVersionCreated = 'content.version_created';
    case ContentDraftUpdated = 'content.draft_updated';
    case ContentSubmitted = 'content.submitted';
    case ContentReviewStarted = 'content.review_started';
    case ContentRevisionRequested = 'content.revision_requested';
    case ContentRejected = 'content.rejected';
    case ContentApproved = 'content.approved';
    case ContentPublished = 'content.published';
    case ContentDirectGlobalPublished = 'content.direct_global_published';
    case ContentArchived = 'content.archived';
    case ContentAttachmentUploaded = 'content.attachment_uploaded';
    case ContentAttachmentRemoved = 'content.attachment_removed';
    case ContentAttachmentDownloadAuthorized = 'content.attachment_download_authorized';
    case ContentAttachmentDownloaded = 'content.attachment_downloaded';
    case ContentFeaturedPlacementChanged = 'content.featured_placement_changed';

    case ContentCategoryCreated = 'content.category_created';

    case ContentCategoryDeactivated = 'content.category_deactivated';

    case BreakGlassRequested = 'break_glass.request';
    case BreakGlassApproved = 'break_glass.approve';
    case BreakGlassDenied = 'break_glass.deny';
    case BreakGlassIdentityViewed = 'break_glass.view_identity';
    case BreakGlassRevoked = 'break_glass.revoke';
    case BreakGlassExpired = 'break_glass.expire';

    case SecurityAccessDenied = 'security.access_denied';

    case AuditPrivacyScrub = 'audit.privacy_scrub';
    case AuditExport = 'audit.export';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
