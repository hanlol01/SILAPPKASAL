import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
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

export const Route = createFileRoute("/dashboard/analytics")({
  component: AnalyticsPage,
  head: () => ({ meta: [{ title: "Analytics - SafeCampus Admin" }] }),
});

const PIE = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
  "var(--muted-foreground)",
];

function labelFromKey(key: string | null) {
  if (!key) return "Unspecified";
  return key
    .replace(/[-_]/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function AnalyticsPage() {
  const { roleCode } = useAuth();
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
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Analytics</h1>
          <p className="text-sm text-muted-foreground">
            Backend dashboard analytics across reports, cases, workflow, and evidence metadata.
          </p>
        </div>
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
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Analytics</h1>
          <p className="text-sm text-muted-foreground">
            Backend dashboard analytics across reports, cases, workflow, and evidence metadata.
          </p>
        </div>
        <QueryErrorState
          message="Analytics data is unavailable."
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
    stage: labelFromKey(item.key),
    count: item.count,
  }));
  const categories = reports.by_category_code.map((item) => ({
    name: labelFromKey(item.key),
    value: item.count,
  }));
  const evidenceClasses = evidence.by_classification.map((item) => ({
    name: labelFromKey(item.key),
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
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Analytics</h1>
        <p className="text-sm text-muted-foreground">
          Backend dashboard analytics across reports, cases, workflow, and evidence metadata.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Total reports</div>
          <div className="mt-2 text-3xl font-semibold">{reports.total}</div>
          <div className="mt-1 text-xs text-muted-foreground">{reports.scope}</div>
        </CardContent></Card>
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Total cases</div>
          <div className="mt-2 text-3xl font-semibold">{cases.total}</div>
          <div className="mt-1 text-xs text-muted-foreground">{cases.assignments.assigned_cases} assigned</div>
        </CardContent></Card>
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Evidence records</div>
          <div className="mt-2 text-3xl font-semibold">{evidence.total}</div>
          <div className="mt-1 text-xs text-muted-foreground">Metadata counts only</div>
        </CardContent></Card>
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Anonymous share</div>
          <div className="mt-2 text-3xl font-semibold">{anonymousShare}%</div>
          <div className="mt-1 text-xs text-muted-foreground">of reports in range</div>
        </CardContent></Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Cases by stage</CardTitle>
            <CardDescription>Distribution from `/dashboard/cases`</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              {caseStages.length === 0 ? (
                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                  No case stage data available.
                </div>
              ) : (
                <ResponsiveContainer>
                  <BarChart data={caseStages} layout="vertical" margin={{ left: 30 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />
                    <XAxis type="number" stroke="var(--muted-foreground)" fontSize={12} />
                    <YAxis dataKey="stage" type="category" stroke="var(--muted-foreground)" fontSize={11} width={130} />
                    <Tooltip {...tooltip} />
                    <Bar dataKey="count" fill="var(--chart-1)" radius={[0, 4, 4, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Reports by category</CardTitle>
            <CardDescription>Category counts from `/dashboard/reports`</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              {categories.length === 0 ? (
                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                  No category data available.
                </div>
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
            <CardTitle>Monthly trends</CardTitle>
            <CardDescription>Reports, cases, and evidence metadata over time</CardDescription>
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
                  <Line type="monotone" dataKey="reports" stroke="var(--chart-1)" strokeWidth={2.5} dot={false} />
                  <Line type="monotone" dataKey="cases" stroke="var(--chart-2)" strokeWidth={2.5} dot={false} />
                  <Line type="monotone" dataKey="evidence" stroke="var(--chart-3)" strokeWidth={2.5} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Evidence classification</CardTitle>
            <CardDescription>{evidence.privacy}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-80">
              {evidenceClasses.length === 0 ? (
                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                  No evidence classification data available.
                </div>
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
          <CardTitle>Backend summary</CardTitle>
          <CardDescription>Totals from `/dashboard/summary`</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
          {Object.entries(summary.totals).map(([key, value]) => (
            <div key={key} className="rounded-lg border p-3">
              <div className="text-muted-foreground">{labelFromKey(key)}</div>
              <div className="mt-1 text-lg font-semibold">{value}</div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
