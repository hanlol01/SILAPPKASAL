import { apiRequest } from "@/lib/api-client";
import type {
  DashboardCases,
  DashboardEvidence,
  DashboardFilters,
  DashboardReports,
  DashboardSummary,
  DashboardWorkflow,
} from "@/lib/api-types";

export const dashboardQueryKeys = {
  summary: (filters?: DashboardFilters) => ["dashboard", "summary", filters] as const,
  reports: (filters?: DashboardFilters) => ["dashboard", "reports", filters] as const,
  cases: (filters?: DashboardFilters) => ["dashboard", "cases", filters] as const,
  workflow: (filters?: DashboardFilters) => ["dashboard", "workflow", filters] as const,
  evidence: (filters?: DashboardFilters) => ["dashboard", "evidence", filters] as const,
};

export function getDashboardSummary(filters?: DashboardFilters) {
  return apiRequest<DashboardSummary>("/dashboard/summary", { query: filters });
}

export function getDashboardReports(filters?: DashboardFilters) {
  return apiRequest<DashboardReports>("/dashboard/reports", { query: filters });
}

export function getDashboardCases(filters?: DashboardFilters) {
  return apiRequest<DashboardCases>("/dashboard/cases", { query: filters });
}

export function getDashboardWorkflow(filters?: DashboardFilters) {
  return apiRequest<DashboardWorkflow>("/dashboard/workflow", { query: filters });
}

export function getDashboardEvidence(filters?: DashboardFilters) {
  return apiRequest<DashboardEvidence>("/dashboard/evidence", { query: filters });
}
