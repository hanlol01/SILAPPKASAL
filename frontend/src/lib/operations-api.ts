import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type {
  CaseRecord,
  CaseStatusPayload,
  Decision,
  DecisionUpdatePayload,
  EvidenceCustodyEvent,
  EvidenceMetadata,
  EvidenceStatusPayload,
  EvidenceUpdatePayload,
  Investigation,
  InvestigationActivity,
  InvestigationActivityPayload,
  PaginatedData,
  Recommendation,
  RecommendationUpdatePayload,
  Recovery,
  RecoveryMonitoring,
  RecoveryMonitoringPayload,
  ReportSummary,
} from "@/lib/operations-types";

type QueryValue = string | number | boolean | undefined;

export const operationsQueryKeys = {
  reports: (query?: Record<string, QueryValue>) => ["operations", "reports", query] as const,
  report: (id: string | number) => ["operations", "report", id] as const,
  cases: (query?: Record<string, QueryValue>) => ["operations", "cases", query] as const,
  case: (id: string | number) => ["operations", "case", id] as const,
  investigations: (caseId: string | number) => ["operations", "case", caseId, "investigations"] as const,
  investigation: (id: string | number) => ["operations", "investigation", id] as const,
  recommendations: (caseId: string | number) => ["operations", "case", caseId, "recommendations"] as const,
  recommendation: (id: string | number) => ["operations", "recommendation", id] as const,
  decisions: (recommendationId: string | number) =>
    ["operations", "recommendation", recommendationId, "decisions"] as const,
  decision: (id: string | number) => ["operations", "decision", id] as const,
  recoveries: (decisionId: string | number) => ["operations", "decision", decisionId, "recoveries"] as const,
  recovery: (id: string | number) => ["operations", "recovery", id] as const,
  recoveryMonitoring: (id: string | number) => ["operations", "recovery", id, "monitoring"] as const,
  evidences: (investigationId: string | number) =>
    ["operations", "investigation", investigationId, "evidences"] as const,
  evidence: (id: string | number) => ["operations", "evidence", id] as const,
  evidenceCustody: (id: string | number) => ["operations", "evidence", id, "custody"] as const,
};

export async function getReports(query?: Record<string, QueryValue>): Promise<PaginatedData<ReportSummary>> {
  const envelope = await apiRequestEnvelope<ReportSummary[]>("/reports", { query });
  return { data: envelope.data, meta: envelope.meta ?? emptyMeta(envelope.data.length) };
}

export function getReport(id: string | number) {
  return apiRequest<ReportSummary>(`/reports/${id}`);
}

export async function getCases(query?: Record<string, QueryValue>): Promise<PaginatedData<CaseRecord>> {
  const envelope = await apiRequestEnvelope<CaseRecord[]>("/cases", { query });
  return { data: envelope.data, meta: envelope.meta ?? emptyMeta(envelope.data.length) };
}

export function getCase(id: string | number) {
  return apiRequest<CaseRecord>(`/cases/${id}`);
}

export function getCaseInvestigations(caseId: string | number) {
  return apiRequest<Investigation[]>(`/cases/${caseId}/investigations`);
}

export function getInvestigation(id: string | number) {
  return apiRequest<Investigation>(`/investigations/${id}`);
}

export function getCaseRecommendations(caseId: string | number) {
  return apiRequest<Recommendation[]>(`/cases/${caseId}/recommendations`);
}

export function getRecommendation(id: string | number) {
  return apiRequest<Recommendation>(`/recommendations/${id}`);
}

export function getRecommendationDecisions(recommendationId: string | number) {
  return apiRequest<Decision[]>(`/recommendations/${recommendationId}/decisions`);
}

export function getDecision(id: string | number) {
  return apiRequest<Decision>(`/decisions/${id}`);
}

export function getDecisionRecoveries(decisionId: string | number) {
  return apiRequest<Recovery[]>(`/decisions/${decisionId}/recoveries`);
}

export function getRecovery(id: string | number) {
  return apiRequest<Recovery>(`/recoveries/${id}`);
}

export function getRecoveryMonitoring(id: string | number) {
  return apiRequest<RecoveryMonitoring[]>(`/recoveries/${id}/monitoring`);
}

export function getInvestigationEvidences(investigationId: string | number) {
  return apiRequest<EvidenceMetadata[]>(`/investigations/${investigationId}/evidences`);
}

export function getEvidence(id: string | number) {
  return apiRequest<EvidenceMetadata>(`/evidences/${id}`);
}

export function getEvidenceCustody(id: string | number) {
  return apiRequest<EvidenceCustodyEvent[]>(`/evidences/${id}/custody`);
}

export function updateCaseStatus(id: string | number, payload: CaseStatusPayload) {
  return apiRequest<CaseRecord>(`/cases/${id}/status`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function createInvestigationActivity(
  id: string | number,
  payload: InvestigationActivityPayload,
) {
  return apiRequest<InvestigationActivity>(`/investigations/${id}/activities`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateRecommendation(id: string | number, payload: RecommendationUpdatePayload) {
  return apiRequest<Recommendation>(`/recommendations/${id}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function updateDecision(id: string | number, payload: DecisionUpdatePayload) {
  return apiRequest<Decision>(`/decisions/${id}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function createRecoveryMonitoring(id: string | number, payload: RecoveryMonitoringPayload) {
  return apiRequest<RecoveryMonitoring>(`/recoveries/${id}/monitoring`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateEvidenceMetadata(id: string | number, payload: EvidenceUpdatePayload) {
  return apiRequest<EvidenceMetadata>(`/evidences/${id}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function updateEvidenceStatus(id: string | number, payload: EvidenceStatusPayload) {
  return apiRequest<EvidenceMetadata>(`/evidences/${id}/status`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

function emptyMeta(total: number) {
  return {
    current_page: 1,
    per_page: total,
    total,
    last_page: 1,
  };
}
