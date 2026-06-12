/**
 * Portal-specific type definitions for the reporter-facing portal.
 *
 * These types are intentionally separate from operations-types.ts.
 * The portal API returns privacy-filtered shapes — reporters must never
 * see internal IDs, chronology, respondent details, or investigator names.
 *
 * Field names match the actual backend response contract.
 */

// ---------------------------------------------------------------------------
// Portal Summary (GET /api/v1/portal/summary)
// ---------------------------------------------------------------------------

export interface PortalSummary {
  total_reports: number;
  active_reports: number;
  completed_reports: number;
  unread_notifications: number;
}

// ---------------------------------------------------------------------------
// Portal Reports (GET /api/v1/portal/reports)
// ---------------------------------------------------------------------------

export interface PortalReport {
  /** Primary key for routing — internal numeric ID is never exposed. */
  registration_number: string;
  report_type: string;
  category: string | null;
  /** Backend-curated reporter-safe status label, e.g. "Under Review". */
  portal_status: string;
  submitted_at: string | null;
}

// ---------------------------------------------------------------------------
// Portal Report Detail (GET /api/v1/portal/reports/{registrationNumber})
// ---------------------------------------------------------------------------

export interface PortalReportDetail {
  registration_number: string;
  report_type: string;
  category: string | null;
  /** Backend-curated reporter-safe status label, e.g. "Under Review". */
  portal_status: string;
  submitted_at: string | null;
}

// ---------------------------------------------------------------------------
// Portal Notifications (GET /api/v1/portal/notifications)
// ---------------------------------------------------------------------------

export interface PortalNotification {
  id: string;
  title: string;
  body: string;
  type: string;
  read_at: string | null;
  created_at: string;
}

// ---------------------------------------------------------------------------
// Reporter Self-Service — Profile (GET /api/v1/me/profile)
// ---------------------------------------------------------------------------

export interface PortalProfile {
  name: string;
  email: string;
  phone_number: string | null;
  nim: string | null;
  nip: string | null;
}

/** Payload for PATCH /api/v1/me/profile — only name and phone_number are editable. */
export interface PortalProfileUpdatePayload {
  name: string;
  phone_number: string | null;
}

// ---------------------------------------------------------------------------
// Reporter Self-Service — Account Status (GET /api/v1/me/account-status)
// ---------------------------------------------------------------------------

export interface PortalAccountStatus {
  is_active: boolean;
  email_verified_at: string | null;
  last_login_at: string | null;
}

// ---------------------------------------------------------------------------
// Reporter Self-Service — Change Password (PATCH /api/v1/me/change-password)
// ---------------------------------------------------------------------------

export interface PortalChangePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}
