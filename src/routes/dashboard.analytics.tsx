import { createFileRoute } from "@tanstack/react-router";
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
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { categoryDistribution, facultyDistribution, monthlyTrend, mockCases } from "@/mock-data";

export const Route = createFileRoute("/dashboard/analytics")({
  component: AnalyticsPage,
  head: () => ({ meta: [{ title: "Analytics — SafeCampus Admin" }] }),
});

const PIE = ["var(--chart-1)","var(--chart-2)","var(--chart-3)","var(--chart-4)","var(--chart-5)","var(--muted-foreground)"];

function AnalyticsPage() {
  const total = mockCases.length;
  const resolved = mockCases.filter((c) => c.status === "resolved" || c.status === "closed").length;
  const resolutionRate = Math.round((resolved / total) * 100);
  const avgDays = 12;

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
          Trends, faculty breakdowns, and resolution metrics across all reports.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Total cases</div>
          <div className="mt-2 text-3xl font-semibold">{total}</div>
          <div className="mt-1 text-xs text-success">+18% YoY</div>
        </CardContent></Card>
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Resolution rate</div>
          <div className="mt-2 text-3xl font-semibold">{resolutionRate}%</div>
          <div className="mt-1 text-xs text-muted-foreground">Resolved or closed</div>
        </CardContent></Card>
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Avg. handling time</div>
          <div className="mt-2 text-3xl font-semibold">{avgDays}d</div>
          <div className="mt-1 text-xs text-success">-2d vs last quarter</div>
        </CardContent></Card>
        <Card><CardContent className="p-5">
          <div className="text-sm text-muted-foreground">Anonymous share</div>
          <div className="mt-2 text-3xl font-semibold">
            {Math.round((mockCases.filter((c) => c.anonymous).length / total) * 100)}%
          </div>
          <div className="mt-1 text-xs text-muted-foreground">of all reports</div>
        </CardContent></Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Cases by faculty</CardTitle>
            <CardDescription>Distribution across academic units</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              <ResponsiveContainer>
                <BarChart data={facultyDistribution} layout="vertical" margin={{ left: 30 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />
                  <XAxis type="number" stroke="var(--muted-foreground)" fontSize={12} />
                  <YAxis dataKey="faculty" type="category" stroke="var(--muted-foreground)" fontSize={11} width={120} />
                  <Tooltip {...tooltip} />
                  <Bar dataKey="cases" fill="var(--chart-1)" radius={[0, 4, 4, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Cases by category</CardTitle>
            <CardDescription>Type of reported incident</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-72">
              <ResponsiveContainer>
                <PieChart>
                  <Pie data={categoryDistribution} dataKey="value" nameKey="name" outerRadius={90} label fontSize={12}>
                    {categoryDistribution.map((_, i) => <Cell key={i} fill={PIE[i % PIE.length]} />)}
                  </Pie>
                  <Tooltip {...tooltip} />
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Monthly trends</CardTitle>
          <CardDescription>Reports vs resolutions over the year</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="h-80">
            <ResponsiveContainer>
              <LineChart data={monthlyTrend}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                <XAxis dataKey="month" stroke="var(--muted-foreground)" fontSize={12} />
                <YAxis stroke="var(--muted-foreground)" fontSize={12} />
                <Tooltip {...tooltip} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Line type="monotone" dataKey="reports" stroke="var(--chart-1)" strokeWidth={2.5} dot={false} />
                <Line type="monotone" dataKey="resolved" stroke="var(--chart-2)" strokeWidth={2.5} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
