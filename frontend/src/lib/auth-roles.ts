import type { RoleCode } from "@/lib/api-types";

export const DASHBOARD_ROLE_CODES = ["super_admin", "admin", "satgas_ppks"] as const;

export const PORTAL_ROLE_CODES = ["reporter"] as const;

export function hasDashboardAccess(roleCode: RoleCode | null) {
  return DASHBOARD_ROLE_CODES.includes(roleCode as (typeof DASHBOARD_ROLE_CODES)[number]);
}

export function hasPortalAccess(roleCode: RoleCode | null) {
  return PORTAL_ROLE_CODES.includes(roleCode as (typeof PORTAL_ROLE_CODES)[number]);
}
