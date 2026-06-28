import { createFileRoute, Link } from "@tanstack/react-router";
import { useQueries, useQuery } from "@tanstack/react-query";
import {
  ArrowLeft,
  BriefcaseMedical,
  ClipboardList,
  FileArchive,
  FileSearch,
  Gavel,
  Lock,
  Scale,
  UserRoundSearch,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { DecisionCreateAction } from "@/components/workflow-actions/decision-create-action";
import { DecisionStatusAction } from "@/components/workflow-actions/decision-status-action";
import { InvestigationCreateAction } from "@/components/workflow-actions/investigation-create-action";
import { InvestigationStatusAction } from "@/components/workflow-actions/investigation-status-action";
import { RecommendationCreateAction } from "@/components/workflow-actions/recommendation-create-action";
import { RecommendationStatusAction } from "@/components/workflow-actions/recommendation-status-action";
import { RecoveryCreateAction } from "@/components/workflow-actions/recovery-create-action";
import { RecoveryStatusAction } from "@/components/workflow-actions/recovery-status-action";
import { SatgasAssignmentAction } from "@/components/workflow-actions/satgas-assignment-action";
import {
  CaseStatusAction,
  DecisionUpdateAction,
  DisabledWorkflowAction,
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
  formatDecisionStatus,
  formatEvidenceClassification,
  formatEvidenceStatus,
  formatInvestigationStatus,
  formatRecommendationStatus,
  formatRecoveryStatus,
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
  Decision,
  EvidenceMetadata,
  Investigation,
  Recommendation,
  Recovery,
} from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/cases/$id")({
  component: CaseDetail,
  head: () => ({ meta: [{ title: "Case detail - SILAPPKASAL Admin" }] }),
});

function CaseDetail() {
  const { id } = Route.useParams();
  const { user, roleCode } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const caseQuery = useQuery({
    queryKey: operationsQueryKeys.case(id),
    queryFn: () => getCase(id),
  });
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
    queries: (investigationsQuery.data ?? []).map((investigation) => ({
      queryKey: operationsQueryKeys.evidences(investigation.id),
      queryFn: () => getInvestigationEvidences(investigation.id),
      enabled: investigationsQuery.isSuccess,
    })),
  });
  const evidences = evidenceQueries.flatMap((query) => query.data ?? []);

  if (caseQuery.isLoading) {
    return <div className="py-12 text-center text-sm text-muted-foreground">{t("dashboard:cases.loading")}</div>;
  }

  if (caseQuery.isError || !caseQuery.data) {
    return <QueryErrorState message={t("dashboard:cases.error")} onRetry={() => caseQuery.refetch()} />;
  }

  const c = caseQuery.data;
  const isAdminRole = roleCode === "super_admin" || roleCode === "admin";
  const isAssignedSatgas =
    roleCode === "satgas_ppks" &&
    (c.assignments ?? []).some((assignment) => assignment.is_active && assignment.satgas_id === user?.id);
  const canUseSatgasActions = isAssignedSatgas && !c.closed_at;
  const canInvestigate = canUseSatgasActions && Boolean(user?.permissions?.includes("cases.investigate"));
  const canRecommend = canUseSatgasActions && Boolean(user?.permissions?.includes("cases.recommend"));
  const canManageDecisionActions =
    roleCode === "super_admin" && Boolean(user?.permissions?.includes("cases.record_decision"));
  const canManageRecoveryActions = isAdminRole && Boolean(user?.permissions?.includes("cases.monitor"));
  const canAddRecoveryMonitoring = canManageRecoveryActions || canUseSatgasActions;
  const activeAssignments = (c.assignments ?? []).filter((assignment) => assignment.is_active);
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

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/dashboard/cases">
            <ArrowLeft className="mr-2 h-4 w-4" /> {t("dashboard:cases.allCases")}
          </Link>
        </Button>
        <div className="flex items-center gap-2">
          <h1 className="font-mono text-lg font-semibold">{c.case_number}</h1>
          <Badge variant="outline">{formatCaseStatus(t, c.status_code)}</Badge>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.metadata")}</CardTitle>
              <CardDescription>{t("dashboard:cases.metadataDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
              <Field label={t("dashboard:cases.caseNumber")}>{c.case_number}</Field>
              <Field label={t("dashboard:reports.registration")}>{c.registration_number}</Field>
              <Field label={t("dashboard:common.status")}>{c.status ?? formatCaseStatus(t, c.status_code)}</Field>
              <Field label={t("dashboard:common.stage")}>{c.current_stage_label ?? formatCaseStatus(t, c.current_stage ?? "-")}</Field>
              <Field label={t("dashboard:common.risk")}>{c.risk_level ?? c.risk_level_code ?? "-"}</Field>
              <Field label={t("dashboard:common.priority")}>{c.priority ?? "-"}</Field>
              <Field label={t("dashboard:common.forwarded")}>{formatDate(c.forwarded_at, i18n.language)}</Field>
              <Field label={t("dashboard:common.closed")}>{formatDate(c.closed_at, i18n.language)}</Field>
            </CardContent>
          </Card>

          <SensitiveReportSection report={c.report} t={t} />
          <InvestigationsSection
            investigations={investigationsQuery.data ?? []}
            loading={investigationsQuery.isLoading}
            canAddActivity={canUseSatgasActions}
            canTransitionStatus={canInvestigate}
            caseId={c.id}
            language={i18n.language}
            t={t}
          />
          <RecommendationsSection
            recommendations={recommendationsQuery.data ?? []}
            loading={recommendationsQuery.isLoading}
            canUpdate={canRecommend}
            canTransitionStatus={canRecommend}
            caseId={c.id}
            t={t}
          />
          <DecisionsSection
            decisions={decisions}
            loading={decisionQueries.some((query) => query.isLoading)}
            canUpdate={canManageDecisionActions}
            canTransitionStatus={canManageDecisionActions}
            caseId={c.id}
            language={i18n.language}
            t={t}
          />
          <RecoveriesSection
            recoveries={recoveries}
            loading={recoveryQueries.some((query) => query.isLoading)}
            canAddMonitoring={canAddRecoveryMonitoring}
            canTransitionStatus={canManageRecoveryActions}
            caseId={c.id}
            t={t}
          />
          <EvidenceSection
            evidences={evidences}
            loading={evidenceQueries.some((query) => query.isLoading)}
            canUpdate={canUseSatgasActions}
            language={i18n.language}
            t={t}
          />
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.assignments")}</CardTitle>
              <CardDescription>{t("dashboard:cases.assignmentsDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {(c.assignments ?? []).length === 0 && (
                <EmptyText>{t("dashboard:cases.noAssignments")}</EmptyText>
              )}
              {(c.assignments ?? []).map((assignment) => (
                <div key={assignment.id} className="rounded-lg border p-3 text-sm">
                  <div className="font-medium">{assignment.satgas_name ?? `Satgas #${assignment.satgas_id}`}</div>
                  <div className="mt-1 text-xs text-muted-foreground">
                    {assignment.is_lead ? t("dashboard:cases.leadSatgas") : t("dashboard:cases.assignedSatgas")} - {formatDate(assignment.assigned_at, i18n.language)}
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:common.actions")}</CardTitle>
              <CardDescription>{t("dashboard:cases.actionsDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {isAdminRole ? (
                <>
                  <SatgasAssignmentAction
                    mode="assign-case"
                    targetId={c.id}
                    currentSatgasIds={(c.assignments ?? [])
                      .filter((assignment) => assignment.is_active)
                      .map((assignment) => assignment.satgas_id)}
                    currentLeadSatgasId={
                      (c.assignments ?? []).find((assignment) => assignment.is_active && assignment.is_lead)
                        ?.satgas_id ?? null
                    }
                  />
                  <p className="text-xs text-muted-foreground">
                    {t("dashboard:reports.satgasHint")}
                  </p>
                </>
              ) : (
                <Button disabled className="w-full" variant="outline">
                  <UserRoundSearch className="mr-2 h-4 w-4" /> {t("dashboard:cases.assignSatgas")}
                </Button>
              )}
              {canUseSatgasActions ? (
                <CaseStatusAction caseId={c.id} currentStatus={c.status_code} />
              ) : (
                <DisabledWorkflowAction
                  title={t("dashboard:workflow.caseStatusUpdate")}
                  description={t("dashboard:common.backendRules")}
                />
              )}
              {canCreateInvestigation && (
                <InvestigationCreateAction caseId={c.id} assignments={activeAssignments} />
              )}
              {canInvestigate && !canCreateInvestigation && (
                <DisabledWorkflowAction
                  title={t("dashboard:workflow.createInvestigationDisabled")}
                  description={
                    c.status !== "investigation"
                      ? t("dashboard:workflow.createInvestigationNeedsStatus")
                      : t("dashboard:workflow.createInvestigationExists")
                  }
                />
              )}
              {canCreateRecommendation && latestCompletedInvestigation && (
                <RecommendationCreateAction caseId={c.id} investigation={latestCompletedInvestigation} />
              )}
              {canRecommend && !canCreateRecommendation && (
                <DisabledWorkflowAction
                  title={t("dashboard:workflow.createRecommendationDisabled")}
                  description={recommendationCreateDisabledReason(
                    t,
                    c.status,
                    recommendationsQuery.isSuccess,
                    (recommendationsQuery.data ?? []).length,
                    investigationsQuery.isSuccess,
                    latestCompletedInvestigation,
                  )}
                />
              )}
              {canCreateDecision && submittedDecisionRecommendation && (
                <DecisionCreateAction caseId={c.id} recommendation={submittedDecisionRecommendation} />
              )}
              {canManageDecisionActions && !canCreateDecision && (
                <DisabledWorkflowAction
                  title={t("dashboard:workflow.createDecisionDisabled")}
                  description={decisionCreateDisabledReason(
                    t,
                    c.status,
                    recommendationsQuery.isSuccess,
                    decisionsLoaded,
                    submittedDecisionRecommendation,
                    decisions.length,
                  )}
                />
              )}
              {canCreateRecovery && finalizedDecisionForRecovery && (
                <RecoveryCreateAction caseId={c.id} decision={finalizedDecisionForRecovery} />
              )}
              {canManageRecoveryActions && !canCreateRecovery && (
                <DisabledWorkflowAction
                  title={t("dashboard:workflow.createRecoveryDisabled")}
                  description={recoveryCreateDisabledReason(t, decisionsLoaded, finalizedDecisionForRecovery, c.closed_at)}
                />
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

function SensitiveReportSection({ report, t }: { report: unknown; t: TFunction }) {
  if (!report || typeof report !== "object") {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Lock className="h-4 w-4" /> {t("dashboard:cases.sensitiveReport")}
          </CardTitle>
          <CardDescription>{t("dashboard:cases.sensitiveMetadataOnly")}</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const data = report as Record<string, unknown>;
  const respondent = data.respondent as { name?: string | null; details?: string | null } | undefined;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("dashboard:cases.sensitiveReport")}</CardTitle>
        <CardDescription>{t("dashboard:cases.sensitiveDesc")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <Field label={t("dashboard:cases.chronology")}>{asText(data.chronology)}</Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t("dashboard:cases.incidentDate")}>{asText(data.incident_date)}</Field>
          <Field label={t("dashboard:cases.incidentTime")}>{asText(data.incident_time)}</Field>
          <Field label={t("dashboard:cases.incidentLocation")}>{asText(data.incident_location)}</Field>
          <Field label={t("dashboard:cases.witnessInfo")}>{asText(data.witness_info)}</Field>
        </div>
        <Separator />
        <div className="grid gap-4 sm:grid-cols-2">
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
  t,
}: {
  investigations: Investigation[];
  loading: boolean;
  canAddActivity: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  language: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={FileSearch} title={t("dashboard:sections.investigations")} loading={loading} empty={investigations.length === 0} t={t}>
      {investigations.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{t("dashboard:sections.investigationNumber", { id: item.id })}</div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="outline">{formatInvestigationStatus(t, item.status)}</Badge>
              {canAddActivity && <InvestigationActivityAction investigation={item} caseId={caseId} />}
              {canTransitionStatus && item.status !== "completed" && (
                <InvestigationStatusAction investigation={item} caseId={caseId} />
              )}
            </div>
          </div>
          <div className="mt-2 grid gap-2 text-muted-foreground sm:grid-cols-2">
            <div>{t("dashboard:sections.lead")}: {item.lead_investigator?.name ?? t("dashboard:common.metadataUnavailable")}</div>
            <div>{t("dashboard:sections.started")}: {formatDate(item.started_at, language)}</div>
          </div>
          {item.plan_summary || item.findings || item.conclusion ? (
            <div className="mt-3 space-y-2">
              {item.plan_summary && <Field label={t("dashboard:sections.planSummary")}>{item.plan_summary}</Field>}
              {item.findings && <Field label={t("dashboard:sections.findings")}>{item.findings}</Field>}
              {item.conclusion && <Field label={t("dashboard:sections.conclusion")}>{item.conclusion}</Field>}
            </div>
          ) : (
            <MetadataOnlyText t={t} />
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
  t,
}: {
  recommendations: Recommendation[];
  loading: boolean;
  canUpdate: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={ClipboardList} title={t("dashboard:sections.recommendations")} loading={loading} empty={recommendations.length === 0} t={t}>
      {recommendations.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{t("dashboard:sections.recommendationNumber", { id: item.id })}</div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="outline">{formatRecommendationStatus(t, item.status)}</Badge>
              {canUpdate && canEditRecommendation(item) && hasRecommendationDetail(item) && (
                <RecommendationUpdateAction recommendation={item} caseId={caseId} />
              )}
              {canTransitionStatus && <RecommendationStatusAction recommendation={item} caseId={caseId} />}
            </div>
          </div>
          <div className="mt-2 text-muted-foreground">{t("dashboard:sections.author")}: {item.author?.name ?? t("dashboard:common.metadataUnavailable")}</div>
          {item.conclusion || item.recommended_actions ? (
            <div className="mt-3 space-y-2">
              {item.conclusion && <Field label={t("dashboard:sections.conclusion")}>{item.conclusion}</Field>}
              {item.recommended_actions && <Field label={t("dashboard:sections.recommendedActions")}>{item.recommended_actions}</Field>}
              {item.sanction_recommendation && <Field label={t("dashboard:sections.sanction")}>{item.sanction_recommendation}</Field>}
              {item.recovery_recommendation && <Field label={t("dashboard:sections.recovery")}>{item.recovery_recommendation}</Field>}
              {item.prevention_recommendation && <Field label={t("dashboard:sections.prevention")}>{item.prevention_recommendation}</Field>}
            </div>
          ) : (
            <MetadataOnlyText t={t} />
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
  t,
}: {
  decisions: Decision[];
  loading: boolean;
  canUpdate: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  language: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={Scale} title={t("dashboard:sections.decisions")} loading={loading} empty={decisions.length === 0} t={t}>
      {decisions.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{t("dashboard:sections.decisionNumber", { id: item.id })}</div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="outline">{formatDecisionStatus(t, item.status)}</Badge>
              {canUpdate && canEditDecision(item) && <DecisionUpdateAction decision={item} />}
              {canTransitionStatus && item.status !== "finalized" && (
                <DecisionStatusAction decision={item} caseId={caseId} />
              )}
            </div>
          </div>
          <div className="mt-2 grid gap-2 text-muted-foreground sm:grid-cols-2">
            <div>{t("dashboard:sections.outcome")}: {formatDecisionOutcome(t, item.outcome_code)}</div>
            <div>{t("dashboard:sections.date")}: {formatDate(item.decision_date, language)}</div>
          </div>
          {item.decision_summary || item.decision_content ? (
            <div className="mt-3 space-y-2">
              {item.decision_summary && <Field label={t("dashboard:sections.summary")}>{item.decision_summary}</Field>}
              {item.decision_content && <Field label={t("dashboard:sections.content")}>{item.decision_content}</Field>}
            </div>
          ) : (
            <MetadataOnlyText t={t} />
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
  t,
}: {
  recoveries: Recovery[];
  loading: boolean;
  canAddMonitoring: boolean;
  canTransitionStatus: boolean;
  caseId: number | string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={BriefcaseMedical} title={t("dashboard:sections.recoveries")} loading={loading} empty={recoveries.length === 0} t={t}>
      {recoveries.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{item.recovery_type?.name ?? t("dashboard:sections.recoveryNumber", { id: item.id })}</div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="outline">{formatRecoveryStatus(t, item.status)}</Badge>
              {canAddMonitoring && <RecoveryMonitoringAction recovery={item} />}
              {canTransitionStatus && !isTerminalRecovery(item) && (
                <RecoveryStatusAction recovery={item} caseId={caseId} />
              )}
            </div>
          </div>
          {item.recovery_plan || item.support_needs || item.notes ? (
            <div className="mt-3 space-y-2">
              {item.recovery_plan && <Field label={t("dashboard:sections.plan")}>{item.recovery_plan}</Field>}
              {item.support_needs && <Field label={t("dashboard:sections.supportNeeds")}>{item.support_needs}</Field>}
              {item.notes && <Field label={t("dashboard:sections.notes")}>{item.notes}</Field>}
            </div>
          ) : (
            <MetadataOnlyText t={t} />
          )}
        </div>
      ))}
    </SectionCard>
  );
}

function EvidenceSection({
  evidences,
  loading,
  canUpdate,
  language,
  t,
}: {
  evidences: EvidenceMetadata[];
  loading: boolean;
  canUpdate: boolean;
  language: string;
  t: TFunction;
}) {
  return (
    <SectionCard icon={FileArchive} title={t("dashboard:sections.evidenceMetadata")} loading={loading} empty={evidences.length === 0} t={t}>
      {evidences.map((item) => (
        <div key={item.id} className="rounded-lg border p-3 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="font-medium">{item.title}</div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="outline">{formatEvidenceStatus(t, item.status)}</Badge>
              {canUpdate && (
                <>
                  <EvidenceMetadataAction evidence={item} />
                  <EvidenceStatusAction evidence={item} />
                </>
              )}
            </div>
          </div>
          <div className="mt-2 grid gap-2 text-muted-foreground sm:grid-cols-2">
            <div>{t("dashboard:sections.evidenceType")}: {item.evidence_type?.name ?? t("dashboard:common.metadataUnavailable")}</div>
            <div>{t("dashboard:sections.classification")}: {formatEvidenceClassification(t, item.classification ?? "-")}</div>
            <div>{t("dashboard:sections.collected")}: {formatDate(item.collected_at, language)}</div>
            <div>{t("dashboard:sections.submittedBy")}: {item.submitted_by?.name ?? t("dashboard:common.metadataUnavailable")}</div>
          </div>
          {item.status_semantics && (
            <div className="mt-3 rounded-md bg-muted p-2 text-xs text-muted-foreground">
              {item.status_semantics}
            </div>
          )}
          {item.description && <Field label={t("dashboard:sections.description")}>{item.description}</Field>}
          {item.file_metadata && (
            <div className="mt-3 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
              <div>{t("dashboard:sections.filename")}: {item.file_metadata.original_filename ?? "-"}</div>
              <div>MIME: {item.file_metadata.mime_type ?? "-"}</div>
              <div>{t("dashboard:sections.fileSize")}: {item.file_metadata.file_size ?? "-"}</div>
              <div>{t("dashboard:sections.checksum")}: {item.file_metadata.checksum_sha256 ?? "-"}</div>
            </div>
          )}
          <div className="mt-3">
            <DisabledWorkflowAction
              title={t("dashboard:sections.evidenceFiles")}
              description={t("dashboard:sections.evidenceFilesOutOfScope")}
            />
          </div>
        </div>
      ))}
    </SectionCard>
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
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Icon className="h-4 w-4" /> {title}
        </CardTitle>
        <CardDescription>{t("dashboard:common.readOnlyOperationalData")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        {loading && <EmptyText>{t("dashboard:sections.loading", { name: title.toLowerCase() })}</EmptyText>}
        {!loading && empty && <EmptyText>{t("dashboard:sections.empty", { name: title.toLowerCase() })}</EmptyText>}
        {!loading && !empty && children}
      </CardContent>
    </Card>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 whitespace-pre-wrap text-sm">{children}</div>
    </div>
  );
}

function MetadataOnlyText({ t }: { t: TFunction }) {
  return <div className="mt-3 text-xs text-muted-foreground">{t("dashboard:common.metadataOnly")}</div>;
}

function EmptyText({ children }: { children: React.ReactNode }) {
  return <div className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">{children}</div>;
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

function submittedRecommendationForDecision(items: Recommendation[]) {
  return items.find((item) => item.status === "submitted_to_leader") ?? null;
}

function finalizedDecision(items: Decision[]) {
  return items.find((item) => item.status === "finalized") ?? null;
}

function recommendationCreateDisabledReason(
  t: TFunction,
  status: string | null | undefined,
  recommendationsLoaded: boolean,
  recommendationCount: number,
  investigationsLoaded: boolean,
  completedInvestigation: Investigation | null,
) {
  if (status !== "recommendation") {
    return t("dashboard:workflow.createRecommendationNeedsStatus");
  }

  if (!recommendationsLoaded || !investigationsLoaded) {
    return t("dashboard:workflow.createRecommendationLoading");
  }

  if (recommendationCount > 0) {
    return t("dashboard:workflow.createRecommendationExists");
  }

  if (!completedInvestigation) {
    return t("dashboard:workflow.createRecommendationNeedsInvestigation");
  }

  return t("dashboard:workflow.createRecommendationUnavailable");
}

function decisionCreateDisabledReason(
  t: TFunction,
  status: string | null | undefined,
  recommendationsLoaded: boolean,
  decisionsLoaded: boolean,
  submittedRecommendation: Recommendation | null,
  decisionCount: number,
) {
  if (status !== "decision") {
    return t("dashboard:workflow.createDecisionNeedsStatus");
  }

  if (!recommendationsLoaded || !decisionsLoaded) {
    return t("dashboard:workflow.createDecisionLoading");
  }

  if (decisionCount > 0) {
    return t("dashboard:workflow.createDecisionExists");
  }

  if (!submittedRecommendation) {
    return t("dashboard:workflow.createDecisionNeedsRecommendation");
  }

  return t("dashboard:workflow.createDecisionUnavailable");
}

function recoveryCreateDisabledReason(
  t: TFunction,
  decisionsLoaded: boolean,
  decision: Decision | null,
  closedAt: string | null | undefined,
) {
  if (closedAt) {
    return t("dashboard:workflow.createRecoveryClosed");
  }

  if (!decisionsLoaded) {
    return t("dashboard:workflow.createRecoveryLoading");
  }

  if (!decision) {
    return t("dashboard:workflow.createRecoveryNeedsDecision");
  }

  return t("dashboard:workflow.createRecoveryUnavailable");
}

function formatDate(value: string | null | undefined, language: string) {
  return formatDateTime(value, language);
}
