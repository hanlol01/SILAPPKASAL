export const INVESTIGATION_ACTIVITY_TYPES = [
  "case_review",
  "document_review",
  "timeline_review",
  "victim_interview",
  "witness_interview",
  "respondent_interview",
] as const;

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

export function labelOption(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
