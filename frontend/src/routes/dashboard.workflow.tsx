import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, ClipboardCheck, FileSearch, Gavel, HeartHandshake, Scale } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { QueryErrorState } from "@/components/query-state";
import { dashboardQueryKeys, getDashboardWorkflow } from "@/lib/dashboard-api";

export const Route = createFileRoute("/dashboard/workflow")({
  component: WorkflowPage,
  head: () => ({ meta: [{ title: "Workflow - SafeCampus Admin" }] }),
});

const steps = [
  {
    key: "reports_forwarded_to_cases",
    label: "Forwarded reports",
    icon: ClipboardCheck,
  },
  {
    key: "cases_with_investigations",
    label: "Investigations",
    icon: FileSearch,
  },
  {
    key: "cases_with_recommendations",
    label: "Recommendations",
    icon: Gavel,
  },
  {
    key: "recommendations_with_decisions",
    label: "Decisions",
    icon: Scale,
  },
  {
    key: "decisions_with_recoveries",
    label: "Recoveries",
    icon: HeartHandshake,
  },
] as const;

function labelFromKey(key: string | null) {
  if (!key) return "Unspecified";
  return key
    .replace(/[-_]/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function WorkflowPage() {
  const workflowQuery = useQuery({
    queryKey: dashboardQueryKeys.workflow(),
    queryFn: () => getDashboardWorkflow(),
  });

  if (workflowQuery.isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Investigation workflow</h1>
          <p className="text-sm text-muted-foreground">
            Loading backend workflow analytics.
          </p>
        </div>
        <Card>
          <CardContent className="p-6 text-sm text-muted-foreground">Loading workflow...</CardContent>
        </Card>
      </div>
    );
  }

  if (workflowQuery.isError || !workflowQuery.data) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Investigation workflow</h1>
          <p className="text-sm text-muted-foreground">
            A clear path from intake to resolution, designed for confidential handling.
          </p>
        </div>
        <QueryErrorState
          message="Workflow analytics are unavailable."
          onRetry={() => workflowQuery.refetch()}
        />
      </div>
    );
  }

  const workflow = workflowQuery.data;
  const maxConversion = Math.max(...Object.values(workflow.conversion_counts), 1);
  const distributions = [
    { title: "Investigations", rows: workflow.status_distributions.investigations },
    { title: "Recommendations", rows: workflow.status_distributions.recommendations },
    { title: "Decisions", rows: workflow.status_distributions.decisions },
    { title: "Recoveries", rows: workflow.status_distributions.recoveries },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Investigation workflow</h1>
        <p className="text-sm text-muted-foreground">
          Backend workflow counts scoped to the authenticated user's role and assignments.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Pipeline conversion counts</CardTitle>
          <CardDescription>{workflow.metric_semantics}</CardDescription>
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
                    Step {index + 1}
                  </div>
                  <div className="mt-1 text-sm font-medium">{step.label}</div>
                  <div className="mt-2 text-2xl font-semibold">{count}</div>
                  <Progress value={pct} className="mt-3" />
                </li>
              );
            })}
          </ol>
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        {distributions.map((group) => (
          <Card key={group.title}>
            <CardHeader>
              <CardTitle>{group.title}</CardTitle>
              <CardDescription>Status distribution from backend workflow analytics</CardDescription>
            </CardHeader>
            <CardContent>
              {group.rows.length === 0 ? (
                <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                  No status data available.
                </div>
              ) : (
                <div className="space-y-3">
                  {group.rows.map((row) => (
                    <div key={row.key ?? "unspecified"} className="flex items-center justify-between rounded-lg border p-3">
                      <div className="text-sm font-medium">{labelFromKey(row.key)}</div>
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
            <CardTitle>Decision outcomes</CardTitle>
            <CardDescription>Descriptive counts only</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {workflow.decision_outcomes.length === 0 ? (
              <div className="text-sm text-muted-foreground">No decision outcome data available.</div>
            ) : (
              workflow.decision_outcomes.map((row) => (
                <div key={row.key ?? "unspecified"} className="flex items-center justify-between rounded-lg border p-3">
                  <span className="text-sm font-medium">{labelFromKey(row.key)}</span>
                  <Badge variant="outline">{row.count}</Badge>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Recovery types</CardTitle>
            <CardDescription>Recovery distribution from backend analytics</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {workflow.recovery_types.length === 0 ? (
              <div className="text-sm text-muted-foreground">No recovery type data available.</div>
            ) : (
              workflow.recovery_types.map((row) => (
                <div key={row.key ?? "unspecified"} className="flex items-center justify-between rounded-lg border p-3">
                  <span className="text-sm font-medium">{labelFromKey(row.key)}</span>
                  <Badge variant="outline">{row.count}</Badge>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>

      <div className="text-xs text-muted-foreground">
        Scope: {workflow.scope}. Range: {workflow.filters.date_from} to {workflow.filters.date_to}.
      </div>
    </div>
  );
}
