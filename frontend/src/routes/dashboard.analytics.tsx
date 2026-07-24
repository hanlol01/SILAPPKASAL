import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import { useMemo } from "react";
import {
  BarChart,
  Bar,
  ResponsiveContainer,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
  Legend,
} from "recharts";
import { AccessDenied } from "@/components/access-denied";
import { EmptyState } from "@/components/empty-state";
import { FilterResetButton } from "@/components/filter-reset-button";
import { OperationalScopeFilter } from "@/components/operational-scope-filter";
import { QueryErrorState, StatSkeletonGrid } from "@/components/query-state";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";
import {
  dashboardQueryKeys,
  getDashboardCases,
  getDashboardEvidence,
  getDashboardReports,
  getDashboardSummary,
  getDashboardWorkflow,
} from "@/lib/dashboard-api";
import {
  formatCaseStatus,
  formatEvidenceClassification,
  formatReportCategory,
} from "@/lib/format-labels";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { Loader2, SearchX } from "lucide-react";
import type { DashboardFilters } from "@/lib/api-types";

export const Route = createFileRoute("/dashboard/analytics")({
  validateSearch: (search: Record<string, unknown>): AnalyticsSearch => ({
    satgas_id: positiveInteger(search.satgas_id),
    assignment_status: search.assignment_status === "unassigned" ? "unassigned" : undefined,
    university_id: positiveInteger(search.university_id),
  }),
  component: AnalyticsPage,
  head: () => ({ meta: [{ title: "Analytics - SILAPPKASAL Admin" }] }),
});

type AnalyticsSearch = {
  satgas_id?: number;
  assignment_status?: "unassigned";
  university_id?: number;
};

function positiveInteger(value: unknown): number | undefined {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

const PIE = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
  "var(--muted-foreground)",
];

function AnalyticsPage() {
  const { roleCode } = useAuth();
  const { t } = useTranslation(["dashboard"]);
  const navigate = Route.useNavigate();
  const search = Route.useSearch();
  const canViewAnalytics = roleCode === "super_admin" || roleCode === "admin";
  const filters = useMemo<DashboardFilters>(() => {
    if (roleCode === "admin") {
      return {
        satgas_id: search.assignment_status ? undefined : search.satgas_id,
        assignment_status: search.assignment_status,
      };
    }

    if (roleCode === "super_admin") {
      return { university_id: search.university_id };
    }

    return {};
  }, [roleCode, search.assignment_status, search.satgas_id, search.university_id]);
  const satgasValue =
    search.assignment_status === "unassigned"
      ? "unassigned"
      : search.satgas_id
        ? String(search.satgas_id)
        : "all";
  const universityValue = search.university_id ? String(search.university_id) : "all";
  const filterActive =
    (roleCode === "admin" && satgasValue !== "all") ||
    (roleCode === "super_admin" && universityValue !== "all");

  const summaryQuery = useQuery({
    queryKey: dashboardQueryKeys.summary(filters),
    queryFn: () => getDashboardSummary(filters),
    enabled: canViewAnalytics,
  });
  const reportsQuery = useQuery({
    queryKey: dashboardQueryKeys.reports(filters),
    queryFn: () => getDashboardReports(filters),
    enabled: canViewAnalytics,
  });
  const casesQuery = useQuery({
    queryKey: dashboardQueryKeys.cases(filters),
    queryFn: () => getDashboardCases(filters),
    enabled: canViewAnalytics,
  });
  const evidenceQuery = useQuery({
    queryKey: dashboardQueryKeys.evidence(filters),
    queryFn: () => getDashboardEvidence(filters),
    enabled: canViewAnalytics,
  });
  const workflowQuery = useQuery({
    queryKey: dashboardQueryKeys.workflow(filters),
    queryFn: () => getDashboardWorkflow(filters),
    enabled: canViewAnalytics,
  });

  if (!canViewAnalytics) {
    return <AccessDenied />;
  }

  const isLoading =
    summaryQuery.isLoading ||
    reportsQuery.isLoading ||
    casesQuery.isLoading ||
    evidenceQuery.isLoading ||
    workflowQuery.isLoading;
  const isScopeLoading =
    isLoading ||
    (summaryQuery.isFetching && !summaryQuery.data) ||
    (reportsQuery.isFetching && !reportsQuery.data) ||
    (casesQuery.isFetching && !casesQuery.data) ||
    (evidenceQuery.isFetching && !evidenceQuery.data) ||
    (workflowQuery.isFetching && !workflowQuery.data);
  const isError =
    summaryQuery.isError ||
    reportsQuery.isError ||
    casesQuery.isError ||
    evidenceQuery.isError ||
    workflowQuery.isError;

  if (isScopeLoading) {
    return (
      <div className="space-y-6">
        <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.analytics") }]} />
        <PageHeader
          title={t("dashboard:analytics.title")}
          description={t("dashboard:analytics.subtitle")}
        />
        <StatSkeletonGrid />
      </div>
    );
  }

  if (
    isError ||
    !summaryQuery.data ||
    !reportsQuery.data ||
    !casesQuery.data ||
    !evidenceQuery.data ||
    !workflowQuery.data
  ) {
    return (
      <div className="space-y-6">
        <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.analytics") }]} />
        <PageHeader
          title={t("dashboard:analytics.title")}
          description={t("dashboard:analytics.subtitle")}
        />
        <QueryErrorState
          message={t("dashboard:analytics.unavailable")}
          onRetry={() => {
            summaryQuery.refetch();
            reportsQuery.refetch();
            casesQuery.refetch();
            evidenceQuery.refetch();
            workflowQuery.refetch();
          }}
        />
      </div>
    );
  }

  const summary = summaryQuery.data;
  const reports = reportsQuery.data;
  const cases = casesQuery.data;
  const evidence = evidenceQuery.data;
  const workflow = workflowQuery.data;
  const noFilteredResults =
    filterActive &&
    reports.total === 0 &&
    cases.total === 0 &&
    evidence.total === 0 &&
    Object.values(workflow.conversion_counts).every((count) => count === 0);
  const anonymousShare =
    reports.total > 0 ? Math.round((reports.by_identity_mode.anonymous / reports.total) * 100) : 0;
  const trend = reports.time_series.map((point) => ({
    bucket: point.bucket,
    reports: point.count,
    cases: cases.time_series.find((item) => item.bucket === point.bucket)?.count ?? 0,
    evidence: evidence.time_series.find((item) => item.bucket === point.bucket)?.count ?? 0,
  }));
  const caseStages = cases.by_current_stage.map((item) => ({
    stage: formatCaseStatus(t, analyticsLabelKey(item.key)),
    count: item.count,
  }));
  const categories = reports.by_category_code.map((item) => ({
    name: formatReportCategory(t, analyticsLabelKey(item.key)),
    value: item.count,
  }));
  const evidenceClasses = evidence.by_classification.map((item) => ({
    name: formatEvidenceClassification(t, analyticsLabelKey(item.key)),
    value: item.count,
  }));

  const tooltip = {
    contentStyle: {
      background: "var(--popover)",
      border: "1px solid var(--border)",
      borderRadius: 8,
      fontSize: 12,
    },
  } as const;

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:nav.analytics") }]} />
      <div className="flex flex-wrap items-end justify-between gap-3">
        <PageHeader
          title={t("dashboard:analytics.title")}
          description={t("dashboard:analytics.subtitle")}
        />
        <div className="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
          <OperationalScopeFilter
            roleCode={roleCode}
            satgasId={satgasValue}
            universityId={universityValue}
            includeUnassigned
            onSatgasChange={(value) => {
              void navigate({
                search: (current) => ({
                  ...current,
                  satgas_id: value !== "all" && value !== "unassigned" ? Number(value) : undefined,
                  assignment_status: value === "unassigned" ? "unassigned" : undefined,
                  university_id: undefined,
                }),
                replace: true,
              });
            }}
            onUniversityChange={(value) => {
              void navigate({
                search: (current) => ({
                  ...current,
                  satgas_id: undefined,
                  assignment_status: undefined,
                  university_id: value === "all" ? undefined : Number(value),
                }),
                replace: true,
              });
            }}
          />
          <FilterResetButton
            active={filterActive}
            onReset={() => void navigate({ search: {}, replace: true })}
          />
          {summaryQuery.isFetching ||
          reportsQuery.isFetching ||
          casesQuery.isFetching ||
          evidenceQuery.isFetching ||
          workflowQuery.isFetching ? (
            <Loader2
              className="h-4 w-4 animate-spin text-muted-foreground"
              aria-label={t("dashboard:common.loading")}
            />
          ) : null}
        </div>
      </div>
      {noFilteredResults && (
        <EmptyState
          icon={SearchX}
          title={t("dashboard:analytics.empty.filteredTitle")}
          description={t("dashboard:analytics.empty.filteredDesc")}
        />
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label={t("dashboard:analytics.cards.totalReports")}
          value={reports.total}
          description={t("dashboard:analytics.cards.scope", { scope: formatScope(t, reports.scope) })}
        />
        <StatCard
          label={t("dashboard:analytics.cards.totalCases")}
          value={cases.total}
          description={t("dashboard:analytics.cards.assigned", { count: cases.assignments.assigned_cases })}
        />
        <StatCard
          label={t("dashboard:analytics.cards.evidenceRecords")}
          value={evidence.total}
          description={t("dashboard:analytics.cards.metadataCountsOnly")}
        />
        <StatCard
          label={t("dashboard:analytics.cards.anonymousShare")}
          value={`${anonymousShare}%`}
          description={t("dashboard:analytics.cards.ofReportsInRange")}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:analytics.charts.casesByStage")}</CardTitle>
            <CardDescription>{t("dashboard:analytics.charts.casesByStageDesc")}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              {caseStages.length === 0 ? (
                <EmptyChart>
                  {filterActive
                    ? t("dashboard:analytics.empty.filteredDesc")
                    : t("dashboard:analytics.empty.caseStage")}
                </EmptyChart>
              ) : (
                <ResponsiveContainer>
                  <BarChart data={caseStages} layout="vertical" margin={{ left: 30 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />
                    <XAxis type="number" stroke="var(--muted-foreground)" fontSize={12} />
                    <YAxis dataKey="stage" type="category" stroke="var(--muted-foreground)" fontSize={11} width={130} />
                    <Tooltip {...tooltip} />
                    <Bar dataKey="count" name={t("dashboard:analytics.series.count")} fill="var(--chart-1)" radius={[0, 4, 4, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:analytics.charts.reportsByCategory")}</CardTitle>
            <CardDescription>{t("dashboard:analytics.charts.reportsByCategoryDesc")}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              {categories.length === 0 ? (
                <EmptyChart>
                  {filterActive
                    ? t("dashboard:analytics.empty.filteredDesc")
                    : t("dashboard:analytics.empty.category")}
                </EmptyChart>
              ) : (
                <ResponsiveContainer>
                  <PieChart>
                    <Pie data={categories} dataKey="value" nameKey="name" outerRadius={90} label fontSize={12}>
                      {categories.map((_, i) => <Cell key={i} fill={PIE[i % PIE.length]} />)}
                    </Pie>
                    <Tooltip {...tooltip} />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                  </PieChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:analytics.charts.monthlyTrends")}</CardTitle>
            <CardDescription>{t("dashboard:analytics.charts.monthlyTrendsDesc")}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-80">
              <ResponsiveContainer>
                <LineChart data={trend}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                  <XAxis dataKey="bucket" stroke="var(--muted-foreground)" fontSize={12} />
                  <YAxis stroke="var(--muted-foreground)" fontSize={12} />
                  <Tooltip {...tooltip} />
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                  <Line type="monotone" dataKey="reports" name={t("dashboard:analytics.series.reports")} stroke="var(--chart-1)" strokeWidth={2.5} dot={false} />
                  <Line type="monotone" dataKey="cases" name={t("dashboard:analytics.series.cases")} stroke="var(--chart-2)" strokeWidth={2.5} dot={false} />
                  <Line type="monotone" dataKey="evidence" name={t("dashboard:analytics.series.evidence")} stroke="var(--chart-3)" strokeWidth={2.5} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:analytics.charts.evidenceClassification")}</CardTitle>
            <CardDescription>{t("dashboard:analytics.evidencePrivacy")}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-80">
              {evidenceClasses.length === 0 ? (
                <EmptyChart>
                  {filterActive
                    ? t("dashboard:analytics.empty.filteredDesc")
                    : t("dashboard:analytics.empty.evidenceClassification")}
                </EmptyChart>
              ) : (
                <ResponsiveContainer>
                  <PieChart>
                    <Pie data={evidenceClasses} dataKey="value" nameKey="name" innerRadius={58} outerRadius={90}>
                      {evidenceClasses.map((_, i) => <Cell key={i} fill={PIE[i % PIE.length]} />)}
                    </Pie>
                    <Tooltip {...tooltip} />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                  </PieChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("dashboard:analytics.summary.title")}</CardTitle>
          <CardDescription>{t("dashboard:analytics.summary.description")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
          {Object.entries(summary.totals).map(([key, value]) => (
            <div key={key} className="rounded-lg border p-3">
              <div className="text-muted-foreground">{formatSummaryTotal(t, key)}</div>
              <div className="mt-1 text-lg font-semibold">{value}</div>
            </div>
          ))}
        </CardContent>
      </Card>
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

function StatCard({ label, value, description }: { label: string; value: string | number; description: string }) {
  return (
    <Card>
      <CardContent className="p-5">
        <div className="text-sm text-muted-foreground">{label}</div>
        <div className="mt-2 text-3xl font-semibold">{value}</div>
        <div className="mt-1 text-xs text-muted-foreground">{description}</div>
      </CardContent>
    </Card>
  );
}

function EmptyChart({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
      {children}
    </div>
  );
}

function formatSummaryTotal(t: ReturnType<typeof useTranslation>["t"], key: string) {
  return t(`dashboard:analytics.summary.totals.${key}`, {
    defaultValue: t("dashboard:common.notAvailable"),
  });
}

function formatScope(t: ReturnType<typeof useTranslation>["t"], scope: string) {
  return t(`dashboard:analytics.scopes.${scope}`, {
    defaultValue: t("dashboard:common.notAvailable"),
  });
}

function analyticsLabelKey(value: unknown) {
  if (value === null || value === undefined || value === "") return null;
  if (typeof value === "string") return value;
  if (typeof value === "number" || typeof value === "boolean" || typeof value === "bigint") return String(value);

  if (typeof value === "object") {
    const record = value as Record<string, unknown>;
    for (const key of ["code", "name", "label", "title", "value", "key"]) {
      const item = record[key];
      if (typeof item === "string" && item.trim()) return item;
      if (typeof item === "number" || typeof item === "boolean" || typeof item === "bigint") return String(item);
    }
  }

  return String(value);
}
