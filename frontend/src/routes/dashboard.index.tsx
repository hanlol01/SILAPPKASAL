import { createFileRoute, Link } from "@tanstack/react-router";
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
import {
  FileWarning,
  Inbox,
  Loader2,
  CheckCircle2,
  Clock,
  EyeOff,
  ArrowRight,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/status-badge";
import { mockCases, monthlyTrend, categoryDistribution } from "@/mock-data";

export const Route = createFileRoute("/dashboard/")({
  component: Overview,
  head: () => ({ meta: [{ title: "Overview — SafeCampus Admin" }] }),
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
  const total = mockCases.length;
  const newReports = mockCases.filter((c) => c.status === "received").length;
  const inProgress = mockCases.filter((c) =>
    ["verification", "investigation", "mediation"].includes(c.status),
  ).length;
  const resolved = mockCases.filter((c) => c.status === "resolved").length;
  const pending = mockCases.filter((c) => c.status === "verification").length;
  const anonymous = mockCases.filter((c) => c.anonymous).length;
  const recent = [...mockCases]
    .sort((a, b) => +new Date(b.date) - +new Date(a.date))
    .slice(0, 5);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Dashboard overview</h1>
        <p className="text-sm text-muted-foreground">
          Real-time view of incoming reports, case progress, and team activity.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <StatCard label="Total reports" value={total} delta="+12% vs last month" icon={Inbox} tone="bg-primary/10 text-primary" />
        <StatCard label="New reports" value={newReports} delta="Last 7 days" icon={FileWarning} tone="bg-info/10 text-info" />
        <StatCard label="In progress" value={inProgress} delta="Across 3 stages" icon={Loader2} tone="bg-warning/10 text-warning" />
        <StatCard label="Resolved" value={resolved} delta="This quarter" icon={CheckCircle2} tone="bg-success/10 text-success" />
        <StatCard label="Pending review" value={pending} delta="Awaiting verification" icon={Clock} tone="bg-accent/40 text-accent-foreground" />
        <StatCard label="Anonymous" value={anonymous} delta={`${Math.round((anonymous / total) * 100)}% of total`} icon={EyeOff} tone="bg-muted text-muted-foreground" />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Monthly reports</CardTitle>
            <CardDescription>Reports received vs resolved per month</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72 w-full">
              <ResponsiveContainer>
                <AreaChart data={monthlyTrend} margin={{ left: -10, right: 10, top: 10 }}>
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
                  <XAxis dataKey="month" stroke="var(--muted-foreground)" fontSize={12} />
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
                  <Area type="monotone" dataKey="resolved" stroke="var(--chart-2)" fill="url(#g2)" strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Category distribution</CardTitle>
            <CardDescription>Reports by incident type</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72 w-full">
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
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader className="flex-row items-center justify-between space-y-0">
            <div>
              <CardTitle>Recent reports</CardTitle>
              <CardDescription>Latest 5 submitted cases</CardDescription>
            </div>
            <Button asChild variant="ghost" size="sm">
              <Link to="/dashboard/cases">
                View all <ArrowRight className="ml-1 h-3.5 w-3.5" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent>
            <div className="overflow-hidden rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">ID</th>
                    <th className="px-3 py-2 text-left">Faculty</th>
                    <th className="px-3 py-2 text-left">Category</th>
                    <th className="px-3 py-2 text-left">Status</th>
                    <th className="px-3 py-2 text-left">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {recent.map((c) => (
                    <tr key={c.id} className="border-t hover:bg-muted/40">
                      <td className="px-3 py-2 font-mono text-xs">
                        <Link to="/dashboard/cases/$id" params={{ id: c.id }} className="hover:underline">
                          {c.id}
                        </Link>
                      </td>
                      <td className="px-3 py-2">{c.faculty}</td>
                      <td className="px-3 py-2 capitalize">{c.category}</td>
                      <td className="px-3 py-2">
                        <StatusBadge status={c.status} />
                      </td>
                      <td className="px-3 py-2 text-muted-foreground">
                        {new Date(c.date).toLocaleDateString()}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Activity feed</CardTitle>
            <CardDescription>Latest team actions</CardDescription>
          </CardHeader>
          <CardContent>
            <ol className="space-y-4">
              {[
                { who: "Maya Lestari", what: "verified RPT-2025-1024", when: "2h ago" },
                { who: "Andi Wijaya", what: "moved RPT-2025-1019 to Investigation", when: "4h ago" },
                { who: "Dewi Anggraini", what: "added internal note on RPT-2025-1011", when: "Yesterday" },
                { who: "System", what: "received 3 new anonymous reports", when: "Yesterday" },
                { who: "Dr. Sarah Putri", what: "published a prevention article", when: "2 days ago" },
              ].map((e, i) => (
                <li key={i} className="flex gap-3">
                  <div className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />
                  <div className="text-sm">
                    <span className="font-medium">{e.who}</span>{" "}
                    <span className="text-muted-foreground">{e.what}</span>
                    <div className="text-xs text-muted-foreground">{e.when}</div>
                  </div>
                </li>
              ))}
            </ol>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
