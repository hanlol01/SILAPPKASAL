import type { CaseStatus } from "@/types";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import {
  formatCaseStatus,
  formatRegistrationStatus,
  formatReportStatus,
  formatDecisionStatus,
  formatEvidenceStatus,
  formatInvestigationStatus,
  formatPriorityLevel,
  formatRecommendationStatus,
  formatRecoveryStatus,
  formatRiskLevel,
} from "@/lib/format-labels";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import {
  AlertTriangle,
  ArrowUpRight,
  Archive,
  CheckCircle2,
  ClipboardCheck,
  ClipboardList,
  FileSearch,
  FileText,
  Gavel,
  HeartHandshake,
  Inbox,
  Lock,
  PauseCircle,
  Scale,
  Search,
  Send,
  UserCheck,
  UserX,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

/**
 * Tone tokens used by every status badge. Keep the surface narrow so a future
 * theme tweak only touches this map.
 */
type StatusTone = "info" | "primary" | "warning" | "success" | "muted" | "destructive" | "accent";

const toneClass: Record<StatusTone, string> = {
  info: "bg-info/15 text-info border-info/30",
  primary: "bg-primary/15 text-primary border-primary/30 dark:text-primary",
  warning: "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning",
  success: "bg-success/15 text-success border-success/30",
  muted: "bg-muted text-muted-foreground border-border",
  destructive: "bg-destructive/15 text-destructive border-destructive/30",
  accent: "bg-accent/30 text-accent-foreground border-accent/40",
};

interface StatusVisual {
  tone: StatusTone;
  icon: LucideIcon;
}

/**
 * Legacy short-code → visual map (kept for backward compatibility with any
 * callers still using the local CaseStatus type from `@/types`).
 */
const legacyCaseStatus: Record<CaseStatus, StatusVisual> = {
  received: { tone: "info", icon: Inbox },
  verification: { tone: "warning", icon: Search },
  investigation: { tone: "primary", icon: FileSearch },
  mediation: { tone: "accent", icon: Scale },
  resolved: { tone: "success", icon: CheckCircle2 },
  closed: { tone: "muted", icon: Lock },
};

/**
 * Tone + icon map for the canonical backend case-status enum.
 * Codes match `dashboard.enum.caseStatus.*` in the locale files.
 */
const caseStatusVisuals: Record<string, StatusVisual> = {
  submitted: { tone: "info", icon: Send },
  under_review: { tone: "warning", icon: Search },
  need_info: { tone: "warning", icon: AlertTriangle },
  rejected: { tone: "destructive", icon: AlertTriangle },
  forwarded: { tone: "info", icon: Inbox },
  assessment: { tone: "warning", icon: Search },
  investigation: { tone: "primary", icon: FileSearch },
  mediation: { tone: "accent", icon: Scale },
  recommendation: { tone: "primary", icon: ClipboardList },
  decision: { tone: "warning", icon: Gavel },
  decided: { tone: "success", icon: CheckCircle2 },
  recovery: { tone: "accent", icon: HeartHandshake },
  monitoring: { tone: "accent", icon: HeartHandshake },
  closed: { tone: "muted", icon: Lock },
  escalated: { tone: "destructive", icon: ArrowUpRight },
};

const defaultCaseVisual: StatusVisual = { tone: "muted", icon: FileText };

const caseStatusAliases: Record<string, string> = {
  "CSTS-01": "submitted",
  "CSTS-02": "under_review",
  "CSTS-03": "need_info",
  "CSTS-04": "rejected",
  "CSTS-05": "forwarded",
  "CSTS-06": "assessment",
  "CSTS-07": "investigation",
  "CSTS-08": "mediation",
  "CSTS-09": "recommendation",
  "CSTS-10": "decision",
  "CSTS-11": "decided",
  "CSTS-12": "recovery",
  "CSTS-13": "monitoring",
  "CSTS-14": "closed",
  "CSTS-15": "escalated",
};

function resolveCaseVisual(status: string | null | undefined): StatusVisual {
  if (!status) return defaultCaseVisual;
  const normalizedStatus = caseStatusAliases[status] ?? status;
  if (normalizedStatus in caseStatusVisuals) return caseStatusVisuals[normalizedStatus];
  if (status in legacyCaseStatus) return legacyCaseStatus[status as CaseStatus];
  return defaultCaseVisual;
}

/**
 * Multi-channel badge for case status. Accepts either the legacy short codes
 * or any backend case-status code; falls back to a neutral chip + icon when
 * the code is unknown so the surface never crashes on an out-of-band value.
 */
export function StatusBadge({
  status,
  className,
}: {
  status: CaseStatus | string | null | undefined;
  className?: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const visual = resolveCaseVisual(status ?? undefined);
  const Icon = visual.icon;
  const label = status ? formatCaseStatus(t, status) : t("dashboard:common.notAvailable");

  return (
    <Badge variant="outline" className={cn("gap-1 font-medium", toneClass[visual.tone], className)}>
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {label}
    </Badge>
  );
}

const reportStatusVisuals: Record<string, StatusVisual> = {
  submitted: { tone: "info", icon: Send },
  under_review: { tone: "warning", icon: Search },
  need_info: { tone: "warning", icon: AlertTriangle },
  rejected: { tone: "destructive", icon: AlertTriangle },
  forwarded: { tone: "success", icon: ArrowUpRight },
};

export function ReportStatusBadge({
  status,
  className,
}: {
  status: string | null | undefined;
  className?: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const visual = status ? (reportStatusVisuals[status] ?? defaultCaseVisual) : defaultCaseVisual;
  const Icon = visual.icon;
  const label = status ? formatReportStatus(t, status) : t("dashboard:common.notAvailable");

  return (
    <Badge variant="outline" className={cn("gap-1 font-medium", toneClass[visual.tone], className)}>
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {label}
    </Badge>
  );
}

const registrationStatusVisuals: Record<string, StatusVisual> = {
  pending: { tone: "warning", icon: PauseCircle },
  approved: { tone: "success", icon: UserCheck },
  rejected: { tone: "destructive", icon: UserX },
};

export function RegistrationStatusBadge({
  status,
  className,
}: {
  status: string | null | undefined;
  className?: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const visual = status ? (registrationStatusVisuals[status] ?? defaultCaseVisual) : defaultCaseVisual;
  const Icon = visual.icon;
  const label = status ? formatRegistrationStatus(t, status) : t("dashboard:common.notAvailable");

  return (
    <Badge variant="outline" className={cn("gap-1 font-medium", toneClass[visual.tone], className)}>
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {label}
    </Badge>
  );
}

export function ActiveStateBadge({
  active,
  activeLabel,
  inactiveLabel,
  className,
}: {
  active: boolean;
  activeLabel: string;
  inactiveLabel: string;
  className?: string;
}) {
  const visual: StatusVisual = active
    ? { tone: "success", icon: CheckCircle2 }
    : { tone: "muted", icon: Lock };
  const Icon = visual.icon;

  return (
    <Badge variant="outline" className={cn("gap-1 font-medium", toneClass[visual.tone], className)}>
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {active ? activeLabel : inactiveLabel}
    </Badge>
  );
}

export type WorkflowFamily =
  | "investigation"
  | "recommendation"
  | "decision"
  | "recovery"
  | "evidence";

interface WorkflowFamilyConfig {
  fallback: StatusVisual;
  format: (t: TFunction, value: unknown) => string;
  byStatus: Record<string, StatusVisual>;
}

const workflowFamilies: Record<WorkflowFamily, WorkflowFamilyConfig> = {
  investigation: {
    fallback: { tone: "info", icon: FileSearch },
    format: formatInvestigationStatus,
    byStatus: {
      planning: { tone: "info", icon: ClipboardList },
      document_review: { tone: "primary", icon: FileSearch },
      evidence_collection: { tone: "primary", icon: FileSearch },
      victim_interview: { tone: "primary", icon: FileSearch },
      witness_interview: { tone: "primary", icon: FileSearch },
      reported_party_interview: { tone: "primary", icon: FileSearch },
      respondent_interview: { tone: "primary", icon: FileSearch },
      evidence_analysis: { tone: "primary", icon: FileSearch },
      timeline_review: { tone: "primary", icon: FileSearch },
      report_drafting: { tone: "warning", icon: ClipboardCheck },
      completed: { tone: "success", icon: CheckCircle2 },
    },
  },
  recommendation: {
    fallback: { tone: "info", icon: ClipboardList },
    format: formatRecommendationStatus,
    byStatus: {
      drafting: { tone: "info", icon: ClipboardList },
      internal_review: { tone: "warning", icon: Search },
      revised: { tone: "warning", icon: ClipboardList },
      submitted_to_leader: { tone: "primary", icon: ClipboardCheck },
      accepted: { tone: "success", icon: CheckCircle2 },
      partially_accepted: { tone: "warning", icon: AlertTriangle },
      rejected: { tone: "destructive", icon: AlertTriangle },
    },
  },
  decision: {
    fallback: { tone: "info", icon: Gavel },
    format: formatDecisionStatus,
    byStatus: {
      draft: { tone: "info", icon: ClipboardList },
      recorded: { tone: "primary", icon: Gavel },
      reviewed: { tone: "warning", icon: Search },
      finalized: { tone: "success", icon: CheckCircle2 },
    },
  },
  recovery: {
    fallback: { tone: "info", icon: HeartHandshake },
    format: formatRecoveryStatus,
    byStatus: {
      planned: { tone: "info", icon: ClipboardList },
      ongoing: { tone: "primary", icon: HeartHandshake },
      completed: { tone: "success", icon: CheckCircle2 },
      discontinued: { tone: "destructive", icon: AlertTriangle },
    },
  },
  evidence: {
    fallback: { tone: "info", icon: FileText },
    format: formatEvidenceStatus,
    byStatus: {
      registered: { tone: "info", icon: FileText },
      under_review: { tone: "warning", icon: Search },
      verified: { tone: "success", icon: CheckCircle2 },
      rejected: { tone: "destructive", icon: AlertTriangle },
      archived: { tone: "muted", icon: Archive },
    },
  },
};

/**
 * Multi-channel badge for the four sub-status families on the case detail
 * page (investigation, recommendation, decision, recovery, evidence).
 * Mirrors the visual structure of StatusBadge so the page reads as one
 * design system rather than five different chip styles.
 */
export function WorkflowStatusBadge({
  family,
  status,
  className,
}: {
  family: WorkflowFamily;
  status: string | null | undefined;
  className?: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const config = workflowFamilies[family];
  const visual = status ? (config.byStatus[status] ?? config.fallback) : config.fallback;
  const Icon = visual.icon;
  const label = status ? config.format(t, status) : t("dashboard:common.notAvailable");

  return (
    <Badge variant="outline" className={cn("gap-1 font-medium", toneClass[visual.tone], className)}>
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {label}
    </Badge>
  );
}
