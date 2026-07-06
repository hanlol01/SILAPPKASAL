/**
 * Portal API functions and query key factory for the reporter-facing portal.
 *
 * All functions use the shared apiRequest / apiRequestEnvelope wrappers
 * from api-client.ts — no new HTTP client code.
 *
 * Query keys use a dedicated ["portal", ...] namespace to prevent
 * cache collisions with admin ["operations", ...] / ["dashboard", ...] keys.
 */

import { apiRequest, apiRequestEnvelope } from "@/lib/api-client";
import type {
  PortalSummary,
  PortalReport,
  PortalReportDetail,
  PortalReportTimeline,
  PortalNotification,
  PortalProfile,
  PortalProfileUpdatePayload,
  PortalAccountStatus,
  PortalChangePasswordPayload,
  ReportSubmissionPayload,
  ReportSubmissionResult,
  TrackingLookupResult,
} from "@/lib/portal-types";
import type { PaginationMeta } from "@/lib/api-types";

// ---------------------------------------------------------------------------
// Query key factory
// ---------------------------------------------------------------------------

type QueryValue = string | number | boolean | undefined;

export const portalQueryKeys = {
  summary:       ()                          => ["portal", "summary"] as const,
  reports:       (q?: Record<string, QueryValue>) => ["portal", "reports", q] as const,
  report:        (regNum: string)            => ["portal", "report", regNum] as const,
  reportTimeline: (regNum: string)           => ["portal", "report", regNum, "timeline"] as const,
  notifications: ()                          => ["portal", "notifications"] as const,
  profile:       ()                          => ["portal", "profile"] as const,
  accountStatus: ()                          => ["portal", "account-status"] as const,
  tracking: (code: string)                   => ["portal", "tracking", code] as const,
};

// ---------------------------------------------------------------------------
// Portal read endpoints
// ---------------------------------------------------------------------------

/** GET /api/v1/portal/summary */
export function getPortalSummary() {
  return apiRequest<PortalSummary>("/portal/summary");
}

/** GET /api/v1/portal/reports */
export async function getPortalReports(
  query?: Record<string, QueryValue>,
): Promise<{ data: PortalReport[]; meta: PaginationMeta }> {
  const envelope = await apiRequestEnvelope<PortalReport[]>("/portal/reports", { query });
  return {
    data: envelope.data,
    meta: envelope.meta ?? {
      current_page: 1,
      per_page: envelope.data.length,
      total: envelope.data.length,
      last_page: 1,
    },
  };
}

/** GET /api/v1/portal/reports/{registrationNumber} */
export function getPortalReport(registrationNumber: string) {
  return apiRequest<PortalReportDetail>(`/portal/reports/${encodeURIComponent(registrationNumber)}`);
}

/** GET /api/v1/portal/reports/{registrationNumber}/timeline — reporter-safe stages only. */
export function getPortalReportTimeline(registrationNumber: string) {
  return apiRequest<PortalReportTimeline>(
    `/portal/reports/${encodeURIComponent(registrationNumber)}/timeline`,
  );
}

/** GET /api/v1/portal/notifications */
export function getPortalNotifications() {
  return apiRequest<PortalNotification[]>("/portal/notifications");
}

// ---------------------------------------------------------------------------
// Reporter self-service endpoints
// ---------------------------------------------------------------------------

/** GET /api/v1/me/profile */
export function getMyProfile() {
  return apiRequest<PortalProfile>("/me/profile");
}

/** PATCH /api/v1/me/profile — only name and phone_number are editable. */
export function updateMyProfile(data: PortalProfileUpdatePayload) {
  return apiRequest<PortalProfile>("/me/profile", {
    method: "PATCH",
    body: JSON.stringify(data),
  });
}

/** GET /api/v1/me/account-status */
export function getMyAccountStatus() {
  return apiRequest<PortalAccountStatus>("/me/account-status");
}

/** PATCH /api/v1/me/change-password */
export function changeMyPassword(data: PortalChangePasswordPayload) {
  return apiRequest<null>("/me/change-password", {
    method: "PATCH",
    body: JSON.stringify(data),
  });
}

/** POST /api/v1/reports */
export function submitReport(data: ReportSubmissionPayload) {
  return apiRequest<ReportSubmissionResult>("/reports", {
    method: "POST",
    body: JSON.stringify(data),
  });
}

/** GET /api/v1/reports/track/{trackingCode} */
export function trackReport(trackingCode: string) {
  return apiRequest<TrackingLookupResult>(`/reports/track/${encodeURIComponent(trackingCode)}`);
}
