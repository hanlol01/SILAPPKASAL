import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
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
import { QueryErrorState, StatSkeletonGrid } from "@/components/query-state";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";
import {
  dashboardQueryKeys,
  getDashboardCases,
  getDashboardEvidence,
  getDashboardReports,
  getDashboardSummary,
} from "@/lib/dashboard-api";
import {
  formatCaseStatus,
  formatEvidenceClassification,
  formatReportCategory,
} from "@/lib/format-labels";
import { PageBreadcrumb } from "@/components/page-breadcrumb";

export const Route = createFileRoute("/dashboard/analytics")({
  component: AnalyticsPage,
  head: () => ({ meta: [{ title: "Analytics - SILAPPKASAL Admin" }] }),
});

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
  const canViewAnalytics = roleCode === "super_admin" || roleCode === "admin";

  const summaryQuery = useQuery({
    queryKey: dashboardQueryKeys.summary(),
    queryFn: () => getDashboardSummary(),
    enabled: canViewAnalytics,
  });
  const reportsQuery = useQuery({
    queryKey: dashboardQueryKeys.reports(),
    queryFn: () => getDashboardReports(),
    enabled: canViewAnalytics,
  });
  const casesQuery = useQuery({
    queryKey: dashboardQueryKeys.cases(),
    queryFn: () => getDashboardCases(),
    enabled: canViewAnalytics,
  });
  const evidenceQuery = useQuery({
    queryKey: dashboardQueryKeys.evidence(),
    queryFn: () => getDashboardEvidence(),
    enabled: canViewAnalytics,
  });

  if (!canViewAnalytics) {
    return <AccessDenied />;
  }

  const isLoading =
    summaryQuery.isLoading || reportsQuery.isLoading || casesQuery.isLoading || evidenceQuery.isLoading;
  const isError =
    summaryQuery.isError || reportsQuery.isError || casesQuery.isError || evidenceQuery.isError;

  if (isLoading) {
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
    !evidenceQuery.data
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
          }}
        />
      </div>
    );
  }

  const summary = summaryQuery.data;
  const reports = reportsQuery.data;
  const cases = casesQuery.data;
  const evidence = evidenceQuery.data;
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
      <PageHeader
        title={t("dashboard:analytics.title")}
        description={t("dashboard:analytics.subtitle")}
      />

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
                <EmptyChart>{t("dashboard:analytics.empty.caseStage")}</EmptyChart>
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
                <EmptyChart>{t("dashboard:analytics.empty.category")}</EmptyChart>
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
                <EmptyChart>{t("dashboard:analytics.empty.evidenceClassification")}</EmptyChart>
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
