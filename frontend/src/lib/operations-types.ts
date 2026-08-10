import type { PaginationMeta } from "@/lib/api-types";
import type { ReportInputDetails } from "@/lib/report-input-types";

export interface PaginatedData<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface PersonRef {
  id: number;
  name: string;
}

export interface MasterRef {
  code: string;
  name: string;
  description?: string | null;
}

export type ReportReporter =
  | {
      masked: true;
    }
  | {
      id: number;
      name: string;
    };

export interface ReportSummary {
  id: number;
  registration_number: string;
  report_type: string;
  is_anonymous?: boolean;
  reporter?: ReportReporter | null;
  category?: MasterRef | null;
  status: string;
  priority: ReportPriorityProjection;
  case?: ReportCaseSummary | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  forwarded_at: string | null;
  created_at: string | null;
  withdrawn_at?: string | null;
  withdrawal_workflow?: ReportWithdrawalWorkflow | null;
  submitted_details?: ReportInputDetails;
}

export interface ReportWithdrawalWorkflow {
  withdrawal_reference: string;
  status: ReportWithdrawalStatus;
  submitted_at: string | null;
  reviewed_at: string | null;
}

export type ReportWithdrawalStatus =
  | "pending_review"
  | "approved"
  | "rejected"
  | "cancelled";

export interface ReportWithdrawalReviewListItem {
  withdrawal_reference: string;
  registration_number: string;
  status: ReportWithdrawalStatus;
  submitted_at: string | null;
  reviewed_at: string | null;
  elapsed_waiting_seconds: number | null;
  campus: MasterRef | null;
  reporter_display_name?: string | null;
}

export interface ReportWithdrawalReviewAttachment {
  attachment_reference: string;
  document_type: "signed_withdrawal_statement";
  version: number;
  mime_type: "application/pdf" | "image/jpeg" | "image/png";
  size: number;
  uploaded_at: string | null;
}

export interface ReportWithdrawalReviewDetail extends ReportWithdrawalReviewListItem {
  created_at?: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  cancelled_at: string | null;
  lock_version?: number;
  report_status?: string;
  case_status?: string | null;
  reason?: string;
  rejection_reason?: string | null;
  resubmission_allowed?: boolean;
  attachments?: ReportWithdrawalReviewAttachment[];
  capabilities: {
    can_review: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_view_signed_document: boolean;
  };
  history: Array<{ status: string; occurred_at: string }>;
}

export interface ReportPriorityProjection {
  availability: "unavailable" | "unassessed" | "assessed";
  level: MasterRef | null;
}

export interface CaseAssignment {
  id: number;
  satgas_id: number;
  satgas_name?: string | null;
  assigned_by_name?: string | null;
  assignment_type: "assign" | "self_assign";
  is_active: boolean;
  assigned_at: string | null;
  unassigned_at: string | null;
}

export type CaseSensitiveReport = ReportInputDetails;

export interface CaseRecord {
  id: number;
  case_number: string;
  registration_number: string;
  status?: string | null;
  status_code: string;
  status_label?: string | null;
  risk_level?: string | null;
  risk_level_code?: string | null;
  priority?: string | null;
  current_stage?: string | null;
  current_stage_label?: string | null;
  report_submitted_at?: string | null;
  forwarded_at: string | null;
  assessment_at?: string | null;
  investigation_started_at?: string | null;
  recommendation_at?: string | null;
  decision_at?: string | null;
  closed_at?: string | null;
  withdrawn_at?: string | null;
  escalated_at?: string | null;
  lock_version: string;
  assignments?: CaseAssignment[];
  assignment_history?: CaseAssignment[];
  assignment_capabilities?: {
    manage: WorkflowActionCapability;
    self_assign: WorkflowActionCapability;
  };
  report?: ReportInputDetails;
  workflow_context?: WorkflowContext;
}

export interface WorkflowActionCapability {
  allowed: boolean;
  reason_code: string | null;
}

export interface WorkflowContext {
  facts: {
    assessment_complete: boolean;
    investigation_exists: boolean;
    investigation_status: string | null;
    investigation_status_code: string | null;
    current_stage_activity_count: number;
    recommendation_exists: boolean;
    active_assignment: boolean;
    recommendation_status: string | null;
    decision_exists: boolean;
    decision_status: string | null;
    recovery_exists: boolean;
    recovery_status: string | null;
    monitoring_count: number;
    same_campus_admin: boolean;
    oversight_read_only: boolean;
    sensitive_oversight_enabled: boolean;
    final_summary_exists: boolean;
    final_summary_published: boolean;
    final_outcome_code: string | null;
    final_outcome_compatible: boolean;
    finalization_path: "completed" | "discontinued" | "legacy_completion" | null;
    operationally_paused?: boolean;
  };
  actions: {
    update_case_status: WorkflowActionCapability;
    create_investigation: WorkflowActionCapability;
    add_activity: WorkflowActionCapability;
    update_investigation_status: WorkflowActionCapability;
    add_evidence: WorkflowActionCapability;
    create_recommendation: WorkflowActionCapability;
    review_recommendation: WorkflowActionCapability;
    create_decision: WorkflowActionCapability;
    manage_recovery: WorkflowActionCapability;
    add_monitoring: WorkflowActionCapability;
    complete_recovery: WorkflowActionCapability;
    discontinue_recovery: WorkflowActionCapability;
    create_final_summary: WorkflowActionCapability;
    update_final_summary: WorkflowActionCapability;
    publish_final_summary: WorkflowActionCapability;
    finalize_closure: WorkflowActionCapability;
  };
  primary_tip_code: string;
  primary_tip_params?: Record<string, string | number | null>;
}

export interface Investigation {
  id: number;
  case_id: number;
  case_number?: string | null;
  registration_number?: string | null;
  status?: string | null;
  status_code: string;
  status_label?: string | null;
  lead_investigator?: PersonRef | null;
  activity_count?: number;
  plan_summary?: string | null;
  findings?: string | null;
  conclusion?: string | null;
  activities?: InvestigationActivity[];
  activity_counts_by_stage?: Record<string, number>;
  current_stage_activity_count?: number;
  started_at: string | null;
  completed_at: string | null;
  created_at: string | null;
}

export interface InvestigationActivity {
  id: number;
  activity_type: string;
  investigation_stage_code?: string | null;
  investigation_stage?: string | null;
  investigation_stage_label?: string | null;
  activity_date: string | null;
  description?: string | null;
  findings?: string | null;
  notes?: string | null;
  investigator?: PersonRef | null;
  created_at: string | null;
}

export interface InvestigationCreatePayload {
  plan_summary: string;
}

export interface InvestigationStatusPayload {
  status: string;
}

export interface InvestigationStatusOption {
  code: string;
  name: string;
  description?: string | null;
}

export interface InvestigationStatusOptions {
  current_status: InvestigationStatusOption;
  valid_transitions: InvestigationStatusOption[];
  current_stage_activity_count: number;
  can_transition: boolean;
  reason_code: string | null;
}

export interface Recommendation {
  id: number;
  case_id: number;
  case_number?: string | null;
  registration_number?: string | null;
  investigation_id: number;
  status?: string | null;
  status_code: string;
  status_label?: string | null;
  author?: PersonRef | null;
  conclusion?: string | null;
  recommended_actions?: string | null;
  sanction_recommendation?: string | null;
  recovery_recommendation?: string | null;
  prevention_recommendation?: string | null;
  review?: {
    revision_note?: string | null;
    returned_by?: PersonRef | null;
    returned_at?: string | null;
    approved_by?: PersonRef | null;
    approved_at?: string | null;
  };
  /** @deprecated Legacy API alias retained for one release. */
  leadership_review?: Recommendation["review"];
  submitted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface RecommendationCreatePayload {
  investigation_id: number;
  conclusion: string;
  recommended_actions: string;
  sanction_recommendation?: string | null;
  recovery_recommendation?: string | null;
  prevention_recommendation?: string | null;
}

export interface Decision {
  id: number;
  recommendation_id: number;
  case_id?: number | null;
  case_number?: string | null;
  registration_number?: string | null;
  status?: string | null;
  status_code: string;
  status_label?: string | null;
  outcome_code?: string | null;
  decision_number?: string | null;
  decision_date: string | null;
  decision_summary?: string | null;
  decision_content?: string | null;
  recorder?: PersonRef | null;
  recorded_at: string | null;
  finalized_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface DecisionCreatePayload {
  outcome_code: string;
  decision_date: string;
  decision_summary: string;
  decision_content: string;
}

export interface Recovery {
  id: number;
  decision_id: number;
  case_id?: number | null;
  case_number?: string | null;
  registration_number?: string | null;
  recovery_type?: MasterRef | null;
  status?: string | null;
  status_code: string;
  status_label?: string | null;
  recovery_plan?: string | null;
  support_needs?: string | null;
  notes?: string | null;
  discontinuation_reason?: string | null;
  creator?: PersonRef | null;
  monitoring?: RecoveryMonitoring[];
  started_at: string | null;
  completed_at: string | null;
  discontinued_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface RecoveryCreatePayload {
  recovery_type_code: string;
  recovery_plan: string;
  support_needs?: string | null;
  notes?: string | null;
}

export interface RecoveryStatusPayload {
  status: string;
  discontinuation_reason?: string | null;
}

export interface RecoveryStatusOption {
  code: string;
  name: string;
  description?: string | null;
  soft_warning?: string | null;
  allowed?: boolean;
  reason_code?: string | null;
}

export interface CaseFinalSummary {
  id: number;
  case_id: number;
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
  is_published: boolean;
  published_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CaseFinalOutcomeOption {
  code: string;
  label: string;
}

export interface CaseFinalSummaryEnvelope {
  summary: CaseFinalSummary | null;
  outcome_options: CaseFinalOutcomeOption[];
}

export interface CaseClosureDocument {
  public_id: string;
  document_number: string;
  issued_at: string | null;
}

export interface CaseClosureDocumentEnvelope {
  document: CaseClosureDocument | null;
  capabilities: {
    manage: boolean;
    issue: boolean;
    preview: boolean;
    download: boolean;
  };
}

export type CaseFinalSummaryPayload = Omit<
  CaseFinalSummary,
  "id" | "case_id" | "outcome_label" | "is_published" | "published_at" | "created_at" | "updated_at"
>;

export type CaseMinuteStatus = "draft" | "finalized" | "superseded";

export interface CaseMinuteCapabilities {
  update: boolean;
  finalize: boolean;
  create_revision: boolean;
}

export interface CaseMinuteCaseReference {
  case_number: string | null;
}

export interface CaseMinuteInternal {
  projection: "internal";
  public_id: string;
  version: number;
  status: CaseMinuteStatus;
  occurred_at: string;
  internal_summary: string | null;
  anonymized_summary: string | null;
  outcome: string | null;
  follow_up: string | null;
  finalized_at: string | null;
  case: CaseMinuteCaseReference;
  supersedes: { public_id: string; version: number } | null;
  creator: { id: number } | null;
  updater: { id: number } | null;
  finalizer: { id: number } | null;
  created_at: string | null;
  updated_at: string | null;
  lock_version: string;
  capabilities: CaseMinuteCapabilities;
}

export interface CaseMinuteMetadata {
  projection: "metadata";
  public_id: string;
  version: number;
  status: CaseMinuteStatus;
  occurred_at: string;
  finalized_at: string | null;
  case: CaseMinuteCaseReference;
  campus: { code: string | null };
}

export type CaseMinute = CaseMinuteInternal | CaseMinuteMetadata;

export interface CaseMinutesEnvelope {
  projection: "internal" | "metadata";
  items: CaseMinute[];
  capabilities: { create: boolean };
}

export interface CaseMinuteDraftPayload {
  occurred_at: string;
  internal_summary?: string | null;
  anonymized_summary?: string | null;
  outcome?: string | null;
  follow_up?: string | null;
}

export type CaseMinuteUpdatePayload = CaseMinuteDraftPayload & { lock_version: string };

export interface RecoveryStatusOptions {
  current_status: RecoveryStatusOption;
  valid_transitions: RecoveryStatusOption[];
}

export interface RecoveryMonitoring {
  id: number;
  recovery_id: number;
  monitoring_date: string | null;
  status?: string | null;
  condition_summary?: string | null;
  follow_up_plan?: string | null;
  notes?: string | null;
  monitor?: PersonRef | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface EvidenceMetadata {
  id: number;
  investigation_id: number;
  case_id?: number | null;
  case_number?: string | null;
  evidence_type?: MasterRef | null;
  title: string;
  description?: string | null;
  source?: string | null;
  collected_at: string | null;
  classification?: string | null;
  status: string;
  status_semantics?: string | null;
  file_metadata?: {
    original_filename?: string | null;
    mime_type?: string | null;
    file_size?: number | null;
    checksum_sha256?: string | null;
  };
  file_attachment: EvidenceFileAttachment | null;
  submitted_by?: PersonRef | null;
  custody_events?: EvidenceCustodyEvent[];
  created_at: string | null;
  updated_at: string | null;
}

export type RecommendationReviewPayload =
  | { action: "approve" }
  | { action: "return_for_revision"; revision_note: string };

export interface ReportCaseAssignmentSummary {
  satgas_id: number;
  satgas_name?: string | null;
  is_active: boolean;
}

export interface ReportCaseSummary {
  id: number;
  case_number: string;
  lock_version: string;
  active_assignments: ReportCaseAssignmentSummary[];
}

export interface EvidenceFileAttachment {
  original_filename: string;
  mime_type: string;
  file_size: number;
  uploaded_at: string | null;
  uploaded_by?: PersonRef | null;
}

export interface ReporterEvidenceFile {
  id: string;
  original_filename: string;
  mime_type: string;
  file_size: number;
  uploaded_at: string | null;
}

export interface EvidenceCustodyEvent {
  event_type: string;
  actor?: PersonRef | null;
  event_at: string | null;
  details?: Record<string, unknown> | string | null;
}

export interface EvidenceCreatePayload {
  evidence_type_code: string;
  title: string;
  description?: string | null;
  source?: string | null;
  collected_at?: string | null;
  classification?: string | null;
}

export interface CaseStatusPayload {
  status: string;
}

export interface InvestigationActivityPayload {
  activity_type: string;
  activity_date: string;
  description: string;
  findings?: string | null;
  notes?: string | null;
}

export interface RecommendationUpdatePayload {
  conclusion?: string;
  recommended_actions?: string;
  sanction_recommendation?: string | null;
  recovery_recommendation?: string | null;
  prevention_recommendation?: string | null;
}

export interface RecommendationStatusPayload {
  status: string;
}

export interface RecommendationStatusOption {
  code: string;
  name: string;
  description?: string | null;
}

export interface RecommendationStatusOptions {
  current_status: RecommendationStatusOption;
  valid_transitions: RecommendationStatusOption[];
}

export interface DecisionUpdatePayload {
  outcome_code?: string;
  decision_date?: string;
  decision_summary?: string;
  decision_content?: string;
}

export interface DecisionStatusPayload {
  status: string;
}

export interface DecisionStatusOption {
  code: string;
  name: string;
  description?: string | null;
}

export interface DecisionStatusOptions {
  current_status: DecisionStatusOption;
  valid_transitions: DecisionStatusOption[];
}

export interface RecoveryMonitoringPayload {
  monitoring_date: string;
  condition_summary: string;
  follow_up_plan?: string | null;
  notes?: string | null;
}

export interface EvidenceUpdatePayload {
  evidence_type_code?: string;
  title?: string;
  description?: string | null;
  source?: string | null;
  collected_at?: string | null;
  classification?: string | null;
}

export interface EvidenceStatusPayload {
  status: string;
}

export interface UserLookupRole {
  code: string | null;
  name: string | null;
}

export interface UserLookupItem {
  id: number;
  name: string;
  role: UserLookupRole | null;
}

export interface SatgasAssignmentPayload {
  satgas_ids: number[];
  lock_version?: string;
}

export interface ForwardReportToCaseResult {
  report: {
    id: number | null;
    status: string | null;
    forwarded_at: string | null;
  };
  case: CaseRecord;
}
