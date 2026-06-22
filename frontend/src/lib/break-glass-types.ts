import type { PaginationMeta } from "@/lib/api-types";

export type BreakGlassStatus = "pending" | "approved" | "denied" | "viewed" | "expired" | string;

export type BreakGlassReasonCategory =
  | "legal_requirement"
  | "safety_emergency"
  | "investigation_necessity"
  | "institutional_compliance"
  | "victim_consent";

export interface BreakGlassPerson {
  id: number;
  name: string;
  role?: {
    code: string | null;
    name: string | null;
  } | null;
}

export interface BreakGlassReport {
  id: number;
  registration_number: string;
  report_type: string;
}

export interface BreakGlassRequest {
  id: number;
  requestor?: BreakGlassPerson | null;
  approver?: BreakGlassPerson | null;
  report?: BreakGlassReport | null;
  reason_category: BreakGlassReasonCategory | string;
  reason: string;
  status: BreakGlassStatus;
  denial_reason?: string | null;
  requested_at: string | null;
  approved_at: string | null;
  denied_at: string | null;
  viewed_at: string | null;
  is_viewable: boolean;
  expires_at: string | null;
  created_at: string | null;
}

export interface BreakGlassReveal {
  name: string | null;
  email: string | null;
  valid_until?: string | null;
  expires_at?: string | null;
}

export interface BreakGlassRequestPayload {
  report_id: number;
  reason_category: BreakGlassReasonCategory;
  reason: string;
  acknowledgment: boolean;
}

export interface BreakGlassDenyPayload {
  denial_reason: string;
}

export interface BreakGlassPage {
  data: BreakGlassRequest[];
  meta: PaginationMeta;
}
