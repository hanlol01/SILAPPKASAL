import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import { ArrowRight, ClipboardCheck, FileSearch, Gavel, HeartHandshake, Scale } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { QueryErrorState } from "@/components/query-state";
import { Skeleton } from "@/components/ui/skeleton";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { dashboardQueryKeys, getDashboardWorkflow } from "@/lib/dashboard-api";
import { formatDate } from "@/lib/format";
import {
  formatDecisionOutcome,
  formatDecisionStatus,
  formatInvestigationStatus,
  formatRecommendationStatus,
  formatRecoveryStatus,
  formatRecoveryType,
} from "@/lib/format-labels";

export const Route = createFileRoute("/dashboard/workflow")({
  component: WorkflowPage,
  head: () => ({ meta: [{ title: "Workflow - SILAPPKASAL Admin" }] }),
});

const steps = [
  {
    key: "reports_forwarded_to_cases",
    labelKey: "forwardedReports",
    icon: ClipboardCheck,
  },
  {
    key: "cases_with_investigations",
    labelKey: "investigations",
    icon: FileSearch,
  },
  {
    key: "cases_with_recommendations",
    labelKey: "recommendations",
    icon: Gavel,
  },
  {
    key: "recommendations_with_decisions",
    labelKey: "decisions",
    icon: Scale,
  },
  {
    key: "decisions_with_recoveries",
    labelKey: "recoveries",
    icon: HeartHandshake,
  },
] as const;

function WorkflowPage() {
  const { t, i18n } = useTranslation(["dashboard"]);
  const workflowQuery = useQuery({
    queryKey: dashboardQueryKeys.workflow(),
    queryFn: () => getDashboardWorkflow(),
  });

  if (workflowQuery.isLoading) {
    return (
      <div className="space-y-6">
        <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.workflow") }]} />
        <PageHeader
          title={t("dashboard:workflow.pipeline.title")}
          description={t("dashboard:workflow.pipeline.loadingDescription")}
        />
        <Card>
          <CardHeader>
            <Skeleton className="h-5 w-48" />
            <Skeleton className="h-4 w-80" />
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-5">
              {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="rounded-lg border p-4 space-y-3">
                  <Skeleton className="h-10 w-10 rounded-lg" />
                  <Skeleton className="h-3 w-16" />
                  <Skeleton className="h-4 w-28" />
                  <Skeleton className="h-8 w-14" />
                  <Skeleton className="h-2 w-full rounded-full" />
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (workflowQuery.isError || !workflowQuery.data) {
    return (
      <div className="space-y-6">
        <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.workflow") }]} />
        <PageHeader
          title={t("dashboard:workflow.pipeline.title")}
          description={t("dashboard:workflow.pipeline.description")}
        />
        <QueryErrorState
          message={t("dashboard:workflow.pipeline.unavailable")}
          onRetry={() => workflowQuery.refetch()}
        />
      </div>
    );
  }

  const workflow = workflowQuery.data;
  const maxConversion = Math.max(...Object.values(workflow.conversion_counts), 1);
  const distributions = [
    { title: t("dashboard:sections.investigations"), rows: workflow.status_distributions.investigations, formatter: formatInvestigationStatus },
    { title: t("dashboard:sections.recommendations"), rows: workflow.status_distributions.recommendations, formatter: formatRecommendationStatus },
    { title: t("dashboard:sections.decisions"), rows: workflow.status_distributions.decisions, formatter: formatDecisionStatus },
    { title: t("dashboard:sections.recoveries"), rows: workflow.status_distributions.recoveries, formatter: formatRecoveryStatus },
  ];

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.workflow") }]} />
      <PageHeader
        title={t("dashboard:workflow.pipeline.title")}
        description={t("dashboard:workflow.pipeline.description")}
      />

      <Card>
        <CardHeader>
          <CardTitle>{t("dashboard:workflow.pipeline.conversionTitle")}</CardTitle>
          <CardDescription>{t("dashboard:workflow.pipeline.metricSemantics")}</CardDescription>
        </CardHeader>
        <CardContent>
          <ol className="grid gap-4 md:grid-cols-5">
            {steps.map((step, index) => {
              const Icon = step.icon;
              const count = workflow.conversion_counts[step.key];
              const pct = Math.round((count / maxConversion) * 100);
              return (
                <li key={step.key} className="rounded-lg border p-4">
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Icon className="h-5 w-5" />
                    </div>
                    {index < steps.length - 1 && <ArrowRight className="hidden h-4 w-4 text-muted-foreground md:block" />}
                  </div>
                  <div className="mt-4 text-xs uppercase tracking-wide text-muted-foreground">
                    {t("dashboard:workflow.pipeline.step", { number: index + 1 })}
                  </div>
                  <div className="mt-1 text-sm font-medium">
                    {t(`dashboard:workflow.pipeline.steps.${step.labelKey}`)}
                  </div>
                  <div className="mt-2 text-2xl font-semibold">{count}</div>
                  <Progress value={pct} className="mt-3" />
                </li>
              );
            })}
          </ol>
          <p className="mt-4 text-xs text-muted-foreground">
            {t("dashboard:workflow.pipeline.relativeBarHint")}
          </p>
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        {distributions.map((group) => (
          <Card key={group.title}>
            <CardHeader>
              <CardTitle>{group.title}</CardTitle>
              <CardDescription>{t("dashboard:workflow.pipeline.distributionDescription")}</CardDescription>
            </CardHeader>
            <CardContent>
              {group.rows.length === 0 ? (
                <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                  {t("dashboard:workflow.pipeline.noStatusData")}
                </div>
              ) : (
                <div className="space-y-3">
                  {group.rows.map((row) => (
                    <div key={row.key ?? "unspecified"} className="flex items-center justify-between rounded-lg border p-3">
                      <div className="text-sm font-medium">{group.formatter(t, row.key)}</div>
                      <Badge variant="secondary">{row.count}</Badge>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:workflow.pipeline.decisionOutcomes")}</CardTitle>
            <CardDescription>{t("dashboard:workflow.pipeline.descriptiveCountsOnly")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {workflow.decision_outcomes.length === 0 ? (
              <div className="text-sm text-muted-foreground">{t("dashboard:workflow.pipeline.noDecisionOutcomeData")}</div>
            ) : (
              workflow.decision_outcomes.map((row) => (
                <div key={row.key ?? "unspecified"} className="flex items-center justify-between rounded-lg border p-3">
                  <span className="text-sm font-medium">{formatDecisionOutcome(t, row.key)}</span>
                  <Badge variant="outline">{row.count}</Badge>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:workflow.pipeline.recoveryTypes")}</CardTitle>
            <CardDescription>{t("dashboard:workflow.pipeline.recoveryDistributionDescription")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {workflow.recovery_types.length === 0 ? (
              <div className="text-sm text-muted-foreground">{t("dashboard:workflow.pipeline.noRecoveryTypeData")}</div>
            ) : (
              workflow.recovery_types.map((row) => (
                <div key={row.key ?? "unspecified"} className="flex items-center justify-between rounded-lg border p-3">
                  <span className="text-sm font-medium">{formatRecoveryType(t, row.key)}</span>
                  <Badge variant="outline">{row.count}</Badge>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>

      <div className="text-xs text-muted-foreground">
        {t("dashboard:workflow.pipeline.footer", {
          scope: workflow.scope,
          dateFrom: formatDate(workflow.filters.date_from, i18n.language),
          dateTo: formatDate(workflow.filters.date_to, i18n.language),
        })}
      </div>
    </div>
  );
}

function PageHeader({ title, description }: { title: string; description: string }) {
  return (
    <div>
      <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
      <p className="text-sm text-muted-foreground">{description}</p>
    </div>
  );
}
