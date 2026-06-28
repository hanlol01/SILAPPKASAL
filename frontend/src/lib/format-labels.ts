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
  | "registrationStatus"
  | "reportCategory"
  | "priorityLevel"
  | "riskLevel"
  | "recoveryType"
  | "evidenceType";

type LabelValue = unknown;

function readableValue(value: LabelValue, preferredKeys: string[]) {
  if (value === null || value === undefined || value === "") return null;
  if (typeof value === "string") return value;
  if (typeof value === "number" || typeof value === "boolean" || typeof value === "bigint") return String(value);

  if (typeof value === "object") {
    const record = value as Record<string, unknown>;

    for (const key of preferredKeys) {
      const item = record[key];
      if (typeof item === "string" && item.trim()) return item;
      if (typeof item === "number" || typeof item === "boolean" || typeof item === "bigint") return String(item);
    }

    try {
      const serialized = JSON.stringify(value);
      return serialized && serialized !== "{}" ? serialized : null;
    } catch {
      return null;
    }
  }

  return String(value);
}

function labelKey(value: LabelValue) {
  return readableValue(value, ["code", "name", "label", "title", "value", "key"]);
}

function fallbackSource(value: LabelValue) {
  return readableValue(value, ["name", "label", "title", "code", "value", "key"]);
}

function fallbackLabel(value: LabelValue) {
  const fallback = fallbackSource(value);
  if (!fallback) return "-";

  return fallback
    .replace(/[-_]/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function translatedLabel(t: TFunction, namespace: LabelNamespace, value: LabelValue) {
  const key = labelKey(value);

  if (!key || key === "-") {
    return "-";
  }

  return t(`dashboard:enum.${namespace}.${key}`, {
    defaultValue: fallbackLabel(value),
  });
}

export function formatRoleLabel(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "roles", value);
}

export function formatReportStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "reportStatus", value);
}

export function formatReportType(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "reportType", value);
}

export function formatCaseStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "caseStatus", value);
}

export function formatInvestigationStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "investigationStatus", value);
}

export function formatRecommendationStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "recommendationStatus", value);
}

export function formatDecisionStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "decisionStatus", value);
}

export function formatDecisionOutcome(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "decisionOutcome", value);
}

export function formatRecoveryStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "recoveryStatus", value);
}

export function formatEvidenceStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "evidenceStatus", value);
}

export function formatEvidenceClassification(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "evidenceClassification", value);
}

export function formatCampusType(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "campusType", value);
}

export function formatDegreeLevel(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "degreeLevel", value);
}

export function formatRegistrationStatus(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "registrationStatus", value);
}

export function formatReportCategory(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "reportCategory", value);
}

export function formatPriorityLevel(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "priorityLevel", value);
}

export function formatRiskLevel(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "riskLevel", value);
}

export function formatRecoveryType(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "recoveryType", value);
}

export function formatEvidenceType(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "evidenceType", value);
}

export function formatLocationType(t: TFunction, value: LabelValue) {
  const key = labelKey(value);

  if (!key || key === "-") {
    return "-";
  }

  return t(`portal:locationTypes.${key}`, {
    defaultValue: fallbackLabel(value),
  });
}

export function formatGenericLabel(value: LabelValue) {
  return fallbackLabel(value);
}
