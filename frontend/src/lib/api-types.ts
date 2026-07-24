export type RoleCode = "super_admin" | "admin" | "satgas_ppks" | string;

export interface ApiEnvelope<T, TMeta = PaginationMeta> {
  success: boolean;
  message: string;
  error_code?: string | null;
  data: T;
  errors?: Record<string, string[]> | null;
  meta?: TMeta;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface ApiRole {
  id?: number;
  code: RoleCode;
  name: string;
  permissions?: ApiPermission[];
}

export interface ApiPermission {
  id?: number;
  code: string;
  name?: string;
}

export interface ApiUser {
  id: number;
  name: string;
  email: string;
  nim: string | null;
  nip: string | null;
  phone_number: string | null;
  university_id?: number | null;
  faculty_id?: number | null;
  study_program_id?: number | null;
  university?: CampusRef | null;
  faculty?: CampusRef | null;
  study_program?: CampusRef | null;
  role: ApiRole | null;
  permissions: string[];
  is_active: boolean;
  email_verified_at: string | null;
}

export interface CampusRef {
  id: number;
  code: string;
  name: string;
  abbreviation?: string | null;
  type?: string | null;
  has_faculties?: boolean;
  degree_level?: string | null;
}

export interface ReporterRegistrationAuthState {
  id: number;
  registration_number: string;
  name: string;
  email: string;
  nim: string;
  phone_number: string;
  university_id: number;
  faculty_id: number | null;
  study_program_id: number;
  university?: CampusRef | null;
  faculty?: CampusRef | null;
  study_program?: CampusRef | null;
  status: "pending" | "rejected" | string;
  rejection_reason?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export type LoginResponseData =
  | {
      type?: "bearer";
      token: string;
      token_type: string;
      expires_in: number;
      user: ApiUser;
    }
  | {
      type: "registration";
      registration: ReporterRegistrationAuthState;
    };

export interface DashboardFilters {
  date_from?: string;
  date_to?: string;
  granularity?: "day" | "week" | "month";
  satgas_id?: number;
  assignment_status?: "unassigned";
  university_id?: number;
}

export interface DashboardAppliedFilters extends DashboardFilters {
  date_from: string;
  date_to: string;
  granularity: "day" | "week" | "month";
}

export interface DashboardGroupCount {
  key: string | null;
  count: number;
}

export interface DashboardTimeSeriesPoint {
  bucket: string;
  count: number;
}

export interface DashboardSummary {
  scope: string;
  filters: DashboardAppliedFilters;
  totals: {
    reports: number;
    cases: number;
    investigations: number;
    recommendations: number;
    decisions: number;
    recoveries: number;
    evidences: number;
  };
  active_workflow: {
    cases_open: number;
    investigations_open: number;
    decisions_not_finalized: number;
    recoveries_open: number;
  };
  time_series: {
    reports: DashboardTimeSeriesPoint[];
    cases: DashboardTimeSeriesPoint[];
  };
}

export interface DashboardReports {
  scope: string;
  filters: DashboardAppliedFilters;
  total: number;
  by_status: DashboardGroupCount[];
  by_report_type: DashboardGroupCount[];
  by_category_code: DashboardGroupCount[];
  by_priority: DashboardGroupCount[];
  by_identity_mode: {
    anonymous: number;
    identified: number;
  };
  time_series: DashboardTimeSeriesPoint[];
}

export interface DashboardCases {
  scope: string;
  filters: DashboardAppliedFilters;
  total: number;
  by_status_code: DashboardGroupCount[];
  by_risk_level_code: DashboardGroupCount[];
  by_priority_code: DashboardGroupCount[];
  by_current_stage: DashboardGroupCount[];
  assignments: {
    assigned_cases: number;
    unassigned_cases: number;
    active_assignments: number;
  };
  time_series: DashboardTimeSeriesPoint[];
}

export interface DashboardWorkflow {
  scope: string;
  filters: DashboardAppliedFilters;
  metric_semantics: string;
  status_distributions: {
    investigations: DashboardGroupCount[];
    recommendations: DashboardGroupCount[];
    decisions: DashboardGroupCount[];
    recoveries: DashboardGroupCount[];
  };
  decision_outcomes: DashboardGroupCount[];
  recovery_types: DashboardGroupCount[];
  monitoring_time_series: DashboardTimeSeriesPoint[];
  conversion_counts: {
    reports_forwarded_to_cases: number;
    cases_with_investigations: number;
    cases_with_recommendations: number;
    recommendations_with_decisions: number;
    decisions_with_recoveries: number;
  };
}

export interface DashboardEvidence {
  scope: string;
  filters: DashboardAppliedFilters;
  privacy: string;
  total: number;
  by_status: DashboardGroupCount[];
  by_classification: DashboardGroupCount[];
  by_evidence_type_code: DashboardGroupCount[];
  file_metadata_presence: {
    with_metadata: number;
    without_metadata: number;
  };
  time_series: DashboardTimeSeriesPoint[];
}

export type MasterDataType =
  | "report-categories"
  | "report-types"
  | "evidence-types"
  | "case-statuses"
  | "risk-levels"
  | "priority-levels"
  | "campus-statuses"
  | "relations"
  | "location-types"
  | "escalation-types"
  | "recovery-types"
  | "recovery-statuses";

export interface MasterDataItem {
  code: string;
  name: string;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  examples?: unknown;
  legal_basis?: unknown;
  workflow_stage?: string;
  stage_name?: string;
  is_terminal?: boolean;
  responsible_role?: string;
  valid_transitions?: string[] | null;
}
