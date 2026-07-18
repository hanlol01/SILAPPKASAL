import type { PaginationMeta } from "@/lib/api-types";

export type AuditQueue =
  | "waiting_admin"
  | "waiting_satgas"
  | "waiting_leader"
  | "emergency_access"
  | "critical_security";

export type AuditUrgency = "normal" | "attention" | "overdue";
export type AuditResult = "succeeded" | "failed" | "denied";
export type AuditActorKind = "system" | "reporter" | "staff";

export interface OversightSummary {
  queues: Record<AuditQueue, number>;
  urgencies: Record<AuditUrgency, number>;
  total: number;
  generated_at: string;
}

export interface OversightItem {
  queue: AuditQueue;
  work_type: string;
  reference: string;
  status: string;
  started_at: string;
  due_at: string;
  elapsed_business_seconds: number;
  elapsed_business_days: number;
  threshold_business_days: number;
  progress_percent: number;
  urgency: AuditUrgency;
}

export interface AuditActor {
  kind: AuditActorKind;
  role_code: string | null;
  display_name_safe: string | null;
  label: string;
}

export interface AuditSubject {
  kind: string | null;
  reference: string | null;
}

export interface AuditLogEntry {
  public_id: string;
  request_id: string | null;
  action: string;
  category: string;
  severity: string;
  result: AuditResult;
  actor: AuditActor;
  subject: AuditSubject;
  is_elevated_access: boolean;
  metadata: Record<string, string | number | boolean | null>;
  changes: {
    before: Record<string, string | number | boolean | null>;
    after: Record<string, string | number | boolean | null>;
  };
  created_at: string;
}

export interface AuditHistoryFilters {
  category?: string;
  severity?: string;
  action?: string;
  result?: AuditResult;
  actor_kind?: AuditActorKind;
  actor_role_code?: string;
  is_elevated_access?: boolean;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
  cutoff?: string;
}

export interface OversightFilters {
  queue?: AuditQueue;
  urgency?: AuditUrgency;
  page?: number;
  per_page?: number;
  cutoff?: string;
}

export interface AuditPageMeta extends PaginationMeta {
  cutoff?: string;
  date_from?: string;
  date_to?: string;
}
