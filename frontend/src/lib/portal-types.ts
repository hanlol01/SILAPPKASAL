/**
 * Portal-specific type definitions for the reporter-facing portal.
 *
 * These types are intentionally separate from operations-types.ts.
 * The portal API returns privacy-filtered shapes — reporters must never
 * see internal IDs, staff identities, or operational narrative content.
 *
 * Field names match the actual backend response contract.
 */

import type { ReportInputDetails, ReportInputReference } from "@/lib/report-input-types";

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
  category: ReportInputReference | null;
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
  category: ReportInputReference | null;
  /** Reporter-safe status code. Legacy display labels are normalized client-side. */
  portal_status: string;
  submitted_at: string | null;
  submitted_details: ReportInputDetails;
  withdrawal_capabilities: WithdrawalCapabilities;
}

export interface ActiveWithdrawalSummary {
  withdrawal_reference: string;
  request_type: "early_cancellation" | "formal_withdrawal";
  status: string;
  lock_version: number;
  created_at: string | null;
  draft_document_viewed_at: string | null;
  submitted_at: string | null;
  has_signed_document: boolean;
  latest_attachment: WithdrawalAttachment | null;
  attachments?: WithdrawalAttachment[];
  capabilities: FormalWithdrawalCapabilities;
  reviewed_at?: string | null;
  approved_at?: string | null;
  rejected_at?: string | null;
  cancelled_at?: string | null;
  rejection_reason?: string | null;
  resubmission_allowed?: boolean;
}

export interface WithdrawalAttachment {
  attachment_reference: string;
  document_type: "signed_withdrawal_statement";
  version: number;
  mime_type: "application/pdf" | "image/jpeg" | "image/png";
  size: number;
  uploaded_at: string | null;
}

export interface FormalWithdrawalCapabilities {
  can_view_draft: boolean;
  can_upload_document: boolean;
  can_submit: boolean;
  can_cancel_request: boolean;
  can_resubmit: boolean;
}

export interface FormalWithdrawalDetail {
  withdrawal_reference: string;
  request_type: "formal_withdrawal";
  status: "draft" | "waiting_document" | "pending_review" | "approved" | "rejected" | "cancelled";
  lock_version: number;
  reason: string;
  created_at: string | null;
  draft_document_viewed_at: string | null;
  submitted_at: string | null;
  cancelled_at: string | null;
  reviewed_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  resubmission_allowed: boolean;
  supersedes_reference: string | null;
  has_signed_document: boolean;
  latest_attachment: WithdrawalAttachment | null;
  attachments: WithdrawalAttachment[];
  capabilities: FormalWithdrawalCapabilities;
}

export interface WithdrawalCapabilities {
  can_cancel: boolean;
  can_request_withdrawal: boolean;
  cancellation_block_reason_code:
    | "already_processed"
    | "terminal_state"
    | "ownership_unavailable"
    | "active_request"
    | "feature_disabled"
    | null;
  withdrawal_block_reason_code:
    | "feature_disabled"
    | "ownership_unavailable"
    | "active_request"
    | "terminal_state"
    | "not_forwarded"
    | "case_stage_ineligible"
    | "decision_finalized"
    | null;
  active_withdrawal: ActiveWithdrawalSummary | null;
  latest_withdrawal: (ActiveWithdrawalSummary & {
    reviewed_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    cancelled_at?: string | null;
    rejection_reason?: string | null;
    resubmission_allowed?: boolean;
  }) | null;
}

export interface DirectCancellationResult {
  withdrawal_reference: string;
  report_status: "cancelled";
  portal_status: "cancelled_by_reporter";
  completed_at: string | null;
  capabilities: WithdrawalCapabilities;
}

export type PortalHandlingState =
  | "unavailable"
  | "not_started"
  | "ongoing"
  | "waiting"
  | "completed"
  | "discontinued";

export interface PortalReportHandlingProgress {
  registration_number: string;
  case: {
    available: boolean;
    state: "not_started" | "ongoing" | "completed";
  };
  investigation: {
    state: PortalHandlingState;
    started_at: string | null;
    completed_at: string | null;
    activity_count: number;
  };
  recommendation: {
    state: PortalHandlingState;
    submitted_at: string | null;
    reviewed_at: string | null;
    approved_at: string | null;
  };
  decision: {
    state: PortalHandlingState;
    decision_date: string | null;
    finalized_at: string | null;
  };
  recovery: {
    state: PortalHandlingState;
    started_at: string | null;
    completed_at: string | null;
    discontinued_at: string | null;
  };
  monitoring: {
    count: number;
    latest_at: string | null;
  };
  evidence: {
    reporter_supporting_file_count: number;
    internal_evidence_count: number;
  };
  final_summary: PortalFinalSummary | null;
  closure_document: {
    available: boolean;
    document_number?: string | null;
    issued_at?: string | null;
  };
}

export type PortalFinalSummary =
  | { state: "legacy_completion" }
  | {
      state: "published";
      outcome_code: string;
      outcome_label: string;
      completion_date: string;
      official_statement: string;
      investigation_summary?: string | null;
      recommendation_result?: string | null;
      decision_result?: string | null;
      recovery_result?: string | null;
      actions_completed?: string | null;
      actions_uncompleted?: string | null;
      follow_up_or_referral?: string | null;
      closing_explanation: string;
      published_at?: string | null;
    };

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
