import type { FieldValues, Path, UseFormReturn } from "react-hook-form";
import { ApiError } from "@/lib/api-client";
import i18n from "@/i18n";

const API_ERROR_KEYS: Record<string, string> = {
  validation_failed: "common:apiErrors.validationFailed",
  unauthenticated: "common:apiErrors.unauthenticated",
  forbidden: "common:apiErrors.forbidden",
  too_many_requests: "common:apiErrors.tooManyRequests",
  invalid_credentials: "common:apiErrors.invalidCredentials",
  account_inactive: "common:apiErrors.accountInactive",
  current_password_incorrect: "common:apiErrors.currentPasswordIncorrect",
  registration_duplicate_active: "common:apiErrors.registrationDuplicateActive",
  registration_duplicate_pending: "common:apiErrors.registrationDuplicatePending",
  registration_invalid_credentials: "common:apiErrors.registrationInvalidCredentials",
  registration_password_unavailable: "common:apiErrors.registrationPasswordUnavailable",
  registration_not_pending: "common:apiErrors.registrationNotPending",
  registration_number_unavailable: "common:apiErrors.registrationNumberUnavailable",
  tracking_not_found: "common:apiErrors.trackingNotFound",
  portal_report_not_found: "common:apiErrors.portalReportNotFound",
  case_assessment_required: "common:apiErrors.caseAssessmentRequired",
  case_investigation_completion_required: "common:apiErrors.caseInvestigationCompletionRequired",
  investigation_stage_activity_required: "common:apiErrors.investigationStageActivityRequired",
  "audit_export.too_many_rows": "common:apiErrors.auditExportTooManyRows",
};

export function apiErrorMessage(
  error: unknown,
  fallback = i18n.t("common:apiErrors.fallback"),
) {
  if (!(error instanceof ApiError) || !error.errorCode) return fallback;

  const key = API_ERROR_KEYS[error.errorCode];
  return key ? i18n.t(key) : fallback;
}

export function applyLaravelErrors<T extends FieldValues>(form: UseFormReturn<T>, error: unknown) {
  const errors = laravelFieldErrors(error);

  Object.entries(errors).forEach(([name, message]) => {
    form.setError(name as Path<T>, {
      type: "server",
      message,
    });
  });
}

export function laravelFieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || !error.errors) return {};

  const safeFallback = apiErrorMessage(error);
  const mayUseLocalizedValidationMessage = error.errorCode === "validation_failed";

  return Object.fromEntries(
    Object.entries(error.errors).map(([name, messages]) => [
      name,
      mayUseLocalizedValidationMessage ? (messages[0] ?? safeFallback) : safeFallback,
    ]),
  );
}
