import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { QueryErrorState } from "@/components/query-state";
import { PortalStatusBadge } from "@/components/portal/portal-status-badge";
import { portalQueryKeys, getPortalReport } from "@/lib/portal-api";
import { label as humanize, formatDate } from "@/lib/format";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";

export const Route = createFileRoute("/portal/reports/$registrationNumber")({
  component: PortalReportDetailPage,
  head: () => ({
    meta: [
      { title: "Report Detail — SafeCampus Portal" },
      {
        name: "description",
        content: "View the current status and details of your report.",
      },
    ],
  }),
});

function PortalReportDetailPage() {
  const { registrationNumber } = Route.useParams();
  const { roleCode } = useAuth();
  const navigate = useNavigate();

  const reportQuery = useQuery({
    queryKey: portalQueryKeys.report(registrationNumber),
    queryFn: () => getPortalReport(registrationNumber),
    enabled: hasPortalAccess(roleCode),
  });

  return (
    <div className="space-y-6">
      {/* Back navigation */}
      <Button
        variant="ghost"
        size="sm"
        onClick={() => navigate({ to: "/portal/reports" as "/" })}
      >
        <ArrowLeft className="mr-2 h-4 w-4" /> My Reports
      </Button>

      {/* Loading */}
      {reportQuery.isLoading && <DetailSkeleton />}

      {/* Error */}
      {reportQuery.isError && (
        <QueryErrorState
          message="This report could not be loaded."
          onRetry={() => reportQuery.refetch()}
        />
      )}

      {/* Success */}
      {reportQuery.isSuccess && <ReportDetail report={reportQuery.data} />}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Report detail content — renders only PortalReportDetail safe fields
// ---------------------------------------------------------------------------

interface ReportDetailProps {
  report: import("@/lib/portal-types").PortalReportDetail;
}

function ReportDetail({ report }: ReportDetailProps) {
  return (
    <>
      {/* Header */}
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="font-mono text-lg font-semibold">
          {report.registration_number}
        </h1>
        <PortalStatusBadge
          status={report.status}
          statusLabel={report.status_label}
        />
      </div>

      {/* Detail card */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Report Information</CardTitle>
          <CardDescription>
            Details for your submitted report.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
          <Field label="Registration Number">
            {report.registration_number}
          </Field>
          <Field label="Report Type">{humanize(report.report_type)}</Field>
          <Field label="Category">
            {report.category_name ?? "—"}
          </Field>
          <Field label="Status">
            <PortalStatusBadge
              status={report.status}
              statusLabel={report.status_label}
            />
          </Field>
          <Field label="Current Stage">
            {report.current_stage_label ?? "—"}
          </Field>
          <Field label="Submitted">{formatDate(report.submitted_at)}</Field>
          <Field label="Last Updated">
            {formatDate(report.last_updated_at)}
          </Field>
        </CardContent>
      </Card>
    </>
  );
}

// ---------------------------------------------------------------------------
// Field component — matches admin detail page pattern
// ---------------------------------------------------------------------------

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">
        {label}
      </div>
      <div className="mt-1 text-sm">{children}</div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Loading skeleton — matches the detail card layout shape
// ---------------------------------------------------------------------------

function DetailSkeleton() {
  return (
    <>
      <div className="flex items-center gap-3">
        <Skeleton className="h-6 w-40" />
        <Skeleton className="h-5 w-20 rounded-full" />
      </div>
      <Card>
        <CardHeader>
          <Skeleton className="h-5 w-36" />
          <Skeleton className="mt-1 h-4 w-52" />
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          {Array.from({ length: 7 }).map((_, i) => (
            <div key={i} className="space-y-1.5">
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-4 w-36" />
            </div>
          ))}
        </CardContent>
      </Card>
    </>
  );
}
