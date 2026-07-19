import type { PaginationMeta } from "@/lib/api-types";

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
  priority?: MasterRef | null;
  case?: ReportCaseSummary | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  forwarded_at: string | null;
  created_at: string | null;
  sensitive_details?: CaseSensitiveReport;
}

export interface CaseAssignment {
  id: number;
  satgas_id: number;
  satgas_name?: string | null;
  assigned_by: number | null;
  is_lead: boolean;
  is_active: boolean;
  assigned_at: string | null;
  unassigned_at: string | null;
}

export interface CaseSensitiveReport {
  chronology?: string | null;
  incident_date?: string | null;
  incident_time?: string | null;
  incident_location?: string | null;
  respondent?: {
    name?: string | null;
    details?: string | null;
  };
  respondent_name?: string | null;
  respondent_campus_status?: string | null;
  respondent_relation?: string | null;
  respondent_details?: string | null;
  witness_info?: string | null;
}

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
  escalated_at?: string | null;
  assignments?: CaseAssignment[];
  report?: CaseSensitiveReport;
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
    active_lead_assignment: boolean;
    recommendation_status: string | null;
    decision_exists: boolean;
    decision_status: string | null;
    recovery_exists: boolean;
    recovery_status: string | null;
    monitoring_count: number;
    same_campus_admin: boolean;
    oversight_read_only: boolean;
    sensitive_oversight_enabled: boolean;
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
  decision_number?: string | null;
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
}

export interface RecoveryStatusOption {
  code: string;
  name: string;
  description?: string | null;
  soft_warning?: string | null;
}

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
  is_lead: boolean;
  is_active: boolean;
}

export interface ReportCaseSummary {
  id: number;
  case_number: string;
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
  decision_number?: string | null;
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
  lead_satgas_id: number;
}

export interface ForwardReportToCaseResult {
  report: {
    id: number | null;
    status: string | null;
    forwarded_at: string | null;
  };
  case: CaseRecord;
}
