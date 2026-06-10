import { createFileRoute } from "@tanstack/react-router";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { mockCases } from "@/mock-data";
import { Check, FileSearch, Gavel, Handshake, Inbox, Lock } from "lucide-react";
import type { CaseStatus } from "@/types";

export const Route = createFileRoute("/dashboard/workflow")({
  component: WorkflowPage,
  head: () => ({ meta: [{ title: "Workflow — SafeCampus Admin" }] }),
});

const stages: { key: CaseStatus | "initial"; label: string; icon: React.ComponentType<{ className?: string }> }[] = [
  { key: "received", label: "Report received", icon: Inbox },
  { key: "verification", label: "Initial review", icon: FileSearch },
  { key: "investigation", label: "Investigation", icon: Gavel },
  { key: "mediation", label: "Mediation", icon: Handshake },
  { key: "resolved", label: "Resolution", icon: Check },
  { key: "closed", label: "Closed", icon: Lock },
];

function WorkflowPage() {
  const counts = stages.map((s) => ({
    ...s,
    count: mockCases.filter((c) => c.status === s.key).length,
  }));
  const total = mockCases.length;
  const sample = mockCases.slice(0, 4);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Investigation workflow</h1>
        <p className="text-sm text-muted-foreground">
          A clear path from intake to resolution, designed for confidential, trauma-informed handling.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Pipeline</CardTitle>
          <CardDescription>Live distribution across the six workflow stages</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="relative">
            <div className="absolute left-0 right-0 top-6 hidden h-px bg-border md:block" />
            <ol className="grid gap-6 md:grid-cols-6">
              {counts.map((s, i) => (
                <li key={s.key} className="relative flex flex-col items-center text-center">
                  <div className="z-10 flex h-12 w-12 items-center justify-center rounded-full border-2 border-primary/30 bg-card text-primary">
                    <s.icon className="h-5 w-5" />
                  </div>
                  <div className="mt-3 text-xs uppercase tracking-wide text-muted-foreground">Stage {i + 1}</div>
                  <div className="text-sm font-medium">{s.label}</div>
                  <Badge variant="secondary" className="mt-2">{s.count} cases</Badge>
                </li>
              ))}
            </ol>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        {sample.map((c) => {
          const idx = stages.findIndex((s) => s.key === c.status);
          const pct = ((idx + 1) / stages.length) * 100;
          return (
            <Card key={c.id}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="font-mono text-sm">{c.id}</CardTitle>
                  <Badge variant="outline" className="capitalize">{c.status}</Badge>
                </div>
                <CardDescription>
                  {c.faculty} · {c.category} · Officer: {c.assignedOfficer}
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <Progress value={pct} />
                <ol className="relative space-y-3 border-l border-border pl-4 text-sm">
                  {c.timeline.slice(-3).map((t, i) => (
                    <li key={i} className="relative">
                      <span className="absolute -left-[21px] top-1 h-3 w-3 rounded-full border-2 border-background bg-primary" />
                      <div className="font-medium">{t.action}</div>
                      <div className="text-xs text-muted-foreground">
                        {t.actor} · {new Date(t.date).toLocaleDateString()}
                      </div>
                    </li>
                  ))}
                </ol>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="text-xs text-muted-foreground">
        Tracking {total} active cases across the workflow.
      </div>
    </div>
  );
}
