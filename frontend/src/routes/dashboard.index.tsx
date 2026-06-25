import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import {
  AreaChart,
  Area,
  ResponsiveContainer,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  PieChart,
  Pie,
  Cell,
  Legend,
} from "recharts";
import { FileWarning, Inbox, Loader2, CheckCircle2, Clock, FileArchive } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { QueryErrorState, StatSkeletonGrid } from "@/components/query-state";
import { formatGenericLabel } from "@/lib/format-labels";
import {
  dashboardQueryKeys,
  getDashboardReports,
  getDashboardSummary,
} from "@/lib/dashboard-api";

export const Route = createFileRoute("/dashboard/")({
  component: Overview,
  head: () => ({ meta: [{ title: "Overview - SILAPPKASAL Admin" }] }),
});

const PIE_COLORS = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
  "var(--muted-foreground)",
];

function StatCard({
  label,
  value,
  delta,
  icon: Icon,
  tone,
}: {
  label: string;
  value: number;
  delta: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: string;
}) {
  return (
    <Card className="overflow-hidden">
      <CardContent className="p-5">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-2 text-3xl font-semibold tracking-tight">{value}</p>
            <p className="mt-1 text-xs text-muted-foreground">{delta}</p>
          </div>
          <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${tone}`}>
            <Icon className="h-5 w-5" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function Overview() {
  const { t } = useTranslation(["dashboard"]);
  const summaryQuery = useQuery({
    queryKey: dashboardQueryKeys.summary(),
    queryFn: () => getDashboardSummary(),
  });
  const reportsQuery = useQuery({
    queryKey: dashboardQueryKeys.reports(),
    queryFn: () => getDashboardReports(),
  });

  if (summaryQuery.isLoading || reportsQuery.isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:overview.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:overview.subtitle")}</p>
        </div>
        <StatSkeletonGrid />
      </div>
    );
  }

  if (summaryQuery.isError || reportsQuery.isError || !summaryQuery.data || !reportsQuery.data) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:overview.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:overview.subtitle")}</p>
        </div>
        <QueryErrorState
          message={t("dashboard:overview.unavailable")}
          onRetry={() => {
            summaryQuery.refetch();
            reportsQuery.refetch();
          }}
        />
      </div>
    );
  }

  const summary = summaryQuery.data;
  const reports = reportsQuery.data;
  const trend = summary.time_series.reports.map((point) => ({
    bucket: point.bucket,
    reports: point.count,
    cases: summary.time_series.cases.find((item) => item.bucket === point.bucket)?.count ?? 0,
  }));
  const categoryDistribution = reports.by_category_code.map((item) => ({
    name: formatGenericLabel(item.key),
    value: item.count,
  }));

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:overview.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:overview.subtitle")}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <StatCard label={t("dashboard:overview.totalReports")} value={summary.totals.reports} delta={summary.scope} icon={Inbox} tone="bg-primary/10 text-primary" />
        <StatCard label={t("dashboard:overview.cases")} value={summary.totals.cases} delta={t("dashboard:overview.forwardedReports")} icon={FileWarning} tone="bg-info/10 text-info" />
        <StatCard label={t("dashboard:overview.openCases")} value={summary.active_workflow.cases_open} delta={t("dashboard:overview.currentlyActive")} icon={Loader2} tone="bg-warning/10 text-warning" />
        <StatCard label={t("dashboard:overview.investigations")} value={summary.totals.investigations} delta={`${summary.active_workflow.investigations_open} ${t("dashboard:overview.open")}`} icon={CheckCircle2} tone="bg-success/10 text-success" />
        <StatCard label={t("dashboard:overview.pendingDecisions")} value={summary.active_workflow.decisions_not_finalized} delta={t("dashboard:overview.notFinalized")} icon={Clock} tone="bg-accent/40 text-accent-foreground" />
        <StatCard label={t("dashboard:overview.evidenceRecords")} value={summary.totals.evidences} delta={t("dashboard:overview.metadataCountsOnly")} icon={FileArchive} tone="bg-muted text-muted-foreground" />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>{t("dashboard:overview.reportsAndCases")}</CardTitle>
            <CardDescription>{t("dashboard:overview.reportsAndCasesDesc")}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72 w-full">
              <ResponsiveContainer>
                <AreaChart data={trend} margin={{ left: -10, right: 10, top: 10 }}>
                  <defs>
                    <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.5} />
                      <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="g2" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="var(--chart-2)" stopOpacity={0.5} />
                      <stop offset="100%" stopColor="var(--chart-2)" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                  <XAxis dataKey="bucket" stroke="var(--muted-foreground)" fontSize={12} />
                  <YAxis stroke="var(--muted-foreground)" fontSize={12} />
                  <Tooltip
                    contentStyle={{
                      background: "var(--popover)",
                      border: "1px solid var(--border)",
                      borderRadius: 8,
                      fontSize: 12,
                    }}
                  />
                  <Area type="monotone" dataKey="reports" stroke="var(--chart-1)" fill="url(#g1)" strokeWidth={2} />
                  <Area type="monotone" dataKey="cases" stroke="var(--chart-2)" fill="url(#g2)" strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard:overview.categoryDistribution")}</CardTitle>
            <CardDescription>{t("dashboard:overview.categoryDistributionDesc")}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72 w-full">
              {categoryDistribution.length === 0 ? (
                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                  {t("dashboard:overview.noCategoryData")}
                </div>
              ) : (
                <ResponsiveContainer>
                  <PieChart>
                    <Pie
                      data={categoryDistribution}
                      dataKey="value"
                      nameKey="name"
                      innerRadius={50}
                      outerRadius={80}
                      paddingAngle={2}
                    >
                      {categoryDistribution.map((_, i) => (
                        <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip
                      contentStyle={{
                        background: "var(--popover)",
                        border: "1px solid var(--border)",
                        borderRadius: 8,
                        fontSize: 12,
                      }}
                    />
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
          <CardTitle>{t("dashboard:overview.currentScope")}</CardTitle>
          <CardDescription>{t("dashboard:overview.currentScopeDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 text-sm sm:grid-cols-3">
          <div className="rounded-lg border p-4">
            <div className="text-muted-foreground">{t("dashboard:common.scope")}</div>
            <div className="mt-1 font-medium">{summary.scope}</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-muted-foreground">{t("dashboard:common.dateRange")}</div>
            <div className="mt-1 font-medium">
              {summary.filters.date_from} - {summary.filters.date_to}
            </div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-muted-foreground">{t("dashboard:common.granularity")}</div>
            <div className="mt-1 font-medium">{summary.filters.granularity}</div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
