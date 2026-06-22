import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Lock } from "lucide-react";
import { AccessDenied } from "@/components/access-denied";
import { BreakGlassRequestDialog } from "@/components/admin/break-glass-request-dialog";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { SatgasAssignmentAction } from "@/components/workflow-actions/satgas-assignment-action";
import { useAuth } from "@/hooks/use-auth";
import { getReport, operationsQueryKeys } from "@/lib/operations-api";
import type { ReportReporter } from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/reports/$id")({
  component: ReportDetailPage,
  head: () => ({ meta: [{ title: "Report detail - SafeCampus Admin" }] }),
});

function ReportDetailPage() {
  const { id } = Route.useParams();
  const { roleCode, user } = useAuth();
  const reportQuery = useQuery({
    queryKey: operationsQueryKeys.report(id),
    queryFn: () => getReport(id),
    enabled: roleCode === "super_admin" || roleCode === "admin",
  });

  if (roleCode !== "super_admin" && roleCode !== "admin") {
    return <AccessDenied />;
  }

  if (reportQuery.isLoading) {
    return <div className="py-12 text-center text-sm text-muted-foreground">Loading report...</div>;
  }

  if (reportQuery.isError || !reportQuery.data) {
    return <QueryErrorState message="Report could not be loaded." onRetry={() => reportQuery.refetch()} />;
  }

  const report = reportQuery.data;
  const canRequestBreakGlass = Boolean(user?.permissions?.includes("privacy.request_break_glass"));
  const isAnonymousReport = Boolean(report.is_anonymous || report.report_type === "anonymous");

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/dashboard/reports">
            <ArrowLeft className="mr-2 h-4 w-4" /> All reports
          </Link>
        </Button>
        <div className="flex items-center gap-2">
          <h1 className="font-mono text-lg font-semibold">{report.registration_number}</h1>
          <Badge variant="outline">{label(report.status)}</Badge>
          {isAnonymousReport && (
            <Badge variant="outline" className="gap-1 text-muted-foreground">
              <Lock className="h-3 w-3" /> Anonymous
            </Badge>
          )}
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">Report metadata</CardTitle>
            <CardDescription>
              Backend returns metadata only for operational report detail.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
            <Field label="Registration">{report.registration_number}</Field>
            <Field label="Report type">{label(report.report_type)}</Field>
            <Field label="Reporter">{reporterDisplay(report.reporter)}</Field>
            <Field label="Category">{report.category?.name ?? "Metadata unavailable"}</Field>
            <Field label="Priority">{report.priority?.name ?? "-"}</Field>
            <Field label="Submitted">{formatDate(report.submitted_at)}</Field>
            <Field label="Reviewed">{formatDate(report.reviewed_at)}</Field>
            <Field label="Forwarded">{formatDate(report.forwarded_at)}</Field>
            <Field label="Created">{formatDate(report.created_at)}</Field>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Actions</CardTitle>
            <CardDescription>Forwarding uses the approved Satgas lookup API.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <SatgasAssignmentAction mode="forward-report" targetId={report.id} />
            {isAnonymousReport && canRequestBreakGlass && (
              <BreakGlassRequestDialog
                reportId={report.id}
                registrationNumber={report.registration_number}
              />
            )}
            <p className="text-xs text-muted-foreground">
              Select one or more Satgas users and choose a lead from the selected users.
              Backend RBAC remains authoritative.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 text-sm">{children}</div>
    </div>
  );
}

function label(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function reporterDisplay(reporter: ReportReporter | null | undefined) {
  if (!reporter) {
    return <span className="text-muted-foreground">Metadata unavailable</span>;
  }

  if ("masked" in reporter && reporter.masked === true) {
    return <span className="text-muted-foreground">Reporter identity hidden</span>;
  }

  return reporter.name;
}

function formatDate(value: string | null | undefined) {
  return value ? new Date(value).toLocaleString() : "-";
}
