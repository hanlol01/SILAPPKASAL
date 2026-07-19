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
  | "evidenceCustodyEvent"
  | "campusType"
  | "degreeLevel"
  | "registrationStatus"
  | "reportCategory"
  | "priorityLevel"
  | "riskLevel"
  | "recoveryType"
  | "evidenceType";

type LabelValue = unknown;

const LABEL_ALIASES: Partial<Record<LabelNamespace, Record<string, string>>> = {
  reportStatus: {
    "CSTS-01": "submitted",
    "CSTS-02": "under_review",
    "CSTS-03": "need_info",
    "CSTS-04": "rejected",
    "CSTS-05": "forwarded",
  },
  caseStatus: {
    "CSTS-01": "submitted",
    "CSTS-02": "under_review",
    "CSTS-03": "need_info",
    "CSTS-04": "rejected",
    "CSTS-05": "forwarded",
    "CSTS-06": "assessment",
    "CSTS-07": "investigation",
    "CSTS-08": "mediation",
    "CSTS-09": "recommendation",
    "CSTS-10": "decision",
    "CSTS-11": "decided",
    "CSTS-12": "recovery",
    "CSTS-13": "monitoring",
    "CSTS-14": "closed",
    "CSTS-15": "escalated",
  },
  investigationStatus: {
    "INVS-01": "planning",
    "INVS-02": "evidence_collection",
    "INVS-03": "victim_interview",
    "INVS-04": "witness_interview",
    "INVS-05": "respondent_interview",
    "INVS-06": "evidence_analysis",
    "INVS-07": "report_drafting",
    "INVS-08": "completed",
  },
  recommendationStatus: {
    "RECS-01": "drafting",
    "RECS-02": "internal_review",
    "RECS-03": "submitted_for_review",
    "RECS-04": "accepted",
    "RECS-05": "partially_accepted",
    "RECS-06": "rejected",
    "RECS-07": "revised",
  },
  decisionStatus: {
    "DECS-01": "draft",
    "DECS-02": "recorded",
    "DECS-03": "finalized",
  },
  recoveryStatus: {
    "RCVS-01": "planned",
    "RCVS-02": "ongoing",
    "RCVS-03": "completed",
    "RCVS-04": "discontinued",
  },
};

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
  const rawKey = labelKey(value);
  const key = rawKey ? (LABEL_ALIASES[namespace]?.[rawKey] ?? rawKey) : rawKey;

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

export function formatInvestigationActivityType(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "investigationActivityType", value);
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

export function formatEvidenceCustodyEvent(t: TFunction, value: LabelValue) {
  return translatedLabel(t, "evidenceCustodyEvent", value);
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

export function formatRespondentCampusStatus(t: TFunction, value: LabelValue) {
  const key = labelKey(value);

  if (!key || key === "-") {
    return "-";
  }

  return t(`portal:respondentCampusStatuses.${key}`, {
    defaultValue: fallbackLabel(value),
  });
}

export function formatRespondentRelation(t: TFunction, value: LabelValue) {
  const key = labelKey(value);

  if (!key || key === "-") {
    return "-";
  }

  return t(`portal:respondentRelations.${key}`, {
    defaultValue: fallbackLabel(value),
  });
}

export function formatGenericLabel(value: LabelValue) {
  return fallbackLabel(value);
}
