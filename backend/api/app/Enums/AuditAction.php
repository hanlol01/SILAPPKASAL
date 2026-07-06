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

    case CaseCreated = 'case.created';
    case CaseAssigned = 'case.assigned';
    case CaseStatusChanged = 'case.status_changed';
    case CaseAssessmentRecorded = 'case.assessment_recorded';

    case InvestigationCreated = 'investigation.created';
    case InvestigationActivityCreated = 'investigation.activity_created';
    case InvestigationStatusChanged = 'investigation.status_changed';

    case RecommendationCreated = 'recommendation.created';
    case RecommendationUpdated = 'recommendation.updated';
    case RecommendationStatusChanged = 'recommendation.status_changed';

    case DecisionCreated = 'decision.created';
    case DecisionUpdated = 'decision.updated';
    case DecisionStatusChanged = 'decision.status_changed';

    case RecoveryCreated = 'recovery.created';
    case RecoveryUpdated = 'recovery.updated';
    case RecoveryStatusChanged = 'recovery.status_changed';
    case RecoveryMonitoringCreated = 'recovery.monitoring_created';

    case EvidenceMetadataCreated = 'evidence.metadata_created';
    case EvidenceMetadataUpdated = 'evidence.metadata_updated';
    case EvidenceStatusChanged = 'evidence.status_changed';

    case BreakGlassRequested = 'break_glass.request';
    case BreakGlassApproved = 'break_glass.approve';
    case BreakGlassDenied = 'break_glass.deny';
    case BreakGlassIdentityViewed = 'break_glass.view_identity';

    case SecurityAccessDenied = 'security.access_denied';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
