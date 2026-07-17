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
  /** Reporter-safe status code. Legacy display labels are normalized client-side. */
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
  /** Reporter-safe status code. Legacy display labels are normalized client-side. */
  portal_status: string;
  submitted_at: string | null;
}

// ---------------------------------------------------------------------------
// Portal Report Timeline
// (GET /api/v1/portal/reports/{registrationNumber}/timeline)
// ---------------------------------------------------------------------------

/**
 * Reporter-safe progress timeline. The backend maps internal workflow states
 * to safe stage codes — the frontend must never reconstruct progress from
 * internal case data.
 */
export interface PortalTimelineEvent {
  /** Safe stage code (e.g. "laporan_dikirim"). Never a raw internal status. */
  stage: string;
  occurred_at: string | null;
}

export interface PortalReportTimeline {
  registration_number: string;
  portal_status: string;
  is_completed: boolean;
  events: PortalTimelineEvent[];
}

// ---------------------------------------------------------------------------
// Reporter-owned supporting files
// ---------------------------------------------------------------------------

export interface PortalEvidenceFile {
  id: string;
  original_filename: string;
  mime_type: string;
  file_size: number;
  uploaded_at: string | null;
}

export interface PortalEvidenceFilesMeta {
  upload_allowed: boolean;
  max_files: number;
  remaining_slots: number;
}

export interface PortalEvidenceFilesResult {
  data: PortalEvidenceFile[];
  meta: PortalEvidenceFilesMeta;
}

// ---------------------------------------------------------------------------
// Portal Notifications (GET /api/v1/portal/notifications)
// ---------------------------------------------------------------------------

export interface PortalNotification {
  id: string;
  title?: string | null;
  body?: string | null;
  type: string;
  notification_type_code?: string | null;
  event?: string | null;
  data?: Record<string, unknown> | null;
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
  id: number;
  role: {
    code: string;
    name: string;
  } | null;
  is_active: boolean;
  email_verified_at: string | null;
  created_at: string | null;
  registration_number?: string | null;
}

// ---------------------------------------------------------------------------
// Reporter Self-Service — Change Password (PATCH /api/v1/me/change-password)
// ---------------------------------------------------------------------------

export interface PortalChangePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ReportSubmissionPayload {
  report_type: "open" | "confidential" | "anonymous" | string;
  category_code: string;
  chronology: string;
  incident_date: string;
  incident_time?: string | null;
  incident_location: string;
  location_type?: string | null;
  respondent_name?: string | null;
  respondent_campus_status?: string | null;
  respondent_relation?: string | null;
  respondent_details?: string | null;
  witness_info?: string | null;
  reporter_phone?: string | null;
}

export interface ReportSubmissionResult {
  registration_number: string;
  tracking_code?: string | null;
  status?: string | null;
  submitted_at?: string | null;
}

export interface TrackingLookupResult {
  registration_number: string;
  tracking_code?: string | null;
  status: string;
  report_type?: string | null;
  category?: string | null;
  submitted_at: string | null;
}
