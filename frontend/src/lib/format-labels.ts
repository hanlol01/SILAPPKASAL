import type { TFunction } from "i18next";

type LabelNamespace =
  | "roles"
  | "reportStatus"
  | "reportType"
  | "caseStatus"
  | "investigationStatus"
  | "recommendationStatus"
  | "decisionStatus"
  | "decisionOutcome"
  | "recoveryStatus"
  | "evidenceStatus"
  | "evidenceClassification"
  | "campusType"
  | "degreeLevel"
  | "registrationStatus";

function fallbackLabel(value: string | null | undefined) {
  if (!value) return "-";

  return value
    .replace(/[-_]/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function translatedLabel(t: TFunction, namespace: LabelNamespace, value: string | null | undefined) {
  if (!value || value === "-") {
    return "-";
  }

  return t(`dashboard:enum.${namespace}.${value}`, {
    defaultValue: fallbackLabel(value),
  });
}

export function formatRoleLabel(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "roles", value);
}

export function formatReportStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "reportStatus", value);
}

export function formatReportType(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "reportType", value);
}

export function formatCaseStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "caseStatus", value);
}

export function formatInvestigationStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "investigationStatus", value);
}

export function formatRecommendationStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "recommendationStatus", value);
}

export function formatDecisionStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "decisionStatus", value);
}

export function formatDecisionOutcome(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "decisionOutcome", value);
}

export function formatRecoveryStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "recoveryStatus", value);
}

export function formatEvidenceStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "evidenceStatus", value);
}

export function formatEvidenceClassification(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "evidenceClassification", value);
}

export function formatCampusType(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "campusType", value);
}

export function formatDegreeLevel(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "degreeLevel", value);
}

export function formatRegistrationStatus(t: TFunction, value: string | null | undefined) {
  return translatedLabel(t, "registrationStatus", value);
}

export function formatLocationType(t: TFunction, value: string | null | undefined) {
  if (!value || value === "-") {
    return "-";
  }

  return t(`portal:locationTypes.${value}`, {
    defaultValue: fallbackLabel(value),
  });
}

export function formatGenericLabel(value: string | null | undefined) {
  return fallbackLabel(value);
}
