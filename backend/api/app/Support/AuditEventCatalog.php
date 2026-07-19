<?php

namespace App\Support;

use App\Enums\AuditAction;
use InvalidArgumentException;

final class AuditEventCatalog
{
    /**
     * @return array{metadata: list<string>, deltas: list<string>}
     */
    public function definition(AuditAction|string $action): array
    {
        $event = $action instanceof AuditAction ? $action : AuditAction::tryFrom($action);

        if (! $event) {
            throw new InvalidArgumentException('Unknown audit action.');
        }

        return match ($event) {
            AuditAction::AuthLogin,
            AuditAction::AuthLogout => $this->fields(
                ['authentication_method'],
            ),
            AuditAction::AuthLoginFailed => $this->fields([
                'fingerprint_version',
                'identifier_fingerprint',
                'network_fingerprint',
                'reason_code',
            ]),

            AuditAction::ReporterRegistrationSubmitted,
            AuditAction::ReporterRegistrationApproved,
            AuditAction::ReporterRegistrationRejected,
            AuditAction::ReporterRegistrationCorrected => $this->fields(
                ['registration_number', 'status', 'nim_changed', 'has_rejection_reason'],
                ['status'],
            ),

            AuditAction::ReporterSelfServiceProfileUpdated => $this->fields([], ['name_changed', 'phone_changed']),
            AuditAction::ReporterSelfServicePasswordChanged => $this->fields(['revoked_other_tokens']),

            AuditAction::UserActivated,
            AuditAction::UserDeactivated => $this->fields([], ['is_active']),
            AuditAction::UserRoleChanged => $this->fields([], ['role_code']),
            AuditAction::UserReporterCreated => $this->fields(['role_code']),
            AuditAction::UserPasswordReset => $this->fields(['temporary_password_generated']),

            AuditAction::CampusUniversityCreated,
            AuditAction::CampusUniversityUpdated,
            AuditAction::CampusUniversityActivated,
            AuditAction::CampusUniversityDeactivated,
            AuditAction::CampusFacultyCreated,
            AuditAction::CampusFacultyUpdated,
            AuditAction::CampusFacultyActivated,
            AuditAction::CampusFacultyDeactivated,
            AuditAction::CampusStudyProgramCreated,
            AuditAction::CampusStudyProgramUpdated,
            AuditAction::CampusStudyProgramActivated,
            AuditAction::CampusStudyProgramDeactivated => $this->fields(
                ['code', 'name'],
                ['code', 'name', 'is_active'],
            ),

            AuditAction::ReportCreated,
            AuditAction::ReportForwarded => $this->fields(
                ['registration_number', 'report_type', 'category_code', 'status'],
                ['status'],
            ),

            AuditAction::CaseCreated,
            AuditAction::CaseAssigned,
            AuditAction::CaseStatusChanged,
            AuditAction::CaseAssessmentRecorded => $this->fields(
                ['case_number', 'registration_number', 'status_code', 'risk_level_code', 'priority_code', 'assignment_count'],
                ['status_code', 'risk_level_code', 'priority_code', 'is_active', 'is_lead'],
            ),

            AuditAction::InvestigationCreated,
            AuditAction::InvestigationActivityCreated,
            AuditAction::InvestigationStatusChanged => $this->fields(
                ['case_number', 'status_code', 'activity_type'],
                ['status_code', 'completed'],
            ),

            AuditAction::RecommendationCreated,
            AuditAction::RecommendationUpdated,
            AuditAction::RecommendationStatusChanged,
            AuditAction::RecommendationSubmitted,
            AuditAction::RecommendationReturnedForRevision,
            AuditAction::RecommendationApproved => $this->fields(
                ['case_number', 'status_code', 'has_revision_note'],
                ['status_code'],
            ),

            AuditAction::DecisionCreated,
            AuditAction::DecisionUpdated,
            AuditAction::DecisionStatusChanged => $this->fields(
                ['case_number', 'decision_number', 'status_code', 'outcome_code'],
                ['status_code', 'outcome_code'],
            ),

            AuditAction::RecoveryCreated,
            AuditAction::RecoveryUpdated,
            AuditAction::RecoveryStatusChanged,
            AuditAction::RecoveryMonitoringCreated => $this->fields(
                ['case_number', 'recovery_type_code', 'status_code', 'has_duration_warning'],
                ['status_code', 'monitoring_date'],
            ),

            AuditAction::EvidenceMetadataCreated,
            AuditAction::EvidenceMetadataUpdated,
            AuditAction::EvidenceStatusChanged,
            AuditAction::EvidenceFileUploaded,
            AuditAction::EvidenceFileDownloaded,
            AuditAction::EvidenceFilePreviewed,
            AuditAction::EvidenceFileDownloadedByOversight,
            AuditAction::EvidenceFilePreviewedByOversight => $this->fields(
                ['case_number', 'evidence_id', 'evidence_type_code', 'classification', 'status_code', 'cross_campus_read'],
                ['status_code', 'classification', 'has_file'],
            ),

            AuditAction::ReporterEvidenceUploaded,
            AuditAction::ReporterEvidenceDownloadedByReporter,
            AuditAction::ReporterEvidenceDownloadedBySatgas,
            AuditAction::ReporterEvidencePreviewedByReporter,
            AuditAction::ReporterEvidencePreviewedBySatgas,
            AuditAction::ReporterEvidenceDownloadedByOversight,
            AuditAction::ReporterEvidencePreviewedByOversight => $this->fields(
                ['registration_number', 'attachment_uuid', 'cross_campus_read'],
            ),

            AuditAction::BreakGlassRequested,
            AuditAction::BreakGlassApproved,
            AuditAction::BreakGlassDenied,
            AuditAction::BreakGlassIdentityViewed => $this->fields(
                ['registration_number', 'reason_category'],
                ['status', 'viewed'],
            ),

            AuditAction::SecurityAccessDenied => $this->fields(
                ['operation_code', 'reason_code'],
            ),
            AuditAction::AuditPrivacyScrub => $this->fields(
                ['scanned_count', 'changed_count', 'failed_count', 'reason_summary', 'dry_run'],
            ),
            AuditAction::AuditExport => $this->fields(
                ['row_count', 'format', 'date_from', 'date_to', 'failure_code'],
            ),
        };
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, bool|float|int|string|null>
     */
    public function sanitizeMetadata(AuditAction|string $action, array $values): array
    {
        return $this->sanitize($values, $this->definition($action)['metadata']);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, bool|float|int|string|null>
     */
    public function sanitizeDeltas(AuditAction|string $action, array $values): array
    {
        return $this->sanitize($values, $this->definition($action)['deltas']);
    }

    /**
     * @param list<string> $metadata
     * @param list<string> $deltas
     * @return array{metadata: list<string>, deltas: list<string>}
     */
    private function fields(array $metadata = [], array $deltas = []): array
    {
        return ['metadata' => $metadata, 'deltas' => $deltas];
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $allowed
     * @return array<string, bool|float|int|string|null>
     */
    private function sanitize(array $values, array $allowed): array
    {
        $safe = [];

        foreach ($allowed as $key) {
            $value = $values[$key] ?? null;

            if (array_key_exists($key, $values) && (is_null($value) || is_scalar($value))) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
