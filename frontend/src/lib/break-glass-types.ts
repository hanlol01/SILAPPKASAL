import type { PaginationMeta } from "@/lib/api-types";

export type BreakGlassStatus =
  | "pending"
  | "approved"
  | "denied"
  | "revoked"
  | "expired"
  | "viewed"
  | string;

export type BreakGlassDurationMinutes = 30 | 60 | 240 | 1440;

export type BreakGlassReasonCategory =
  | "legal_requirement"
  | "safety_emergency"
  | "investigation_necessity"
  | "institutional_compliance"
  | "victim_consent";

export interface BreakGlassPerson {
  name: string;
  role?: {
    code: string | null;
    name: string | null;
  } | null;
}

export interface BreakGlassReport {
  registration_number: string;
  report_type: string;
}

export interface BreakGlassCase {
  case_number: string;
}

export interface BreakGlassRequest {
  id: number;
  requestor?: BreakGlassPerson | null;
  approver?: BreakGlassPerson | null;
  report?: BreakGlassReport | null;
  case?: BreakGlassCase | null;
  reason_category: BreakGlassReasonCategory | string;
  reason: string;
  requested_duration_minutes: number;
  status: BreakGlassStatus;
  denial_reason?: string | null;
  revocation_reason?: string | null;
  requested_at: string | null;
  approved_at: string | null;
  grant_starts_at: string | null;
  revoked_at: string | null;
  denied_at: string | null;
  viewed_at: string | null;
  view_count: number;
  last_viewed_at: string | null;
  can_reveal: boolean;
  can_revoke: boolean;
  expires_at: string | null;
  created_at: string | null;
}

export interface BreakGlassReveal {
  name: string | null;
  nim: string | null;
  email: string | null;
  phone_number: string | null;
  faculty: { code: string | null; name: string | null } | null;
  study_program: { code: string | null; name: string | null } | null;
  university: { code: string | null; name: string | null } | null;
}

export interface BreakGlassRequestPayload {
  case_id: number;
  reason_category: BreakGlassReasonCategory;
  reason: string;
  requested_duration_minutes: BreakGlassDurationMinutes;
  acknowledgment: boolean;
}

export interface BreakGlassDenyPayload {
  denial_reason: string;
}

export interface BreakGlassRevokePayload {
  revocation_reason: string;
}

export interface BreakGlassPage {
  data: BreakGlassRequest[];
  meta: PaginationMeta;
}
