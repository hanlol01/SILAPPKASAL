import type { RoleCode } from "@/lib/api-types";

export const DASHBOARD_ROLE_CODES = ["super_admin", "admin", "satgas_ppks"] as const;

export function hasDashboardAccess(roleCode: RoleCode | null) {
  return DASHBOARD_ROLE_CODES.includes(roleCode as (typeof DASHBOARD_ROLE_CODES)[number]);
}
