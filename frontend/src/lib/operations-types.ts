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

export interface ReportSummary {
  id: number;
  registration_number: string;
  report_type: string;
  category?: MasterRef | null;
  status: string;
  priority?: MasterRef | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  forwarded_at: string | null;
  created_at: string | null;
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
  forwarded_at: string | null;
  assessment_at?: string | null;
  investigation_started_at?: string | null;
  recommendation_at?: string | null;
  decision_at?: string | null;
  closed_at?: string | null;
  escalated_at?: string | null;
  assignments?: CaseAssignment[];
  report?: CaseSensitiveReport;
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
  started_at: string | null;
  completed_at: string | null;
  created_at: string | null;
}

export interface InvestigationActivity {
  id: number;
  activity_type: string;
  activity_date: string | null;
  description?: string | null;
  findings?: string | null;
  notes?: string | null;
  investigator?: PersonRef | null;
  created_at: string | null;
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
  submitted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
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
  outcome_code: string;
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
  submitted_by?: PersonRef | null;
  custody_events?: EvidenceCustodyEvent[];
  created_at: string | null;
  updated_at: string | null;
}

export interface EvidenceCustodyEvent {
  event_type: string;
  actor?: PersonRef | null;
  event_at: string | null;
  details?: string | null;
}
