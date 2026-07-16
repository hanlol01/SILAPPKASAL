import { apiDownload, apiFetchBlob, apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type {
  CaseRecord,
  CaseStatusPayload,
  Decision,
  DecisionCreatePayload,
  DecisionStatusOptions,
  DecisionStatusPayload,
  DecisionUpdatePayload,
  EvidenceCreatePayload,
  EvidenceCustodyEvent,
  EvidenceMetadata,
  EvidenceStatusPayload,
  EvidenceUpdatePayload,
  ForwardReportToCaseResult,
  Investigation,
  InvestigationActivity,
  InvestigationActivityPayload,
  InvestigationCreatePayload,
  InvestigationStatusOptions,
  InvestigationStatusPayload,
  PaginatedData,
  Recommendation,
  RecommendationCreatePayload,
  RecommendationStatusOptions,
  RecommendationStatusPayload,
  RecommendationUpdatePayload,
  Recovery,
  RecoveryCreatePayload,
  RecoveryMonitoring,
  RecoveryMonitoringPayload,
  RecoveryStatusOptions,
  RecoveryStatusPayload,
  ReporterEvidenceFile,
  ReportSummary,
  SatgasAssignmentPayload,
  UserLookupItem,
} from "@/lib/operations-types";

type QueryValue = string | number | boolean | undefined;

export function normalizeOperationId(id: string | number) {
  return String(id);
}

export const operationsQueryKeys = {
  reportsRoot: () => ["operations", "reports"] as const,
  reports: (query?: Record<string, QueryValue>) => ["operations", "reports", query] as const,
  report: (id: string | number) => ["operations", "report", normalizeOperationId(id)] as const,
  casesRoot: () => ["operations", "cases"] as const,
  cases: (query?: Record<string, QueryValue>) => ["operations", "cases", query] as const,
  case: (id: string | number) => ["operations", "case", normalizeOperationId(id)] as const,
  investigations: (caseId: string | number) =>
    ["operations", "case", normalizeOperationId(caseId), "investigations"] as const,
  investigation: (id: string | number) =>
    ["operations", "investigation", normalizeOperationId(id)] as const,
  investigationStatusOptions: (id: string | number) =>
    ["operations", "investigation", normalizeOperationId(id), "status-options"] as const,
  recommendations: (caseId: string | number) =>
    ["operations", "case", normalizeOperationId(caseId), "recommendations"] as const,
  recommendation: (id: string | number) =>
    ["operations", "recommendation", normalizeOperationId(id)] as const,
  recommendationStatusOptions: (id: string | number) =>
    ["operations", "recommendation", normalizeOperationId(id), "status-options"] as const,
  decisions: (recommendationId: string | number) =>
    ["operations", "recommendation", normalizeOperationId(recommendationId), "decisions"] as const,
  decision: (id: string | number) =>
    ["operations", "decision", normalizeOperationId(id)] as const,
  decisionStatusOptions: (id: string | number) =>
    ["operations", "decision", normalizeOperationId(id), "status-options"] as const,
  recoveries: (decisionId: string | number) =>
    ["operations", "decision", normalizeOperationId(decisionId), "recoveries"] as const,
  recovery: (id: string | number) =>
    ["operations", "recovery", normalizeOperationId(id)] as const,
  recoveryStatusOptions: (id: string | number) =>
    ["operations", "recovery", normalizeOperationId(id), "status-options"] as const,
  recoveryMonitoring: (id: string | number) =>
    ["operations", "recovery", normalizeOperationId(id), "monitoring"] as const,
  evidences: (investigationId: string | number) =>
    ["operations", "investigation", normalizeOperationId(investigationId), "evidences"] as const,
  evidence: (id: string | number) =>
    ["operations", "evidence", normalizeOperationId(id)] as const,
  evidenceCustody: (id: string | number) =>
    ["operations", "evidence", normalizeOperationId(id), "custody"] as const,
  reporterEvidenceFiles: (caseId: string | number) =>
    ["operations", "case", normalizeOperationId(caseId), "reporter-evidence-files"] as const,
  userLookup: (role: string, search?: string) => ["operations", "users", "lookup", role, search ?? ""] as const,
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

export function getInvestigationStatusOptions(id: string | number) {
  return apiRequest<InvestigationStatusOptions>(`/investigations/${id}/status-options`);
}

export function getCaseRecommendations(caseId: string | number) {
  return apiRequest<Recommendation[]>(`/cases/${caseId}/recommendations`);
}

export function getRecommendation(id: string | number) {
  return apiRequest<Recommendation>(`/recommendations/${id}`);
}

export function getRecommendationStatusOptions(id: string | number) {
  return apiRequest<RecommendationStatusOptions>(`/recommendations/${id}/status-options`);
}

export function getRecommendationDecisions(recommendationId: string | number) {
  return apiRequest<Decision[]>(`/recommendations/${recommendationId}/decisions`);
}

export function getDecision(id: string | number) {
  return apiRequest<Decision>(`/decisions/${id}`);
}

export function getDecisionStatusOptions(id: string | number) {
  return apiRequest<DecisionStatusOptions>(`/decisions/${id}/status-options`);
}

export function getDecisionRecoveries(decisionId: string | number) {
  return apiRequest<Recovery[]>(`/decisions/${decisionId}/recoveries`);
}

export function getRecovery(id: string | number) {
  return apiRequest<Recovery>(`/recoveries/${id}`);
}

export function getRecoveryStatusOptions(id: string | number) {
  return apiRequest<RecoveryStatusOptions>(`/recoveries/${id}/status-options`);
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

export function lookupUsers(role: string, search?: string) {
  return apiRequest<UserLookupItem[]>("/users/lookup", {
    query: { role, search: search || undefined, limit: 50 },
  });
}

export function forwardReportToCase(id: string | number, payload: SatgasAssignmentPayload) {
  return apiRequest<ForwardReportToCaseResult>(`/reports/${id}/forward-to-case`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function assignCaseSatgas(id: string | number, payload: SatgasAssignmentPayload) {
  return apiRequest<CaseRecord>(`/cases/${id}/assign`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function updateCaseStatus(id: string | number, payload: CaseStatusPayload) {
  return apiRequest<CaseRecord>(`/cases/${id}/status`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function recordCaseAssessment(
  id: string | number,
  payload: { risk_level_code: string; priority_level_code: string },
) {
  return apiRequest<CaseRecord>(`/cases/${id}/assessment`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function createInvestigation(caseId: string | number, payload: InvestigationCreatePayload) {
  return apiRequest<Investigation>(`/cases/${caseId}/investigations`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateInvestigationStatus(id: string | number, payload: InvestigationStatusPayload) {
  return apiRequest<Investigation>(`/investigations/${id}/status`, {
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

export function createRecommendation(caseId: string | number, payload: RecommendationCreatePayload) {
  return apiRequest<Recommendation>(`/cases/${caseId}/recommendations`, {
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

export function updateRecommendationStatus(id: string | number, payload: RecommendationStatusPayload) {
  return apiRequest<Recommendation>(`/recommendations/${id}/status`, {
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

export function createDecision(recommendationId: string | number, payload: DecisionCreatePayload) {
  return apiRequest<Decision>(`/recommendations/${recommendationId}/decisions`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateDecisionStatus(id: string | number, payload: DecisionStatusPayload) {
  return apiRequest<Decision>(`/decisions/${id}/status`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function createRecovery(decisionId: string | number, payload: RecoveryCreatePayload) {
  return apiRequest<Recovery>(`/decisions/${decisionId}/recoveries`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateRecoveryStatus(id: string | number, payload: RecoveryStatusPayload) {
  return apiRequest<Recovery>(`/recoveries/${id}/status`, {
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

export function createEvidence(investigationId: string | number, payload: EvidenceCreatePayload) {
  return apiRequest<EvidenceMetadata>(`/investigations/${investigationId}/evidences`, {
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

export function uploadEvidenceFile(id: string | number, file: File) {
  const body = new FormData();
  body.append("file", file);

  return apiRequest<EvidenceMetadata>(`/evidences/${id}/file`, {
    method: "POST",
    body,
  });
}

export function downloadEvidenceFile(id: string | number) {
  return apiDownload(`/evidences/${id}/file`, `evidence-${id}`);
}

export function previewEvidenceFile(id: string | number, signal?: AbortSignal) {
  return apiFetchBlob(`/evidences/${id}/preview`, { signal });
}

export function getCaseReporterEvidenceFiles(caseId: string | number) {
  return apiRequest<ReporterEvidenceFile[]>(`/cases/${caseId}/reporter-evidence-files`);
}

export function downloadCaseReporterEvidenceFile(id: string) {
  return apiDownload(
    `/reporter-evidence-files/${encodeURIComponent(id)}/download`,
    `reporter-evidence-${id}`,
  );
}

export function previewCaseReporterEvidenceFile(id: string, signal?: AbortSignal) {
  return apiFetchBlob(
    `/reporter-evidence-files/${encodeURIComponent(id)}/preview`,
    { signal },
  );
}

function emptyMeta(total: number) {
  return {
    current_page: 1,
    per_page: total,
    total,
    last_page: 1,
  };
}
