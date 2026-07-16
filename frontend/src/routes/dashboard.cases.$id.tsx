import { createFileRoute, Link } from "@tanstack/react-router";
import { useQueries, useQuery } from "@tanstack/react-query";
import { useCallback, useEffect, useState } from "react";
import {
  AlertCircle,
  ArrowLeft,
  BriefcaseMedical,
  CheckCircle2,
  ClipboardList,
  FileArchive,
  FileSearch,
  Gavel,
  History,
  Lock,
  RefreshCw,
  Scale,
  Send,
  Share2,
  Users,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { QueryErrorState } from "@/components/query-state";
import { ReporterEvidenceFiles } from "@/components/reporter-evidence-files";
import { EmptyState } from "@/components/empty-state";
import { EvidenceCustodyDisclosure } from "@/components/evidence-custody-disclosure";
import { EvidenceFileAttachment } from "@/components/evidence-file-attachment";
import { PriorityLevelBadge, RiskLevelBadge, StatusBadge, WorkflowStatusBadge } from "@/components/status-badge";
import {
  ProgressTimeline,
  ProgressTimelineSkeleton,
  type ProgressTimelineEvent,
} from "@/components/progress-timeline";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DecisionCreateAction } from "@/components/workflow-actions/decision-create-action";
import { DecisionStatusAction } from "@/components/workflow-actions/decision-status-action";
import { InvestigationCreateAction } from "@/components/workflow-actions/investigation-create-action";
import { InvestigationStatusAction } from "@/components/workflow-actions/investigation-status-action";
import { RecommendationCreateAction } from "@/components/workflow-actions/recommendation-create-action";
import { RecommendationStatusAction } from "@/components/workflow-actions/recommendation-status-action";
import { CaseAssessmentAction } from "@/components/workflow-actions/case-assessment-action";
import { EvidenceCreateAction } from "@/components/workflow-actions/evidence-create-action";
import { RecoveryCreateAction } from "@/components/workflow-actions/recovery-create-action";
import { RecoveryStatusAction } from "@/components/workflow-actions/recovery-status-action";
import { SatgasAssignmentAction } from "@/components/workflow-actions/satgas-assignment-action";
import {
  CaseStatusAction,
  DecisionUpdateAction,
  EvidenceMetadataAction,
  EvidenceStatusAction,
  InvestigationActivityAction,
  RecommendationUpdateAction,
  RecoveryMonitoringAction,
} from "@/components/workflow-actions/workflow-action-dialogs";
import { useAuth } from "@/hooks/use-auth";
import { formatDateTime } from "@/lib/format";
import {
  formatCaseStatus,
  formatDecisionOutcome,
  formatEvidenceClassification,
  formatEvidenceType,
  formatPriorityLevel,
  formatRiskLevel,
} from "@/lib/format-labels";
import {
  getCase,
  getCaseInvestigations,
  getCaseRecommendations,
  getDecisionRecoveries,
  getInvestigationEvidences,
  getRecommendationDecisions,
  operationsQueryKeys,
} from "@/lib/operations-api";
import type {
  CaseRecord,
  Decision,
  EvidenceMetadata,
  Investigation,
  Recommendation,
  Recovery,
} from "@/lib/operations-types";

const WORKFLOW_TABS = ["investigation", "recommendation", "decision", "recovery", "evidence"] as const;
type WorkflowTab = (typeof WORKFLOW_TABS)[number];

const WORKFLOW_TAB_FALLBACK: WorkflowTab = "investigation";
const WORKFLOW_TAB_BY_TOKEN: Record<string, WorkflowTab> = {
  "4": "investigation",
  csts_07: "investigation",
  csts_08: "investigation",
  investigation: "investigation",
  investigations: "investigation",
  investigasi: "investigation",
  mediation: "investigation",
  mediasi: "investigation",

  "5": "recommendation",
  csts_09: "recommendation",
  recommendation: "recommendation",
  recommendations: "recommendation",
  rekomendasi: "recommendation",

  "6": "decision",
  csts_10: "decision",
  csts_11: "decision",
  decided: "decision",
  decision: "decision",
  decisions: "decision",
  keputusan: "decision",

  "7": "recovery",
  csts_12: "recovery",
  csts_13: "recovery",
  csts_14: "recovery",
  closed: "recovery",
  monitoring: "recovery",
  pemulihan: "recovery",
  recovery: "recovery",
  recoveries: "recovery",
};

const NEXT_STEP_STATUSES = [
  "forwarded",
  "assessment",
  "investigation",
  "mediation",
  "recommendation",
  "decision",
  "decided",
  "recovery",
  "monitoring",
  "closed",
  "escalated",
] as const;

const RESTRICTED_ROLE_CODES = ["super_admin", "admin", "satgas_ppks", "reporter"] as const;

export const Route = createFileRoute("/dashboard/cases/$id")({
  component: CaseDetail,
  head: () => ({ meta: [{ title: "Case detail - SILAPPKASAL Admin" }] }),
});

function CaseDetail() {
  const { id } = Route.useParams();
  const { user, roleCode } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const [caseStatusAvailable, setCaseStatusAvailable] = useState(true);
  const caseQuery = useQuery({
    queryKey: operationsQueryKeys.case(id),
    queryFn: () => getCase(id),
  });
  const isAssignedSatgas =
    roleCode === "satgas_ppks" &&
    (caseQuery.data?.assignments ?? []).some(
      (assignment) => assignment.is_active && assignment.satgas_id === user?.id,
    );
  const canViewEvidence =
    isAssignedSatgas && Boolean(user?.permissions?.includes("evidence.view.case"));
  const canViewReporterEvidence =
    isAssignedSatgas
    && user?.is_active === true
    && Boolean(user.permissions?.includes("reporter_evidence.read.assigned"));
  const canDownloadReporterEvidence =
    canViewReporterEvidence
    && Boolean(user?.permissions?.includes("reporter_evidence.download.assigned"));
  const investigationsQuery = useQuery({
    queryKey: operationsQueryKeys.investigations(id),
    queryFn: () => getCaseInvestigations(id),
  });
  const recommendationsQuery = useQuery({
    queryKey: operationsQueryKeys.recommendations(id),
    queryFn: () => getCaseRecommendations(id),
  });
  const decisionQueries = useQueries({
    queries: (recommendationsQuery.data ?? []).map((recommendation) => ({
      queryKey: operationsQueryKeys.decisions(recommendation.id),
      queryFn: () => getRecommendationDecisions(recommendation.id),
      enabled: recommendationsQuery.isSuccess,
    })),
  });
  const decisions = decisionQueries.flatMap((query) => query.data ?? []);
  const recoveryQueries = useQueries({
    queries: decisions.map((decision) => ({
      queryKey: operationsQueryKeys.recoveries(decision.id),
      queryFn: () => getDecisionRecoveries(decision.id),
      enabled: decisions.length > 0,
    })),
  });
  const recoveries = recoveryQueries.flatMap((query) => query.data ?? []);
  const evidenceQueries = useQueries({
    queries: canViewEvidence
      ? (investigationsQuery.data ?? []).map((investigation) => ({
          queryKey: operationsQueryKeys.evidences(investigation.id),
          queryFn: () => getInvestigationEvidences(investigation.id),
          enabled: investigationsQuery.isSuccess,
        }))
      : [],
  });
  const evidences = evidenceQueries.flatMap((query) => query.data ?? []);

  useEffect(() => {
    setCaseStatusAvailable(true);
  }, [caseQuery.data?.status_code]);

  const handleCaseStatusAvailability = useCallback((available: boolean) => {
    setCaseStatusAvailable(available);
  }, []);

  if (caseQuery.isLoading) {
    return <CaseDetailSkeleton />;
  }

  if (caseQuery.isError || !caseQuery.data) {
    return <QueryErrorState message={t("dashboard:cases.error")} onRetry={() => caseQuery.refetch()} />;
  }

  const c = caseQuery.data;
  const isAdminRole = roleCode === "super_admin" || roleCode === "admin";
  const canManageAssignments =
    isAdminRole && Boolean(user?.permissions?.includes("cases.assign_satgas"));
  const canUseSatgasActions = isAssignedSatgas && !c.closed_at;
  const canInvestigate = canUseSatgasActions && Boolean(user?.permissions?.includes("cases.investigate"));
  const canRecommend = canUseSatgasActions && Boolean(user?.permissions?.includes("cases.recommend"));
  const canManageDecisionActions =
    roleCode === "super_admin" && Boolean(user?.permissions?.includes("cases.record_decision"));
  const canManageRecoveryActions = isAdminRole && Boolean(user?.permissions?.includes("cases.monitor"));
  const canAddRecoveryMonitoring = canManageRecoveryActions || canUseSatgasActions;
  const activeAssignments = (c.assignments ?? []).filter((assignment) => assignment.is_active);
  const evidenceInvestigation = selectEvidenceInvestigation(investigationsQuery.data ?? []);
  const canUpdateEvidence =
    canViewEvidence &&
    Boolean(user?.permissions?.includes("evidence.upload")) &&
    !c.closed_at;
  const canDownloadEvidence =
    canViewEvidence && Boolean(user?.permissions?.includes("evidence.download"));
  const canCreateEvidence =
    canUpdateEvidence && evidenceInvestigation !== null;
  const latestCompletedInvestigation = mostRecentCompletedInvestigation(investigationsQuery.data ?? []);
  const submittedDecisionRecommendation = submittedRecommendationForDecision(recommendationsQuery.data ?? []);
  const decisionsLoaded = recommendationsQuery.isSuccess && decisionQueries.every((query) => query.isSuccess);
  const finalizedDecisionForRecovery = finalizedDecision(decisions);
  const canCreateInvestigation =
    canInvestigate &&
    investigationsQuery.isSuccess &&
    c.status === "investigation" &&
    (investigationsQuery.data ?? []).length === 0;
  const canCreateRecommendation =
    canRecommend &&
    investigationsQuery.isSuccess &&
    recommendationsQuery.isSuccess &&
    c.status === "recommendation" &&
    (recommendationsQuery.data ?? []).length === 0 &&
    latestCompletedInvestigation !== null;
  const canCreateDecision =
    canManageDecisionActions &&
    recommendationsQuery.isSuccess &&
    decisionsLoaded &&
    c.status === "decision" &&
    submittedDecisionRecommendation !== null &&
    decisions.length === 0;
  const canCreateRecovery =
    canManageRecoveryActions &&
    decisionsLoaded &&
    finalizedDecisionForRecovery !== null &&
    !c.closed_at;
  const hasGeneralActions =
    (canUseSatgasActions && caseStatusAvailable)
    || (canUseSatgasActions && c.status === "assessment")
    || canCreateInvestigation
    || canCreateRecommendation
    || canCreateDecision
    || canCreateRecovery;
  const defaultWorkflowTab = defaultWorkflowTabForCase(c);
  const restrictedLabel = restrictedRoleLabel(t, roleCode);
  const nextStepText = nextStepMessage(t, c, isAssignedSatgas, roleCode);
  const timelineLoading =
    investigationsQuery.isLoading ||
    recommendationsQuery.isLoading ||
    decisionQueries.some((query) => query.isLoading) ||
    recoveryQueries.some((query) => query.isLoading);
  const timelineEvents = caseProgressEvents(t, i18n.language, c, {
    investigations: investigationsQuery.data ?? [],
    recommendations: recommendationsQuery.data ?? [],
    decisions,
    recoveries,
  });

  return (
    <div className="min-w-0 space-y-6">
      <Breadcrumb className="min-w-0">
        <BreadcrumbList className="min-w-0">
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/dashboard">{t("dashboard:nav.overview")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/dashboard/cases">{t("dashboard:cases.title")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem className="min-w-0">
            <BreadcrumbPage className="min-w-0 break-words [overflow-wrap:anywhere]">
              {c.case_number}
            </BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>
      <div className="flex min-w-0 flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/dashboard/cases">
            <ArrowLeft className="mr-2 h-4 w-4" /> {t("dashboard:cases.allCases")}
          </Link>
        </Button>
        <div className="flex min-w-0 flex-wrap items-center gap-2">
          <h1 className="min-w-0 break-words font-mono text-lg font-semibold [overflow-wrap:anywhere]">
            {c.case_number}
          </h1>
          <StatusBadge status={c.status ?? c.status_code} />
        </div>
      </div>

      <div className="grid min-w-0 gap-4 lg:grid-cols-3">
        <div className="min-w-0 space-y-4 lg:col-span-2">
          <Card className="min-w-0">
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.metadata")}</CardTitle>
              <CardDescription>{t("dashboard:cases.metadataDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="grid min-w-0 gap-4 text-sm sm:grid-cols-2">
              <Field label={t("dashboard:cases.caseNumber")}>{c.case_number}</Field>
              <Field label={t("dashboard:reports.registration")}>{c.registration_number}</Field>
              <Field label={t("dashboard:common.status")}>{formatCaseStatus(t, c.status ?? c.status_code)}</Field>
              <Field label={t("dashboard:common.stage")}>{c.current_stage_label ?? formatCaseStatus(t, c.current_stage ?? "-")}</Field>
              <Field label={t("dashboard:common.risk")}>
                {(c.risk_level ?? c.risk_level_code) ? (
                  <RiskLevelBadge value={c.risk_level ?? c.risk_level_code} />
                ) : (
                  formatRiskValue(t, c.risk_level ?? c.risk_level_code)
                )}
              </Field>
              <Field label={t("dashboard:common.priority")}>
                {c.priority ? <PriorityLevelBadge value={c.priority} /> : formatPriorityValue(t, c.priority)}
              </Field>
              <Field label={t("dashboard:common.forwarded")}>{formatDate(c.forwarded_at, i18n.language)}</Field>
              <Field label={t("dashboard:common.closed")}>{formatDate(c.closed_at, i18n.language)}</Field>
            </CardContent>
          </Card>

          <Card className="min-w-0">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <History className="h-4 w-4" /> {t("dashboard:cases.progress.title")}
              </CardTitle>
              <CardDescription>{t("dashboard:cases.progress.desc")}</CardDescription>
            </CardHeader>
            <CardContent className="min-w-0">
              {timelineLoading ? (
                <ProgressTimelineSkeleton rows={4} />
              ) : timelineEvents.length === 0 ? (
                <EmptyText>{t("dashboard:cases.progress.empty")}</EmptyText>
              ) : (
                <ProgressTimeline
                  events={timelineEvents}
                  className="min-w-0 [&_li]:min-w-0 [&_li>div>div]:break-words [&_li>div>div]:[overflow-wrap:anywhere] [&_li>div>div]:whitespace-pre-wrap"
                />
              )}
            </CardContent>
          </Card>

          <SensitiveReportSection report={c.report} roleLabel={restrictedLabel} t={t} />
          <Tabs defaultValue={defaultWorkflowTab} className="w-full min-w-0">
            <div className="w-full min-w-0 overscroll-x-contain overflow-x-auto">
              <TabsList className="h-auto w-max min-w-full flex-nowrap justify-start">
                <TabsTrigger value="investigation" className="shrink-0 whitespace-nowrap">
                  {t("dashboard:cases.tabInvestigation")}
                </TabsTrigger>
                <TabsTrigger value="recommendation" className="shrink-0 whitespace-nowrap">
                  {t("dashboard:cases.tabRecommendation")}
                </TabsTrigger>
                <TabsTrigger value="decision" className="shrink-0 whitespace-nowrap">
                  {t("dashboard:cases.tabDecision")}
                </TabsTrigger>
                <TabsTrigger value="recovery" className="shrink-0 whitespace-nowrap">
                  {t("dashboard:cases.tabRecovery")}
                </TabsTrigger>
                <TabsTrigger value="evidence" className="shrink-0 whitespace-nowrap">
                  {t("dashboard:cases.tabEvidence")}
                </TabsTrigger>
              </TabsList>
            </div>
            <TabsContent value="investigation" className="min-w-0">
              <InvestigationsSection
                investigations={investigationsQuery.data ?? []}
                loading={investigationsQuery.isLoading}
                canAddActivity={canUseSatgasActions}
                canTransitionStatus={canInvestigate}
                caseId={c.id}
                language={i18n.language}
                roleLabel={restrictedLabel}
                t={t}
              />
            </TabsContent>
            <TabsContent value="recommendation" className="min-w-0">
              <RecommendationsSection
                recommendations={recommendationsQuery.data ?? []}
                loading={recommendationsQuery.isLoading}
                canUpdate={canRecommend}
                canTransitionStatus={canRecommend}
                caseId={c.id}
                roleLabel={restrictedLabel}
                t={t}
              />
            </TabsContent>
            <TabsContent value="decision" className="min-w-0">
              <DecisionsSection
                decisions={decisions}
                loading={decisionQueries.some((query) => query.isLoading)}
                canUpdate={canManageDecisionActions}
                canTransitionStatus={canManageDecisionActions}
                caseId={c.id}
                language={i18n.language}
                roleLabel={restrictedLabel}
                t={t}
              />
            </TabsContent>
            <TabsContent value="recovery" className="min-w-0">
              <RecoveriesSection
                recoveries={recoveries}
                loading={recoveryQueries.some((query) => query.isLoading)}
                canAddMonitoring={canAddRecoveryMonitoring}
                canTransitionStatus={canManageRecoveryActions}
                caseId={c.id}
                roleLabel={restrictedLabel}
                t={t}
              />
            </TabsContent>
            <TabsContent value="evidence" className="min-w-0">
              <div className="min-w-0 space-y-4">
                {canViewReporterEvidence && (
                  <ReporterEvidenceFiles
                    caseId={c.id}
                    canDownload={canDownloadReporterEvidence}
                    language={i18n.language}
                  />
                )}
                <EvidenceSection
                  evidences={evidences}
                  loading={evidenceQueries.some((query) => query.isLoading)}
                  error={evidenceQueries.some((query) => query.isError)}
                  onRetry={() => evidenceQueries.forEach((query) => query.refetch())}
                  canAccess={canViewEvidence}
                  canUpdate={canUpdateEvidence}
                  canDownload={canDownloadEvidence}
                  createInvestigation={canCreateEvidence ? evidenceInvestigation : null}
                  language={i18n.language}
                  roleLabel={restrictedLabel}
                  t={t}
                />
              </div>
            </TabsContent>
          </Tabs>
        </div>

        <div className="min-w-0 space-y-4">
          <Card className="min-w-0">
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.assignments")}</CardTitle>
              <CardDescription>{t("dashboard:cases.assignmentsDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="min-w-0 space-y-3">
              {(c.assignments ?? []).length === 0 && (
                <EmptyText>{t("dashboard:cases.noAssignments")}</EmptyText>
              )}
              {(c.assignments ?? []).map((assignment) => (
                <div key={assignment.id} className="min-w-0 rounded-lg border p-3 text-sm">
                  <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
                    {assignment.satgas_name ?? `Satgas #${assignment.satgas_id}`}
                  </div>
                  <div className="mt-1 text-xs text-muted-foreground">
                    {assignment.is_lead ? t("dashboard:cases.leadSatgas") : t("dashboard:cases.assignedSatgas")} - {formatDate(assignment.assigned_at, i18n.language)}
                  </div>
                </div>
              ))}
              {canManageAssignments && !c.closed_at && (
                <>
                  <SatgasAssignmentAction
                    mode="assign-case"
                    targetId={c.id}
                    currentSatgasIds={activeAssignments.map((assignment) => assignment.satgas_id)}
                    currentLeadSatgasId={
                      activeAssignments.find((assignment) => assignment.is_lead)?.satgas_id ?? null
                    }
                  />
                  <p className="text-xs text-muted-foreground">
                    {t("dashboard:reports.satgasHint")}
                  </p>
                </>
              )}
              {roleCode === "satgas_ppks" && (
                <p className="text-xs text-muted-foreground">
                  {t("dashboard:cases.assignmentManagedBy")}
                </p>
              )}
            </CardContent>
          </Card>

          {hasGeneralActions && (
            <Card className="min-w-0">
              <CardHeader>
                <CardTitle className="text-base">{t("dashboard:common.actions")}</CardTitle>
                <CardDescription>{t("dashboard:cases.actionsDesc")}</CardDescription>
              </CardHeader>
              <CardContent className="min-w-0 space-y-3">
                {canUseSatgasActions && (
                  <CaseStatusAction
                    caseId={c.id}
                    currentStatus={c.status_code}
                    onAvailabilityChange={handleCaseStatusAvailability}
                  />
                )}
                {canUseSatgasActions && c.status === "assessment" && (
                <CaseAssessmentAction
                  caseId={c.id}
                  currentRiskCode={c.risk_level_code}
                  currentPriorityCode={c.priority}
                  hasAssessment={Boolean(c.assessment_at || c.risk_level_code || c.priority)}
                />
                )}
                {canCreateInvestigation && (
                  <InvestigationCreateAction caseId={c.id} assignments={activeAssignments} />
                )}
                {canCreateRecommendation && latestCompletedInvestigation && (
                  <RecommendationCreateAction caseId={c.id} investigation={latestCompletedInvestigation} />
                )}
                {canCreateDecision && submittedDecisionRecommendation && (
                  <DecisionCreateAction caseId={c.id} recommendation={submittedDecisionRecommendation} />
                )}
                {canCreateRecovery && finalizedDecisionForRecovery && (
                  <RecoveryCreateAction caseId={c.id} decision={finalizedDecisionForRecovery} />
                )}
              </CardContent>
            </Card>
          )}

          <Card className="min-w-0">
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.currentStatusTitle")}</CardTitle>
              <CardDescription>{t("dashboard:cases.currentStatusDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              <StatusBadge status={c.status ?? c.status_code} />
              {((c.risk_level ?? c.risk_level_code) || c.priority) && (
                <div className="flex flex-wrap items-center gap-2">
                  {(c.risk_level ?? c.risk_level_code) && (
                    <RiskLevelBadge value={c.risk_level ?? c.risk_level_code} />
                  )}
                  {c.priority && <PriorityLevelBadge value={c.priority} />}
                </div>
              )}
              <div className="min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
                {t("dashboard:common.stage")}: {c.current_stage_label ?? formatCaseStatus(t, c.current_stage ?? c.status ?? c.status_code)}
              </div>
            </CardContent>
          </Card>

          <Card className="min-w-0">
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.nextStep.title")}</CardTitle>
              <CardDescription>{t("dashboard:cases.nextStep.desc")}</CardDescription>
            </CardHeader>
            <CardContent className="min-w-0">
              <p className="min-w-0 break-words text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
                {nextStepText}
              </p>
            </CardContent>
          </Card>

        </div>
      </div>
    </div>
  );
}

function SensitiveReportSection({ report, roleLabel, t }: { report: unknown; roleLabel: string; t: TFunction }) {
  if (!report || typeof report !== "object") {
    return (
      <Card className="min-w-0">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Lock className="h-4 w-4" /> {t("dashboard:cases.sensitiveReport")}
          </CardTitle>
          <CardDescription>{t("dashboard:cases.restrictedDetail", { roleLabel })}</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const data = report as Record<string, unknown>;
  const respondent = data.respondent as { name?: string | null; details?: string | null } | undefined;

  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle className="text-base">{t("dashboard:cases.sensitiveReport")}</CardTitle>
        <CardDescription>{t("dashboard:cases.sensitiveDesc")}</CardDescription>
      </CardHeader>
      <CardContent className="min-w-0 space-y-4 text-sm">
        <Field label={t("dashboard:cases.chronology")}>{asText(data.chronology)}</Field>
        <div className="grid min-w-0 gap-4 sm:grid-cols-2">
          <Field label={t("dashboard:cases.incidentDate")}>{asText(data.incident_date)}</Field>
          <Field label={t("dashboard:cases.incidentTime")}>{asText(data.incident_time)}</Field>
          <Field label={t("dashboard:cases.incidentLocation")}>{asText(data.incident_location)}</Field>
          <Field label={t("dashboard:cases.witnessInfo")}>{asText(data.witness_info)}</Field>
        </div>
        <Separator />
        <div className="grid min-w-0 gap-4 sm:grid-cols-2">
          <Field label={t("dashboard:cases.respondentName")}>{respondent?.name ?? "-"}</Field>
          <Field label={t("dashboard:cases.respondentDetails")}>{respondent?.details ?? "-"}</Field>
        </div>
      </CardContent>
    </Card>
  );
}

function InvestigationsSection({
  investigations,
  loading,
  canAddActivity,
  canTransitionStatus,
  caseId,
  language,
  roleLabel,
  t,
}: {
  investigations: Investigation[];
  loading: boolean;
  canAddActivity: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  language: string;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={FileSearch} title={t("dashboard:sections.investigations")} loading={loading} empty={investigations.length === 0} t={t}>
      {investigations.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.investigationNumber", { id: item.id })}</div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="investigation" status={item.status} />
              {canAddActivity && <InvestigationActivityAction investigation={item} caseId={caseId} />}
              {canTransitionStatus && item.status !== "completed" && (
                <InvestigationStatusAction investigation={item} caseId={caseId} />
              )}
            </div>
          </div>
          <div className="mt-2 grid min-w-0 gap-2 text-muted-foreground sm:grid-cols-2">
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.lead")}: {item.lead_investigator?.name ?? t("dashboard:common.metadataUnavailable")}</div>
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.started")}: {formatDate(item.started_at, language)}</div>
          </div>
          {item.plan_summary || item.findings || item.conclusion ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.plan_summary && <Field label={t("dashboard:sections.planSummary")}>{item.plan_summary}</Field>}
              {item.findings && <Field label={t("dashboard:sections.findings")}>{item.findings}</Field>}
              {item.conclusion && <Field label={t("dashboard:sections.conclusion")}>{item.conclusion}</Field>}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function RecommendationsSection({
  recommendations,
  loading,
  canUpdate,
  canTransitionStatus,
  caseId,
  roleLabel,
  t,
}: {
  recommendations: Recommendation[];
  loading: boolean;
  canUpdate: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={ClipboardList} title={t("dashboard:sections.recommendations")} loading={loading} empty={recommendations.length === 0} t={t}>
      {recommendations.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.recommendationNumber", { id: item.id })}</div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="recommendation" status={item.status} />
              {canUpdate && canEditRecommendation(item) && hasRecommendationDetail(item) && (
                <RecommendationUpdateAction recommendation={item} caseId={caseId} />
              )}
              {canTransitionStatus && <RecommendationStatusAction recommendation={item} caseId={caseId} />}
            </div>
          </div>
          <div className="mt-2 min-w-0 break-words text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.author")}: {item.author?.name ?? t("dashboard:common.metadataUnavailable")}</div>
          {item.conclusion || item.recommended_actions ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.conclusion && <Field label={t("dashboard:sections.conclusion")}>{item.conclusion}</Field>}
              {item.recommended_actions && <Field label={t("dashboard:sections.recommendedActions")}>{item.recommended_actions}</Field>}
              {item.sanction_recommendation && <Field label={t("dashboard:sections.sanction")}>{item.sanction_recommendation}</Field>}
              {item.recovery_recommendation && <Field label={t("dashboard:sections.recovery")}>{item.recovery_recommendation}</Field>}
              {item.prevention_recommendation && <Field label={t("dashboard:sections.prevention")}>{item.prevention_recommendation}</Field>}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function DecisionsSection({
  decisions,
  loading,
  canUpdate,
  canTransitionStatus,
  caseId,
  language,
  roleLabel,
  t,
}: {
  decisions: Decision[];
  loading: boolean;
  canUpdate: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  language: string;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={Scale} title={t("dashboard:sections.decisions")} loading={loading} empty={decisions.length === 0} t={t}>
      {decisions.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.decisionNumber", { id: item.id })}</div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="decision" status={item.status} />
              {canUpdate && canEditDecision(item) && <DecisionUpdateAction decision={item} caseId={caseId} />}
              {canTransitionStatus && item.status !== "finalized" && (
                <DecisionStatusAction decision={item} caseId={caseId} />
              )}
            </div>
          </div>
          <div className="mt-2 grid min-w-0 gap-2 text-muted-foreground sm:grid-cols-2">
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.outcome")}: {formatDecisionOutcome(t, item.outcome_code)}</div>
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.date")}: {formatDate(item.decision_date, language)}</div>
          </div>
          {item.decision_summary || item.decision_content ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.decision_summary && <Field label={t("dashboard:sections.summary")}>{item.decision_summary}</Field>}
              {item.decision_content && <Field label={t("dashboard:sections.content")}>{item.decision_content}</Field>}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function RecoveriesSection({
  recoveries,
  loading,
  canAddMonitoring,
  canTransitionStatus,
  caseId,
  roleLabel,
  t,
}: {
  recoveries: Recovery[];
  loading: boolean;
  canAddMonitoring: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={BriefcaseMedical} title={t("dashboard:sections.recoveries")} loading={loading} empty={recoveries.length === 0} t={t}>
      {recoveries.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">{item.recovery_type?.name ?? t("dashboard:sections.recoveryNumber", { id: item.id })}</div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="recovery" status={item.status} />
              {canAddMonitoring && item.status === "ongoing" && (
                <RecoveryMonitoringAction recovery={item} caseId={caseId} />
              )}
              {canTransitionStatus && !isTerminalRecovery(item) && (
                <RecoveryStatusAction recovery={item} caseId={caseId} />
              )}
            </div>
          </div>
          {item.recovery_plan || item.support_needs || item.notes ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.recovery_plan && <Field label={t("dashboard:sections.plan")}>{item.recovery_plan}</Field>}
              {item.support_needs && <Field label={t("dashboard:sections.supportNeeds")}>{item.support_needs}</Field>}
              {item.notes && <Field label={t("dashboard:sections.notes")}>{item.notes}</Field>}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function EvidenceSection({
  evidences,
  loading,
  error,
  onRetry,
  canAccess,
  canUpdate,
  canDownload,
  createInvestigation,
  language,
  roleLabel,
  t,
}: {
  evidences: EvidenceMetadata[];
  loading: boolean;
  error: boolean;
  onRetry: () => void;
  canAccess: boolean;
  canUpdate: boolean;
  canDownload: boolean;
  createInvestigation: Investigation | null;
  language: string;
  roleLabel: string;
  t: TFunction;
}) {
  if (!canAccess) {
    return (
      <Card className="min-w-0">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <FileArchive className="h-4 w-4" /> {t("dashboard:sections.evidenceTitle")}
          </CardTitle>
          <CardDescription>{t("dashboard:cases.restrictedDetail", { roleLabel })}</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const createAction = createInvestigation ? (
    <EvidenceCreateAction investigation={createInvestigation} />
  ) : null;

  return (
    <Card className="min-w-0 overflow-hidden">
      <CardHeader className="gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0 space-y-1.5">
          <CardTitle className="flex items-center gap-2 text-base">
            <FileArchive className="h-4 w-4 shrink-0" /> {t("dashboard:sections.evidenceTitle")}
          </CardTitle>
          <CardDescription>{t("dashboard:sections.evidenceCount", { count: evidences.length })}</CardDescription>
        </div>
        {evidences.length > 0 ? createAction : null}
      </CardHeader>
      <CardContent className="min-w-0 space-y-4">
        {loading ? (
          <div className="space-y-3">
            <Skeleton className="h-32 w-full" />
            <Skeleton className="h-32 w-full" />
          </div>
        ) : error ? (
          <div className="flex flex-col items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-start gap-3">
              <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" aria-hidden="true" />
              <p className="break-words text-sm text-destructive">{t("dashboard:sections.evidenceLoadError")}</p>
            </div>
            <Button variant="outline" size="sm" onClick={onRetry}>
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
              {t("dashboard:sections.custodyRetry")}
            </Button>
          </div>
        ) : evidences.length === 0 ? (
          <EmptyState
            icon={FileArchive}
            title={t("dashboard:sections.evidenceEmptyTitle")}
            description={t("dashboard:sections.evidenceEmptyDesc")}
            action={createInvestigation ? <EvidenceCreateAction investigation={createInvestigation} /> : undefined}
          />
        ) : (
          <div className="space-y-3">
            {evidences.map((item) => (
              <div key={item.id} className="min-w-0 space-y-4 rounded-lg border p-4 text-sm">
                <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">{item.title}</div>
                    <div className="mt-1 min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
                      {formatEvidenceType(t, item.evidence_type?.code ?? item.evidence_type?.name)}
                    </div>
                  </div>
                  <WorkflowStatusBadge family="evidence" status={item.status} className="w-fit shrink-0" />
                </div>
                <div className="grid min-w-0 gap-3 text-muted-foreground sm:grid-cols-2">
                  <Field label={t("dashboard:sections.classification")}>
                    {formatEvidenceClassification(t, item.classification ?? "-")}
                  </Field>
                  <Field label={t("dashboard:sections.collected")}>
                    {formatDate(item.collected_at, language)}
                  </Field>
                  {item.source && (
                    <div className="min-w-0 sm:col-span-2">
                      <Field label={t("dashboard:sections.source")}>{item.source}</Field>
                    </div>
                  )}
                  {item.description && (
                    <div className="min-w-0 sm:col-span-2">
                      <Field label={t("dashboard:sections.description")}>{item.description}</Field>
                    </div>
                  )}
                </div>
                {canUpdate && (
                  <div className="flex min-w-0 flex-wrap gap-2">
                    {item.status !== "archived" && <EvidenceMetadataAction evidence={item} />}
                    <EvidenceStatusAction evidence={item} />
                  </div>
                )}
                <EvidenceFileAttachment
                  evidence={item}
                  canUpload={canUpdate}
                  canDownload={canDownload}
                  language={language}
                />
                <EvidenceCustodyDisclosure evidenceId={item.id} language={language} />
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function SectionCard({
  icon: Icon,
  title,
  loading,
  empty,
  children,
  t,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  loading: boolean;
  empty: boolean;
  children: React.ReactNode;
  t: TFunction;
}) {
  return (
    <Card className="min-w-0">
      <CardHeader className="min-w-0">
        <CardTitle className="flex min-w-0 items-center gap-2 text-base">
          <Icon className="h-4 w-4" /> {title}
        </CardTitle>
        <CardDescription>{t("dashboard:common.readOnlyOperationalData")}</CardDescription>
      </CardHeader>
      <CardContent className="min-w-0 space-y-3">
        {loading && <EmptyText>{t("dashboard:sections.loading", { name: title.toLowerCase() })}</EmptyText>}
        {!loading && empty && <EmptyText>{t("dashboard:sections.empty", { name: title.toLowerCase() })}</EmptyText>}
        {!loading && !empty && children}
      </CardContent>
    </Card>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 min-w-0 break-words text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">{children}</div>
    </div>
  );
}

function MetadataOnlyText({ roleLabel, t }: { roleLabel: string; t: TFunction }) {
  return (
    <div className="mt-3 text-xs text-muted-foreground">
      {t("dashboard:cases.restrictedDetail", { roleLabel })}
    </div>
  );
}

function EmptyText({ children }: { children: React.ReactNode }) {
  return <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">{children}</div>;
}

function defaultWorkflowTabForCase(caseRecord: CaseRecord): WorkflowTab {
  const values = [
    caseRecord.current_stage,
    caseRecord.current_stage_label,
    caseRecord.status,
    caseRecord.status_label,
    caseRecord.status_code,
  ];

  for (const value of values) {
    const tab = WORKFLOW_TAB_BY_TOKEN[normalizeWorkflowToken(value)];

    if (tab && isWorkflowTab(tab)) {
      return tab;
    }
  }

  return WORKFLOW_TAB_FALLBACK;
}

function restrictedRoleLabel(t: TFunction, roleCode: string | null | undefined) {
  const code = roleCode ?? "";

  return (RESTRICTED_ROLE_CODES as readonly string[]).includes(code)
    ? t(`dashboard:cases.restrictedRoles.${code}`)
    : t("dashboard:cases.restrictedRoles.unknown");
}

function nextStepMessage(
  t: TFunction,
  caseRecord: CaseRecord,
  isAssignedSatgas: boolean,
  roleCode: string | null | undefined,
) {
  const token = normalizeWorkflowToken(caseRecord.status ?? caseRecord.status_code);
  const fallback = t("dashboard:cases.nextStep.fallback");

  if (!(NEXT_STEP_STATUSES as readonly string[]).includes(token)) {
    return fallback;
  }

  const audiences = isAssignedSatgas
    ? ["satgas", "all"]
    : roleCode === "super_admin"
      ? ["superAdmin", "admin", "all"]
      : roleCode === "admin"
        ? ["admin", "all"]
        : ["all"];

  for (const audience of audiences) {
    const text = t(`dashboard:cases.nextStep.${token}.${audience}`, { defaultValue: "" });

    if (text) {
      return text;
    }
  }

  return fallback;
}

function normalizeWorkflowToken(value: unknown) {
  if (value === null || value === undefined) {
    return "";
  }

  return String(value)
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
}

function isWorkflowTab(value: string): value is WorkflowTab {
  return (WORKFLOW_TABS as readonly string[]).includes(value);
}

function asText(value: unknown) {
  return typeof value === "string" && value ? value : "-";
}

function hasRecommendationDetail(item: Recommendation) {
  return item.conclusion !== undefined && item.recommended_actions !== undefined;
}

function canEditRecommendation(item: Recommendation) {
  return item.status === "drafting" || item.status === "revised";
}

function canEditDecision(item: Decision) {
  return item.status === "draft";
}

function isTerminalRecovery(item: Recovery) {
  return item.status === "completed" || item.status === "discontinued";
}

function mostRecentCompletedInvestigation(items: Investigation[]) {
  const completed = items.filter((item) => item.status === "completed" && item.completed_at);

  if (completed.length === 0) {
    return null;
  }

  return [...completed].sort((a, b) => {
    const aTime = a.completed_at ? new Date(a.completed_at).getTime() : 0;
    const bTime = b.completed_at ? new Date(b.completed_at).getTime() : 0;

    return bTime - aTime;
  })[0];
}

function selectEvidenceInvestigation(items: Investigation[]) {
  return (
    items.find((item) => {
      const status = normalizeWorkflowToken(item.status ?? item.status_code);
      return status !== "completed" && status !== "invs_08";
    }) ?? null
  );
}

function submittedRecommendationForDecision(items: Recommendation[]) {
  return items.find((item) => item.status === "submitted_to_leader") ?? null;
}

function finalizedDecision(items: Decision[]) {
  return items.find((item) => item.status === "finalized") ?? null;
}

/**
 * Derives the internal case progress timeline from data already returned by
 * approved read endpoints. Events without a real timestamp are omitted —
 * never fabricated. Result is ordered chronologically (oldest first) so the
 * shared ProgressTimeline can emphasize the most recent event.
 */
function caseProgressEvents(
  t: TFunction,
  language: string,
  caseRecord: CaseRecord,
  data: {
    investigations: Investigation[];
    recommendations: Recommendation[];
    decisions: Decision[];
    recoveries: Recovery[];
  },
): ProgressTimelineEvent[] {
  const earliest = (values: Array<string | null | undefined>): string | null => {
    const sorted = values
      .filter((value): value is string => Boolean(value))
      .filter((value) => !Number.isNaN(new Date(value).getTime()))
      .sort((a, b) => new Date(a).getTime() - new Date(b).getTime());

    return sorted[0] ?? null;
  };

  const candidates = [
    {
      id: "report-submitted",
      key: "reportSubmitted",
      icon: Send,
      at: caseRecord.report_submitted_at ?? null,
    },
    {
      id: "forwarded",
      key: "forwarded",
      icon: Share2,
      at: caseRecord.forwarded_at,
    },
    {
      id: "satgas-assigned",
      key: "satgasAssigned",
      icon: Users,
      at: earliest((caseRecord.assignments ?? []).map((assignment) => assignment.assigned_at)),
    },
    {
      id: "investigation-created",
      key: "investigationCreated",
      icon: FileSearch,
      at: earliest(data.investigations.map((item) => item.created_at ?? item.started_at)),
    },
    {
      id: "recommendation-submitted",
      key: "recommendationSubmitted",
      icon: ClipboardList,
      at: earliest(data.recommendations.map((item) => item.submitted_at)),
    },
    {
      id: "decision-finalized",
      key: "decisionFinalized",
      icon: Gavel,
      at: earliest(data.decisions.map((item) => item.finalized_at)),
    },
    {
      id: "recovery-completed",
      key: "recoveryCompleted",
      icon: BriefcaseMedical,
      at: earliest(data.recoveries.map((item) => item.completed_at)),
    },
    {
      id: "case-closed",
      key: "caseClosed",
      icon: CheckCircle2,
      at: caseRecord.closed_at ?? null,
    },
  ];

  return candidates
    .flatMap((candidate) => (candidate.at ? [{ ...candidate, at: candidate.at }] : []))
    .sort((a, b) => new Date(a.at).getTime() - new Date(b.at).getTime())
    .map((candidate) => ({
      id: candidate.id,
      title: t(`dashboard:cases.progress.events.${candidate.key}`),
      timestamp: formatDate(candidate.at, language),
      description: t(`dashboard:cases.progress.events.${candidate.key}Desc`),
      icon: candidate.icon,
    }));
}

function formatDate(value: string | null | undefined, language: string) {
  return formatDateTime(value, language);
}

function formatRiskValue(t: TFunction, value: unknown) {
  if (!value) return "-";
  return formatRiskLevel(t, value);
}

function formatPriorityValue(t: TFunction, value: unknown) {
  if (!value) return "-";
  return formatPriorityLevel(t, value);
}

function CaseDetailSkeleton() {
  return (
    <div className="space-y-6" aria-busy="true" aria-live="polite">
      <Skeleton className="h-4 w-64" />
      <div className="flex flex-wrap items-center gap-3">
        <Skeleton className="h-8 w-24" />
        <Skeleton className="h-6 w-32" />
        <Skeleton className="h-6 w-24" />
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <div className="rounded-lg border p-5 space-y-3">
            <Skeleton className="h-5 w-40" />
            <Skeleton className="h-3 w-64" />
            <div className="grid gap-3 sm:grid-cols-2 pt-2">
              {Array.from({ length: 8 }).map((_, index) => (
                <div key={index} className="space-y-2">
                  <Skeleton className="h-3 w-24" />
                  <Skeleton className="h-4 w-40" />
                </div>
              ))}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            {Array.from({ length: 5 }).map((_, index) => (
              <Skeleton key={index} className="h-9 w-28" />
            ))}
          </div>
          <div className="rounded-lg border p-5 space-y-3">
            <Skeleton className="h-5 w-32" />
            <Skeleton className="h-3 w-56" />
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-20 w-full" />
          </div>
        </div>
        <div className="space-y-4">
          <div className="rounded-lg border p-5 space-y-3">
            <Skeleton className="h-5 w-32" />
            <Skeleton className="h-6 w-28" />
            <Skeleton className="h-3 w-40" />
          </div>
          <div className="rounded-lg border p-5 space-y-3">
            <Skeleton className="h-5 w-32" />
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
          </div>
          <div className="rounded-lg border p-5 space-y-3">
            <Skeleton className="h-5 w-28" />
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-9 w-full" />
          </div>
        </div>
      </div>
    </div>
  );
}
