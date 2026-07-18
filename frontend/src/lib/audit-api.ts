import { apiDownload, apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type {
  AuditHistoryFilters,
  AuditLogEntry,
  AuditPageMeta,
  OversightFilters,
  OversightItem,
  OversightSummary,
} from "@/lib/audit-types";
import type { PaginationMeta } from "@/lib/api-types";

interface LaravelResourcePage<T> {
  data: T[];
  meta: PaginationMeta;
}

export const auditQueryKeys = {
  summary: (filters: Pick<OversightFilters, "urgency" | "cutoff">) =>
    ["audit", "oversight-summary", filters] as const,
  oversight: (filters: OversightFilters) => ["audit", "oversight", filters] as const,
  history: (filters: AuditHistoryFilters) => ["audit", "history", filters] as const,
  detail: (publicId: string | null) => ["audit", "detail", publicId] as const,
};

function query<T extends object>(filters: T) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== ""),
  ) as Record<string, string | number | boolean | undefined>;
}

export function getOversightSummary(filters: Pick<OversightFilters, "urgency" | "cutoff">) {
  return apiRequest<OversightSummary>("/audit-logs/summary", { query: query(filters) });
}

export async function getOversightItems(filters: OversightFilters) {
  const response = await apiRequestEnvelope<OversightItem[], AuditPageMeta>(
    "/audit-logs/oversight",
    { query: query(filters) },
  );

  return { data: response.data, meta: response.meta! };
}

export async function getAuditHistory(filters: AuditHistoryFilters) {
  const response = await apiRequestEnvelope<LaravelResourcePage<AuditLogEntry>, AuditPageMeta>(
    "/audit-logs",
    { query: query(filters) },
  );

  return {
    data: response.data.data,
    meta: {
      ...response.data.meta,
      ...response.meta,
    } as AuditPageMeta,
  };
}

export function getAuditDetail(publicId: string) {
  return apiRequest<AuditLogEntry>(`/audit-logs/${encodeURIComponent(publicId)}`);
}

export function downloadAuditCsv(filters: AuditHistoryFilters) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== "" && key !== "page" && key !== "per_page") {
      params.set(key, String(value));
    }
  });

  const suffix = params.size > 0 ? `?${params.toString()}` : "";
  return apiDownload(`/audit-logs/export${suffix}`, "audit-log.csv");
}
