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

    case ReporterSelfServiceProfileUpdated = 'reporter_self_service.profile_updated';
    case ReporterSelfServicePasswordChanged = 'reporter_self_service.password_changed';

    case ReportCreated = 'report.created';
    case ReportForwarded = 'report.forwarded';

    case CaseCreated = 'case.created';
    case CaseAssigned = 'case.assigned';
    case CaseStatusChanged = 'case.status_changed';

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

    case SecurityAccessDenied = 'security.access_denied';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
