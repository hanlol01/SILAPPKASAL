import { createFileRoute, Link } from "@tanstack/react-router";
import { useQueries, useQuery } from "@tanstack/react-query";
import { useCallback, useEffect, useRef, useState } from "react";
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
  Lightbulb,
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
import { ReportInputDetailsContent } from "@/components/report-input-details";
import { EmptyState } from "@/components/empty-state";
import { EvidenceCustodyDisclosure } from "@/components/evidence-custody-disclosure";
import { EvidenceFileAttachment } from "@/components/evidence-file-attachment";
import {
  PriorityLevelBadge,
  RiskLevelBadge,
  StatusBadge,
  WorkflowStatusBadge,
} from "@/components/status-badge";
import {
  ProgressTimeline,
  ProgressTimelineSkeleton,
  type ProgressTimelineEvent,
} from "@/components/progress-timeline";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import { InvestigationStageProgress } from "@/components/workflow/investigation-stage-progress";
import { CaseEmergencyAccessCard } from "@/components/security/case-emergency-access-card";
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
import {
  RecommendationReviewActions,
  RecommendationSubmitAction,
} from "@/components/workflow-actions/recommendation-review-actions";
import { CaseAssessmentAction } from "@/components/workflow-actions/case-assessment-action";
import { EvidenceCreateAction } from "@/components/workflow-actions/evidence-create-action";
import { RecoveryCreateAction } from "@/components/workflow-actions/recovery-create-action";
import { RecoveryStatusAction } from "@/components/workflow-actions/recovery-status-action";
import {
  CaseClosureAction,
  CaseFinalSummaryActions,
  CaseFinalSummaryCard,
} from "@/components/workflow-actions/case-final-summary";
import { CaseMinuteCard } from "@/components/workflow-actions/case-minute";
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
import { formatDate, formatDateTime } from "@/lib/format";
import {
  formatCaseStatus,
  formatDecisionOutcome,
  formatEvidenceClassification,
  formatEvidenceType,
  formatInvestigationStatus,
  formatPriorityLevel,
  formatRecoveryType,
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
import type { ReportInputDetails } from "@/lib/report-input-types";

const WORKFLOW_TABS = [
  "investigation",
  "recommendation",
  "decision",
  "recovery",
  "evidence",
] as const;
type WorkflowTab = (typeof WORKFLOW_TABS)[number];

const WORKFLOW_TAB_FALLBACK: WorkflowTab = "investigation";
const CASE_WORKFLOW_TAB_CLASS =
  "relative min-w-28 flex-1 shrink-0 whitespace-nowrap rounded-none bg-transparent px-3 py-2 shadow-none after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:origin-center after:scale-x-0 after:rounded-full after:bg-primary after:transition-transform hover:bg-muted/60 hover:text-foreground motion-reduce:transition-none motion-reduce:after:transition-none data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none data-[state=active]:after:scale-x-100";
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
  const [workflowTabSelection, setWorkflowTabSelection] = useState<{
    context: string;
    value: WorkflowTab;
  } | null>(null);
  const workflowTabListRef = useRef<HTMLDivElement | null>(null);
  const caseQuery = useQuery({
    queryKey: operationsQueryKeys.case(id),
    queryFn: () => getCase(id),
  });
  const isAssignedSatgas =
    roleCode === "satgas_ppks" &&
    (caseQuery.data?.assignments ?? []).some(
      (assignment) => assignment.is_active && assignment.satgas_id === user?.id,
    );
  const isSensitiveOversight =
    roleCode === "super_admin" &&
    caseQuery.data?.workflow_context?.facts.sensitive_oversight_enabled === true;
  const canViewEvidence =
    isSensitiveOversight ||
    (isAssignedSatgas && Boolean(user?.permissions?.includes("evidence.view.case")));
  const canViewReporterEvidence =
    isSensitiveOversight ||
    (isAssignedSatgas &&
      user?.is_active === true &&
      Boolean(user.permissions?.includes("reporter_evidence.read.assigned")));
  const canDownloadReporterEvidence =
    isSensitiveOversight ||
    (canViewReporterEvidence &&
      Boolean(user?.permissions?.includes("reporter_evidence.download.assigned")));
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
    const frame = window.requestAnimationFrame(() => {
      scrollActiveWorkflowTabIntoView(workflowTabListRef.current);
    });

    return () => window.cancelAnimationFrame(frame);
  }, [
    caseQuery.data?.id,
    caseQuery.data?.status,
    caseQuery.data?.status_code,
    workflowTabSelection,
  ]);

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
    return (
      <QueryErrorState message={t("dashboard:cases.error")} onRetry={() => caseQuery.refetch()} />
    );
  }

  const c = caseQuery.data;
  const workflowContext = c.workflow_context;
  const caseStatusToken = normalizeWorkflowToken(c.status ?? c.status_code);
  const isOperationallyTerminalCase = ["closed", "csts_14", "withdrawn", "csts_16"].includes(
    caseStatusToken,
  );
  const operationallyPaused = workflowContext?.facts.operationally_paused !== false;
  const canUseEmergencyAccess =
    isAssignedSatgas &&
    user?.is_active === true &&
    Boolean(user.permissions?.includes("privacy.request_break_glass")) &&
    !operationallyPaused &&
    !isOperationallyTerminalCase;
  const workflowTabContext = `${c.id}:${normalizeWorkflowToken(c.status ?? c.status_code)}`;
  const activeWorkflowTab =
    workflowTabSelection?.context === workflowTabContext
      ? workflowTabSelection.value
      : defaultWorkflowTabForCase(c);

  function handleWorkflowTabChange(value: string) {
    if (!isWorkflowTab(value)) return;
    setWorkflowTabSelection({ context: workflowTabContext, value });
  }
  const canManageAssignments =
    roleCode === "admin" &&
    c.assignment_capabilities?.manage.allowed === true;
  const canUseSatgasActions =
    isAssignedSatgas && !operationallyPaused && !isOperationallyTerminalCase;
  const canRecommend =
    canUseSatgasActions && Boolean(user?.permissions?.includes("cases.recommend"));
  const canReviewRecommendation =
    roleCode === "admin" &&
    user?.is_active === true &&
    Boolean(user.permissions?.includes("cases.review_recommendation")) &&
    workflowContext?.actions.review_recommendation.allowed === true;
  const canManageDecisionActions =
    roleCode === "admin" &&
    user?.is_active === true &&
    Boolean(user.permissions?.includes("cases.record_decision")) &&
    workflowContext?.facts.same_campus_admin === true &&
    !operationallyPaused &&
    !isOperationallyTerminalCase;
  const canManageRecoveryActions =
    roleCode === "admin" &&
    Boolean(user?.permissions?.includes("cases.monitor")) &&
    workflowContext?.actions.manage_recovery.allowed === true;
  const canAddRecoveryMonitoring =
    roleCode === "satgas_ppks" &&
    workflowContext?.actions.add_monitoring.allowed === true;
  const activeAssignments = (c.assignments ?? []).filter((assignment) => assignment.is_active);
  const pastAssignments = (c.assignment_history ?? []).filter((assignment) => !assignment.is_active);
  const evidenceInvestigation = selectEvidenceInvestigation(investigationsQuery.data ?? []);
  const canUpdateEvidence =
    canUseSatgasActions && Boolean(user?.permissions?.includes("evidence.upload"));
  const canDownloadEvidence =
    isSensitiveOversight ||
    (isAssignedSatgas && Boolean(user?.permissions?.includes("evidence.download")));
  const canCreateEvidence =
    workflowContext?.actions.add_evidence.allowed === true && evidenceInvestigation !== null;
  const latestCompletedInvestigation = mostRecentCompletedInvestigation(
    investigationsQuery.data ?? [],
  );
  const acceptedDecisionRecommendation = acceptedRecommendationForDecision(
    recommendationsQuery.data ?? [],
  );
  const decisionsLoaded =
    recommendationsQuery.isSuccess && decisionQueries.every((query) => query.isSuccess);
  const finalizedDecisionForRecovery = finalizedDecision(decisions);
  const canCreateInvestigation =
    investigationsQuery.isSuccess && workflowContext?.actions.create_investigation.allowed === true;
  const canCreateRecommendation =
    investigationsQuery.isSuccess &&
    recommendationsQuery.isSuccess &&
    workflowContext?.actions.create_recommendation.allowed === true &&
    latestCompletedInvestigation !== null;
  const canCreateDecision =
    canManageDecisionActions &&
    workflowContext?.actions.create_decision.allowed === true &&
    recommendationsQuery.isSuccess &&
    decisionsLoaded &&
    c.status === "decision" &&
    acceptedDecisionRecommendation !== null &&
    decisions.length === 0;
  const canCreateRecovery =
    canManageRecoveryActions &&
    decisionsLoaded &&
    finalizedDecisionForRecovery !== null &&
    recoveries.length === 0 &&
    !isOperationallyTerminalCase;
  const isLifecycleControlledCase = ["recommendation", "csts_09", "decision", "csts_10"].includes(
    caseStatusToken,
  );
  const canUseGenericCaseStatus =
    canUseSatgasActions &&
    !isLifecycleControlledCase &&
    workflowContext?.actions.update_case_status.allowed === true;
  const hasGeneralActions = Boolean(workflowContext) ||
    (canUseGenericCaseStatus && caseStatusAvailable) ||
    canCreateRecovery || canCreateDecision;
  const restrictedLabel = restrictedRoleLabel(t, roleCode);
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
      {roleCode === "super_admin" && (
        <div className="rounded-lg border border-primary/25 bg-primary/5 p-4 text-sm">
          <div className="flex items-start gap-3">
            <Lock className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
            <div className="min-w-0">
              <p className="font-medium">{t("dashboard:workflow.oversightReadOnly")}</p>
              {!isSensitiveOversight && (
                <p className="mt-1 text-muted-foreground">
                  {t("dashboard:workflow.sensitiveOversightUnavailable")}
                </p>
              )}
            </div>
          </div>
        </div>
      )}
      {roleCode === "satgas_ppks" && workflowContext?.facts.operationally_paused === true && (
        <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm" role="status">
          {t("dashboard:withdrawals.satgasPendingBanner")}
        </div>
      )}
      {roleCode === "satgas_ppks" && isOperationallyTerminalCase && ["withdrawn", "csts_16"].includes(caseStatusToken) && (
        <div className="rounded-lg border border-muted-foreground/30 bg-muted p-4 text-sm" role="status">
          <p className="font-medium">{t("dashboard:withdrawals.satgasWithdrawnBanner")}</p>
          {c.withdrawn_at && <p className="mt-1 text-muted-foreground">{formatDateTime(c.withdrawn_at, i18n.language)}</p>}
        </div>
      )}

      <div className="grid min-w-0 gap-4 lg:grid-cols-3">
        <div className="min-w-0 space-y-4 lg:col-span-2">
          <CollapsibleDataCard
            title={t("dashboard:cases.metadata")}
            description={t("dashboard:cases.metadataDesc")}
            contentClassName="grid gap-4 text-sm sm:grid-cols-2"
          >
            <Field label={t("dashboard:cases.caseNumber")}>{c.case_number}</Field>
            <Field label={t("dashboard:reports.registration")}>{c.registration_number}</Field>
            <Field label={t("dashboard:common.status")}>
              {formatCaseStatus(t, c.status ?? c.status_code)}
            </Field>
            <Field label={t("dashboard:common.stage")}>
              {c.current_stage_label ?? formatCaseStatus(t, c.current_stage ?? "-")}
            </Field>
            <Field label={t("dashboard:common.risk")}>
              {(c.risk_level ?? c.risk_level_code) ? (
                <RiskLevelBadge value={c.risk_level ?? c.risk_level_code} />
              ) : (
                formatRiskValue(t, c.risk_level ?? c.risk_level_code)
              )}
            </Field>
            <Field label={t("dashboard:common.priority")}>
              {c.priority ? (
                <PriorityLevelBadge value={c.priority} />
              ) : (
                formatPriorityValue(t, c.priority)
              )}
            </Field>
            <Field label={t("dashboard:common.forwarded")}>
              {formatDateTime(c.forwarded_at, i18n.language)}
            </Field>
            <Field label={t("dashboard:common.closed")}>
              {formatDateTime(c.closed_at, i18n.language)}
            </Field>
            {c.withdrawn_at && (
              <Field label={t("dashboard:withdrawals.withdrawnAt")}>
                {formatDateTime(c.withdrawn_at, i18n.language)}
              </Field>
            )}
          </CollapsibleDataCard>

          <CollapsibleDataCard
            icon={History}
            title={t("dashboard:cases.progress.title")}
            description={t("dashboard:cases.progress.desc")}
          >
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
          </CollapsibleDataCard>

          <SensitiveReportSection report={c.report} roleLabel={restrictedLabel} t={t} />
          {canUseEmergencyAccess && c.report?.identification.report_type === "anonymous" && (
            <CaseEmergencyAccessCard
              caseId={c.id}
              registrationNumber={c.registration_number}
            />
          )}
          <CaseFinalSummaryCard caseId={c.id} language={i18n.language} />
          <CaseMinuteCard caseId={c.id} language={i18n.language} />
          <Tabs
            value={activeWorkflowTab}
            onValueChange={handleWorkflowTabChange}
            className="w-full min-w-0"
          >
            <div
              className="w-full min-w-0 overscroll-x-contain overflow-x-auto"
              ref={workflowTabListRef}
            >
              <TabsList className="h-auto w-full min-w-[36rem] flex-nowrap justify-start rounded-none border-b border-border bg-transparent p-0 sm:min-w-full">
                <TabsTrigger value="investigation" className={CASE_WORKFLOW_TAB_CLASS}>
                  {t("dashboard:cases.tabInvestigation")}
                </TabsTrigger>
                <TabsTrigger value="recommendation" className={CASE_WORKFLOW_TAB_CLASS}>
                  {t("dashboard:cases.tabRecommendation")}
                </TabsTrigger>
                <TabsTrigger value="decision" className={CASE_WORKFLOW_TAB_CLASS}>
                  {t("dashboard:cases.tabDecision")}
                </TabsTrigger>
                <TabsTrigger value="recovery" className={CASE_WORKFLOW_TAB_CLASS}>
                  {t("dashboard:cases.tabRecovery")}
                </TabsTrigger>
                <TabsTrigger value="evidence" className={CASE_WORKFLOW_TAB_CLASS}>
                  {t("dashboard:cases.tabEvidence")}
                </TabsTrigger>
              </TabsList>
            </div>
            <TabsContent value="investigation" className="min-w-0">
              <InvestigationsSection
                investigations={investigationsQuery.data ?? []}
                loading={investigationsQuery.isLoading}
                canAddActivity={workflowContext?.actions.add_activity.allowed === true}
                canTransitionStatus={workflowContext?.actions.update_investigation_status.allowed === true}
                transitionReason={workflowReason(
                  t,
                  workflowContext?.actions.update_investigation_status.reason_code,
                  workflowContext?.facts.investigation_status,
                )}
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
                canSubmit={canRecommend}
                canReview={canReviewRecommendation}
                caseId={c.id}
                language={i18n.language}
                roleCode={roleCode}
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
                roleCode={roleCode}
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
                roleCode={roleCode}
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
                  unavailableReason={workflowReason(
                    t,
                    workflowContext?.actions.add_evidence.reason_code,
                    workflowContext?.facts.investigation_status,
                  )}
                  capabilityLoading={!workflowContext || investigationsQuery.isLoading}
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
                    {assignment.satgas_name ?? t("dashboard:common.metadataUnavailable")}
                  </div>
                  <div className="mt-1 text-xs text-muted-foreground">
                    {t("dashboard:cases.assignedSatgas")} - {formatDateTime(assignment.assigned_at, i18n.language)}
                  </div>
                </div>
              ))}
              {pastAssignments.length > 0 && (
                <div className="space-y-2 border-t pt-3">
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {t("dashboard:cases.assignmentHistory")}
                  </p>
                  {pastAssignments.map((assignment) => (
                    <div key={assignment.id} className="min-w-0 rounded-lg bg-muted/40 p-3 text-sm">
                      <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere]">
                        {assignment.satgas_name ?? t("dashboard:common.metadataUnavailable")}
                      </div>
                      <div className="mt-1 text-xs text-muted-foreground">
                        {t(`dashboard:cases.assignmentTypes.${assignment.assignment_type}`)} · {formatDateTime(assignment.assigned_at, i18n.language)} – {formatDateTime(assignment.unassigned_at, i18n.language)}
                      </div>
                      {assignment.assigned_by_name && (
                        <div className="mt-1 text-xs text-muted-foreground">
                          {t("dashboard:cases.assignedBy", { name: assignment.assigned_by_name })}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
              {canManageAssignments && (
                <>
                  <SatgasAssignmentAction
                    mode="assign-case"
                    targetId={c.id}
                    currentSatgasIds={activeAssignments.map((assignment) => assignment.satgas_id)}
                    lockVersion={c.lock_version}
                  />
                  <p className="text-xs text-muted-foreground">
                    {t("dashboard:reports.satgasHint")}
                  </p>
                </>
              )}
              {roleCode === "satgas_ppks" && (
                <p className="text-xs text-muted-foreground">
                  {t("dashboard:cases.assignmentSelfServiceInfo")}
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
                {canUseGenericCaseStatus && (
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
                    hasAssessment={Boolean(c.risk_level_code && c.priority)}
                  />
                )}
                {canCreateInvestigation && (
                  <InvestigationCreateAction caseId={c.id} />
                )}
                {!canCreateInvestigation &&
                  c.status === "investigation" &&
                  !workflowContext?.facts.investigation_exists && (
                    <UnavailableWorkflowAction
                      label={t("dashboard:workflow.createInvestigation")}
                      reason={workflowReason(
                        t,
                        workflowContext?.actions.create_investigation.reason_code,
                      )}
                    />
                  )}
                {canCreateRecommendation && latestCompletedInvestigation && (
                  <RecommendationCreateAction
                    caseId={c.id}
                    investigation={latestCompletedInvestigation}
                  />
                )}
                {canCreateRecovery && finalizedDecisionForRecovery && (
                  <RecoveryCreateAction caseId={c.id} decision={finalizedDecisionForRecovery} />
                )}
                {canCreateDecision && acceptedDecisionRecommendation && (
                  <DecisionCreateAction caseId={c.id} recommendation={acceptedDecisionRecommendation} />
                )}
                {roleCode === "admin" && ["recovery", "monitoring"].includes(c.status ?? "") && (
                  <CaseFinalSummaryActions
                    caseId={c.id}
                    createCapability={workflowContext?.actions.create_final_summary}
                    updateCapability={workflowContext?.actions.update_final_summary}
                    publishCapability={workflowContext?.actions.publish_final_summary}
                  />
                )}
                {roleCode === "satgas_ppks" && ["recovery", "monitoring"].includes(c.status ?? "") && (
                  <CaseClosureAction caseId={c.id} capability={workflowContext?.actions.finalize_closure} />
                )}
                <div className="rounded-lg border border-primary/20 bg-primary/5 p-3">
                  <div className="flex items-start gap-2">
                    <Lightbulb className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    <div className="min-w-0">
                      <div className="text-sm font-medium">{t("dashboard:workflow.tipsTitle")}</div>
                      <p className="mt-1 break-words text-sm text-muted-foreground [overflow-wrap:anywhere]">
                        {workflowTip(
                          t,
                          workflowContext?.primary_tip_code,
                          workflowContext?.facts.investigation_status,
                        )}
                      </p>
                    </div>
                  </div>
                </div>
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
                {t("dashboard:common.stage")}:{" "}
                {c.current_stage_label ??
                  formatCaseStatus(t, c.current_stage ?? c.status ?? c.status_code)}
              </div>
            </CardContent>
          </Card>

        </div>
      </div>
    </div>
  );
}

function SensitiveReportSection({
  report,
  roleLabel,
  t,
}: {
  report: unknown;
  roleLabel: string;
  t: TFunction;
}) {
  if (!report || typeof report !== "object") {
    return (
      <Card className="min-w-0">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Lock className="h-4 w-4" /> {t("dashboard:reportInputDetails.title")}
          </CardTitle>
          <CardDescription>{t("dashboard:cases.restrictedDetail", { roleLabel })}</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  return (
    <CollapsibleDataCard
      title={t("dashboard:reportInputDetails.title")}
      description={t("dashboard:reportInputDetails.description")}
    >
      <ReportInputDetailsContent
        details={report as ReportInputDetails}
        translationScope="dashboard"
      />
    </CollapsibleDataCard>
  );
}

function InvestigationsSection({
  investigations,
  loading,
  canAddActivity,
  canTransitionStatus,
  transitionReason,
  caseId,
  language,
  roleLabel,
  t,
}: {
  investigations: Investigation[];
  loading: boolean;
  canAddActivity: boolean;
  canTransitionStatus: boolean;
  transitionReason?: string;
  caseId: number | string;
  language: string;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard
      icon={FileSearch}
      title={t("dashboard:sections.investigations")}
      loading={loading}
      empty={investigations.length === 0}
      t={t}
    >
      {investigations.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:sections.investigationNumber")}
            </div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="investigation" status={item.status} />
              {canAddActivity && (
                <InvestigationActivityAction investigation={item} caseId={caseId} />
              )}
              {item.status !== "completed" && (
                <InvestigationStatusAction
                  investigation={item}
                  caseId={caseId}
                  allowed={canTransitionStatus}
                  reason={transitionReason}
                />
              )}
            </div>
          </div>
          <div className="mt-2 grid min-w-0 gap-2 text-muted-foreground sm:grid-cols-2">
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:sections.lead")}:{" "}
              {item.lead_investigator?.name ?? t("dashboard:common.metadataUnavailable")}
            </div>
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:sections.started")}: {formatDateTime(item.started_at, language)}
            </div>
          </div>
          {item.plan_summary || item.findings || item.conclusion ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.plan_summary && (
                <Field label={t("dashboard:sections.planSummary")}>{item.plan_summary}</Field>
              )}
              {item.findings && (
                <Field label={t("dashboard:sections.findings")}>{item.findings}</Field>
              )}
              {item.conclusion && (
                <Field label={t("dashboard:sections.conclusion")}>{item.conclusion}</Field>
              )}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
          {item.activities && (
            <InvestigationStageProgress investigation={item} language={language} />
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
  canSubmit,
  canReview,
  caseId,
  language,
  roleCode,
  roleLabel,
  t,
}: {
  recommendations: Recommendation[];
  loading: boolean;
  canUpdate: boolean;
  canSubmit: boolean;
  canReview: boolean;
  caseId: number | string;
  language: string;
  roleCode: string | null | undefined;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard
      icon={ClipboardList}
      title={t("dashboard:sections.recommendations")}
      loading={loading}
      empty={recommendations.length === 0}
      emptyText={t("dashboard:sections.recommendationCreateFromCaseAction")}
      t={t}
    >
      {recommendations.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:sections.recommendationNumber")}
            </div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="recommendation" status={item.status} />
              {canUpdate && canEditRecommendation(item) && hasRecommendationDetail(item) && (
                <RecommendationUpdateAction recommendation={item} caseId={caseId} />
              )}
              {canSubmit && canSubmitRecommendation(item) && (
                <RecommendationSubmitAction recommendation={item} caseId={caseId} />
              )}
              {canReview &&
                ["submitted_for_review", "submitted_to_leader"].includes(
                  normalizeWorkflowToken(item.status ?? item.status_code),
                ) && (
                <RecommendationReviewActions recommendation={item} caseId={caseId} />
              )}
            </div>
          </div>
          <div className="mt-2 min-w-0 break-words text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
            {t("dashboard:sections.author")}:{" "}
            {item.author?.name ?? t("dashboard:common.metadataUnavailable")}
          </div>
          {item.conclusion || item.recommended_actions ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.conclusion && (
                <Field label={t("dashboard:sections.conclusion")}>{item.conclusion}</Field>
              )}
              {item.recommended_actions && (
                <Field label={t("dashboard:sections.recommendedActions")}>
                  {item.recommended_actions}
                </Field>
              )}
              {item.sanction_recommendation && (
                <Field label={t("dashboard:sections.sanction")}>
                  {item.sanction_recommendation}
                </Field>
              )}
              {item.recovery_recommendation && (
                <Field label={t("dashboard:sections.recovery")}>
                  {item.recovery_recommendation}
                </Field>
              )}
              {item.prevention_recommendation && (
                <Field label={t("dashboard:sections.prevention")}>
                  {item.prevention_recommendation}
                </Field>
              )}
              {(item.review ?? item.leadership_review)?.revision_note && (
                <Field label={t("dashboard:workflow.revisionNote")}>
                  {(item.review ?? item.leadership_review)?.revision_note}
                </Field>
              )}
              {(item.review ?? item.leadership_review)?.approved_at && (
                <Field label={t("dashboard:workflow.approvalRecord")}>
                  {t("dashboard:workflow.approvedByAt", {
                    name:
                      (item.review ?? item.leadership_review)?.approved_by?.name ??
                      t("dashboard:common.metadataUnavailable"),
                    date: formatDateTime(
                      (item.review ?? item.leadership_review)?.approved_at ?? null,
                      language,
                    ),
                  })}
                </Field>
              )}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
          <p className="mt-3 min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
            {recommendationGuidance(t, item, roleCode)}
          </p>
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
  roleCode,
  roleLabel,
  t,
}: {
  decisions: Decision[];
  loading: boolean;
  canUpdate: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  language: string;
  roleCode: string | null | undefined;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard
      icon={Scale}
      title={t("dashboard:sections.decisions")}
      loading={loading}
      empty={decisions.length === 0}
      emptyText={t("dashboard:workflow.decisionCreateFromActionCard")}
      t={t}
    >
      {decisions.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
              {item.decision_number?.trim() || t("dashboard:sections.decisionNumberPending")}
            </div>
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <WorkflowStatusBadge family="decision" status={item.status} />
              {canUpdate && canEditDecision(item) && (
                <DecisionUpdateAction decision={item} caseId={caseId} />
              )}
              {canTransitionStatus && item.status !== "finalized" && (
                <DecisionStatusAction decision={item} caseId={caseId} />
              )}
            </div>
          </div>
          <div className="mt-2 grid min-w-0 gap-2 text-muted-foreground sm:grid-cols-2">
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:sections.outcome")}: {formatDecisionOutcome(t, item.outcome_code)}
            </div>
            <div className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:sections.date")}: {formatDate(item.decision_date, language)}
            </div>
          </div>
          {item.decision_summary || item.decision_content ? (
            <div className="mt-3 min-w-0 space-y-2">
              {item.decision_summary && (
                <Field label={t("dashboard:sections.summary")}>{item.decision_summary}</Field>
              )}
              {item.decision_content && (
                <Field label={t("dashboard:sections.content")}>{item.decision_content}</Field>
              )}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
          <p className="mt-3 min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
            {decisionGuidance(t, item, roleCode)}
          </p>
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
  roleCode,
  roleLabel,
  t,
}: {
  recoveries: Recovery[];
  loading: boolean;
  canAddMonitoring: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  roleCode: string | null | undefined;
  roleLabel: string;
  t: TFunction;
}) {
  return (
    <SectionCard
      icon={BriefcaseMedical}
      title={t("dashboard:sections.recoveries")}
      loading={loading}
      empty={recoveries.length === 0}
      t={t}
    >
      {recoveries.map((item) => (
        <div key={item.id} className="min-w-0 rounded-lg border p-3 text-sm">
          <div className="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
              {recoveryDisplayLabel(t, item, recoveries)}
            </div>
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
              {item.recovery_plan && (
                <Field label={t("dashboard:sections.plan")}>{item.recovery_plan}</Field>
              )}
              {item.support_needs && (
                <Field label={t("dashboard:sections.supportNeeds")}>{item.support_needs}</Field>
              )}
              {item.notes && <Field label={t("dashboard:sections.notes")}>{item.notes}</Field>}
              {item.discontinuation_reason && (
                <Field label={t("dashboard:workflow.discontinuationReason")}>{item.discontinuation_reason}</Field>
              )}
            </div>
          ) : (
            <MetadataOnlyText roleLabel={roleLabel} t={t} />
          )}
          <div className="mt-3 space-y-2 border-t pt-3">
            <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {t("dashboard:workflow.monitoringHistory")}
            </div>
            {(item.monitoring ?? []).map((monitoring) => (
              <div key={monitoring.id} className="rounded-md bg-muted/40 p-3">
                <div className="text-xs text-muted-foreground">{formatDate(monitoring.monitoring_date, undefined)}</div>
                {monitoring.condition_summary && <Field label={t("dashboard:workflow.conditionSummary")}>{monitoring.condition_summary}</Field>}
                {monitoring.follow_up_plan && <Field label={t("dashboard:workflow.followUpPlan")}>{monitoring.follow_up_plan}</Field>}
              </div>
            ))}
            {(item.monitoring ?? []).length === 0 && roleCode === "admin" && (
              <p className="text-xs text-muted-foreground">{t("dashboard:workflow.adminMonitoringEmpty")}</p>
            )}
            {(item.monitoring ?? []).length === 0 && roleCode === "super_admin" && (
              <p className="text-xs text-muted-foreground">{t("dashboard:workflow.oversightMonitoringEmpty")}</p>
            )}
          </div>
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
  unavailableReason,
  capabilityLoading,
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
  unavailableReason?: string;
  capabilityLoading: boolean;
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
          <CardDescription>
            {unavailableReason || t("dashboard:cases.restrictedDetail", { roleLabel })}
          </CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const createAction = createInvestigation ? (
    <EvidenceCreateAction investigation={createInvestigation} />
  ) : (
    <UnavailableWorkflowAction
      label={t("dashboard:workflow.addEvidence")}
      reason={capabilityLoading ? t("dashboard:workflow.reasons.capability_loading") : unavailableReason}
    />
  );

  return (
    <CollapsibleDataCard
      icon={FileArchive}
      title={t("dashboard:sections.evidenceTitle")}
      description={t("dashboard:sections.evidenceCount", { count: evidences.length })}
      headerAction={evidences.length > 0 ? createAction : null}
      contentClassName="space-y-4"
    >
      {loading ? (
        <div className="space-y-3">
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ) : error ? (
        <div className="flex flex-col items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex min-w-0 items-start gap-3">
            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" aria-hidden="true" />
            <p className="break-words text-sm text-destructive">
              {t("dashboard:sections.evidenceLoadError")}
            </p>
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
          action={createAction}
        />
      ) : (
        <div className="space-y-3">
          {evidences.map((item) => (
            <div key={item.id} className="min-w-0 space-y-4 rounded-lg border p-4 text-sm">
              <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                  <div className="min-w-0 break-words font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
                    {item.title}
                  </div>
                  <div className="mt-1 min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
                    {formatEvidenceType(t, item.evidence_type?.code ?? item.evidence_type?.name)}
                  </div>
                </div>
                <WorkflowStatusBadge
                  family="evidence"
                  status={item.status}
                  className="w-fit shrink-0"
                />
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
    </CollapsibleDataCard>
  );
}

function SectionCard({
  icon: Icon,
  title,
  loading,
  empty,
  emptyAction,
  emptyText,
  children,
  t,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  loading: boolean;
  empty: boolean;
  emptyAction?: React.ReactNode;
  emptyText?: string;
  children: React.ReactNode;
  t: TFunction;
}) {
  return (
    <CollapsibleDataCard
      icon={Icon}
      title={title}
      description={t("dashboard:common.readOnlyOperationalData")}
      contentClassName="space-y-3"
    >
      {loading && (
        <EmptyText>{t("dashboard:sections.loading", { name: title.toLowerCase() })}</EmptyText>
      )}
      {!loading && empty && (
        <>
          <EmptyText>{emptyText ?? t("dashboard:sections.empty", { name: title.toLowerCase() })}</EmptyText>
          {emptyAction && <div className="flex min-w-0 justify-end">{emptyAction}</div>}
        </>
      )}
      {!loading && !empty && children}
    </CollapsibleDataCard>
  );
}

function UnavailableWorkflowAction({ label, reason }: { label: string; reason?: string }) {
  return (
    <div className="min-w-0 space-y-1">
      <Button className="w-full" variant="outline" disabled>{label}</Button>
      {reason && <p className="break-words text-xs text-muted-foreground [overflow-wrap:anywhere]">{reason}</p>}
    </div>
  );
}

function workflowReason(t: TFunction, code?: string | null, stage?: string | null) {
  if (!code) return undefined;
  return t(`dashboard:workflow.reasons.${code}`, {
    stage: formatInvestigationStatus(t, stage),
    defaultValue: t("dashboard:workflow.reasons.action_unavailable"),
  });
}

function workflowTip(t: TFunction, code?: string | null, stage?: string | null) {
  if (!code) return t("dashboard:workflow.tips.follow_available_case_action");
  return t(`dashboard:workflow.tips.${code}`, {
    stage: formatInvestigationStatus(t, stage),
    defaultValue: t("dashboard:workflow.tips.follow_available_case_action"),
  });
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 min-w-0 break-words text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
        {children}
      </div>
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
  return (
    <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
      {children}
    </div>
  );
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

function scrollActiveWorkflowTabIntoView(container: HTMLDivElement | null) {
  const activeTab = container?.querySelector<HTMLElement>(`[data-state="active"]`);
  if (!container || !activeTab) return;

  const containerBounds = container.getBoundingClientRect();
  const activeBounds = activeTab.getBoundingClientRect();

  if (activeBounds.left < containerBounds.left) {
    container.scrollLeft -= containerBounds.left - activeBounds.left;
  } else if (activeBounds.right > containerBounds.right) {
    container.scrollLeft += activeBounds.right - containerBounds.right;
  }
}

function asText(value: unknown) {
  return typeof value === "string" && value ? value : "-";
}

function hasRecommendationDetail(item: Recommendation) {
  return item.conclusion !== undefined && item.recommended_actions !== undefined;
}

function canEditRecommendation(item: Recommendation) {
  return (
    item.status === "drafting" || item.status === "internal_review" || item.status === "revised"
  );
}

function canSubmitRecommendation(item: Recommendation) {
  return (
    item.status === "drafting" || item.status === "internal_review" || item.status === "revised"
  );
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

    return bTime - aTime || b.id - a.id;
  })[0];
}

function selectEvidenceInvestigation(items: Investigation[]) {
  return (
    [...items]
      .filter((item) => {
        const status = normalizeWorkflowToken(item.status ?? item.status_code);
        return status !== "completed" && status !== "invs_08";
      })
      .sort((a, b) =>
        compareNewest(a.started_at ?? a.created_at, a.id, b.started_at ?? b.created_at, b.id),
      )[0] ?? null
  );
}

function acceptedRecommendationForDecision(items: Recommendation[]) {
  return (
    [...items]
      .filter((item) => item.status === "accepted")
      .sort((a, b) => compareNewest(a.created_at, a.id, b.created_at, b.id))[0] ?? null
  );
}

function recommendationGuidance(
  t: TFunction,
  recommendation: Recommendation,
  roleCode: string | null | undefined,
) {
  const status = normalizeWorkflowToken(recommendation.status ?? recommendation.status_code);
  const role =
    roleCode === "super_admin" ? "superAdmin" : roleCode === "satgas_ppks" ? "satgas" : "admin";

  return t(`dashboard:workflow.recommendationGuidance.${status}.${role}`, {
    defaultValue: t("dashboard:workflow.recommendationGuidance.fallback"),
  });
}

function decisionGuidance(t: TFunction, decision: Decision, roleCode: string | null | undefined) {
  const status = normalizeWorkflowToken(decision.status ?? decision.status_code);
  const role =
    roleCode === "satgas_ppks" ? "satgas" : roleCode === "admin" ? "admin" : "superAdmin";

  return t(`dashboard:workflow.decisionGuidance.${status}.${role}`, {
    defaultValue: t("dashboard:workflow.decisionGuidance.fallback"),
  });
}

function finalizedDecision(items: Decision[]) {
  return (
    [...items]
      .filter((item) => item.status === "finalized")
      .sort((a, b) => compareNewest(a.created_at, a.id, b.created_at, b.id))[0] ?? null
  );
}

function compareNewest(
  aDate: string | null | undefined,
  aId: number,
  bDate: string | null | undefined,
  bId: number,
) {
  const aTime = aDate ? new Date(aDate).getTime() : 0;
  const bTime = bDate ? new Date(bDate).getTime() : 0;
  return bTime - aTime || bId - aId;
}

function recoveryDisplayLabel(t: TFunction, item: Recovery, items: Recovery[]) {
  const typeValue = item.recovery_type?.code ?? item.recovery_type?.name;
  const typeKey = normalizeWorkflowToken(typeValue) || "recovery";
  const matchingItems = items.filter(
    (candidate) =>
      (normalizeWorkflowToken(candidate.recovery_type?.code ?? candidate.recovery_type?.name) ||
        "recovery") === typeKey,
  );
  const typeLabel = typeValue
    ? formatRecoveryType(t, typeValue)
    : t("dashboard:sections.recoveryNumber");

  if (matchingItems.length < 2) return typeLabel;

  return t("dashboard:sections.recoveryTypeSequence", {
    type: typeLabel,
    sequence: matchingItems.findIndex((candidate) => candidate.id === item.id) + 1,
  });
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
    .flatMap((candidate, order) =>
      candidate.at ? [{ ...candidate, at: candidate.at, order }] : [],
    )
    .sort((a, b) => new Date(a.at).getTime() - new Date(b.at).getTime() || a.order - b.order)
    .map((candidate) => ({
      id: candidate.id,
      title: t(`dashboard:cases.progress.events.${candidate.key}`),
      timestamp: formatDateTime(candidate.at, language),
      description: t(`dashboard:cases.progress.events.${candidate.key}Desc`),
      icon: candidate.icon,
    }));
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
