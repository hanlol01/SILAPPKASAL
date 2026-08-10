import { apiDownload, apiFetchBlob, apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type {
  CaseRecord,
  CaseFinalSummary,
  CaseFinalSummaryEnvelope,
  CaseFinalSummaryPayload,
  CaseClosureDocument,
  CaseClosureDocumentEnvelope,
  CaseMinute,
  CaseMinuteDraftPayload,
  CaseMinutesEnvelope,
  CaseMinuteUpdatePayload,
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
  RecommendationReviewPayload,
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
  ReportWithdrawalReviewDetail,
  ReportWithdrawalReviewListItem,
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
  withdrawalReviewsRoot: () => ["operations", "withdrawal-reviews"] as const,
  withdrawalReviews: (query?: Record<string, QueryValue>) =>
    ["operations", "withdrawal-reviews", query] as const,
  withdrawalReview: (publicId: string) =>
    ["operations", "withdrawal-review", publicId] as const,
  casesRoot: () => ["operations", "cases"] as const,
  cases: (query?: Record<string, QueryValue>) => ["operations", "cases", query] as const,
  case: (id: string | number) => ["operations", "case", normalizeOperationId(id)] as const,
  caseFinalSummary: (id: string | number) =>
    ["operations", "case", normalizeOperationId(id), "final-summary"] as const,
  caseClosureDocument: (id: string | number) =>
    ["operations", "case", normalizeOperationId(id), "closure-document"] as const,
  caseMinutes: (id: string | number) =>
    ["operations", "case", normalizeOperationId(id), "minutes"] as const,
  caseMinute: (publicId: string) => ["operations", "case-minute", publicId] as const,
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
  reportReporterEvidenceFiles: (reportId: string | number) =>
    ["operations", "report", normalizeOperationId(reportId), "reporter-evidence-files"] as const,
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

export function getCaseClosureDocument(caseId: string | number) {
  return apiRequest<CaseClosureDocumentEnvelope>(`/cases/${caseId}/closure-document`);
}

export function issueCaseClosureDocument(caseId: string | number) {
  return apiRequest<CaseClosureDocument>(`/cases/${caseId}/closure-document`, { method: "POST" });
}

export function downloadCaseClosureDocument(publicId: string) {
  return apiDownload(
    `/case-closure-documents/${encodeURIComponent(publicId)}/download`,
    "Berita Acara Hasil Pelaporan Kekerasan Seksual.pdf",
  );
}

export function previewCaseClosureDocument(publicId: string, signal?: AbortSignal) {
  return apiFetchBlob(`/case-closure-documents/${encodeURIComponent(publicId)}/preview`, { signal });
}

export async function getReportWithdrawalReviews(
  query?: Record<string, QueryValue>,
): Promise<PaginatedData<ReportWithdrawalReviewListItem>> {
  const envelope = await apiRequestEnvelope<ReportWithdrawalReviewListItem[]>(
    "/report-withdrawals",
    { query },
  );
  return { data: envelope.data, meta: envelope.meta ?? emptyMeta(envelope.data.length) };
}

export function getReportWithdrawalReview(publicId: string) {
  return apiRequest<ReportWithdrawalReviewDetail>(
    `/report-withdrawals/${encodeURIComponent(publicId)}`,
  );
}

export function previewReportWithdrawalDocument(
  publicId: string,
  attachmentPublicId: string,
  signal?: AbortSignal,
) {
  return apiFetchBlob(
    `/report-withdrawals/${encodeURIComponent(publicId)}/signed-document/${encodeURIComponent(attachmentPublicId)}`,
    { signal },
  );
}

export function approveReportWithdrawal(publicId: string, lockVersion: number) {
  return apiRequest<ReportWithdrawalReviewDetail>(
    `/report-withdrawals/${encodeURIComponent(publicId)}/approve`,
    {
      method: "POST",
      body: JSON.stringify({ lock_version: lockVersion, confirmed: true }),
    },
  );
}

export function rejectReportWithdrawal(
  publicId: string,
  payload: { lock_version: number; rejection_reason: string; resubmission_allowed: boolean },
) {
  return apiRequest<ReportWithdrawalReviewDetail>(
    `/report-withdrawals/${encodeURIComponent(publicId)}/reject`,
    { method: "POST", body: JSON.stringify(payload) },
  );
}

export function getCaseFinalSummary(id: string | number) {
  return apiRequest<CaseFinalSummaryEnvelope>(`/cases/${id}/final-summary`);
}

export function getCaseMinutes(caseId: string | number) {
  return apiRequest<CaseMinutesEnvelope>(`/cases/${caseId}/minutes`);
}

export function getCaseMinute(publicId: string) {
  return apiRequest<CaseMinute>(`/case-minutes/${encodeURIComponent(publicId)}`);
}

export function createCaseMinute(caseId: string | number, payload: CaseMinuteDraftPayload) {
  return apiRequest<CaseMinute>(`/cases/${caseId}/minutes`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateCaseMinute(publicId: string, payload: CaseMinuteUpdatePayload) {
  return apiRequest<CaseMinute>(`/case-minutes/${encodeURIComponent(publicId)}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function createCaseMinuteRevision(publicId: string) {
  return apiRequest<CaseMinute>(`/case-minutes/${encodeURIComponent(publicId)}/revisions`, {
    method: "POST",
  });
}

export function finalizeCaseMinute(publicId: string, lockVersion: string) {
  return apiRequest<CaseMinute>(`/case-minutes/${encodeURIComponent(publicId)}/finalize`, {
    method: "POST",
    body: JSON.stringify({ lock_version: lockVersion }),
  });
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

export function selfAssignCase(id: string | number, lockVersion: string) {
  return apiRequest<CaseRecord>(`/cases/${id}/self-assign`, {
    method: "POST",
    body: JSON.stringify({ lock_version: lockVersion }),
  });
}

export function createCaseFinalSummary(caseId: string | number, payload: CaseFinalSummaryPayload) {
  return apiRequest<CaseFinalSummary>(`/cases/${caseId}/final-summary`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export function updateCaseFinalSummary(caseId: string | number, payload: CaseFinalSummaryPayload) {
  return apiRequest<CaseFinalSummary>(`/cases/${caseId}/final-summary`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export function publishCaseFinalSummary(caseId: string | number) {
  return apiRequest<CaseFinalSummary>(`/cases/${caseId}/final-summary/publish`, {
    method: "POST",
  });
}

export function finalizeCaseClosure(caseId: string | number) {
  return apiRequest<CaseRecord>(`/cases/${caseId}/close`, { method: "POST" });
}

export function submitRecommendation(id: string | number) {
  return apiRequest<Recommendation>(`/recommendations/${id}/submit`, {
    method: "POST",
  });
}

export function reviewRecommendation(id: string | number, payload: RecommendationReviewPayload) {
  return apiRequest<Recommendation>(`/recommendations/${id}/review`, {
    method: "POST",
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

export function getReportReporterEvidenceFiles(reportId: string | number) {
  return apiRequest<ReporterEvidenceFile[]>(`/reports/${reportId}/reporter-evidence-files`);
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
