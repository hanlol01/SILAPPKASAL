export const INVESTIGATION_ACTIVITY_TYPES = [
  "case_review",
  "document_review",
  "timeline_review",
  "victim_interview",
  "witness_interview",
  "respondent_interview",
  "evidence_analysis",
  "report_drafting",
] as const;

export const INVESTIGATION_ACTIVITY_TYPES_BY_STAGE: Record<string, readonly (typeof INVESTIGATION_ACTIVITY_TYPES)[number][]> = {
  planning: ["case_review", "document_review", "timeline_review"],
  evidence_collection: ["document_review", "timeline_review"],
  victim_interview: ["victim_interview"],
  witness_interview: ["witness_interview"],
  respondent_interview: ["respondent_interview"],
  evidence_analysis: ["document_review", "timeline_review", "evidence_analysis"],
  report_drafting: ["report_drafting"],
};

export const DECISION_OUTCOMES = [
  "accepted",
  "partially_accepted",
  "rejected",
  "deferred",
] as const;

export const EVIDENCE_CLASSIFICATIONS = [
  "internal",
  "confidential",
  "restricted",
] as const;

export const EVIDENCE_STATUSES = [
  "registered",
  "under_review",
  "verified",
  "rejected",
  "archived",
] as const;

/**
 * Mirrors the backend evidence lifecycle transitions so the status dialog
 * only offers choices the system will accept. The backend remains the
 * authority; invalid submissions are still rejected server-side.
 */
export const EVIDENCE_STATUS_TRANSITIONS: Record<string, readonly string[]> = {
  registered: ["under_review", "archived"],
  under_review: ["verified", "rejected", "archived"],
  verified: ["archived"],
  rejected: ["archived"],
  archived: [],
};

export function labelOption(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
