import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, CheckCircle2, Lock } from "lucide-react";
import { useTranslation } from "react-i18next";
import { AccessDenied } from "@/components/access-denied";
import { QueryErrorState } from "@/components/query-state";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import { ReportInputDetailsContent } from "@/components/report-input-details";
import { ReporterEvidenceFiles } from "@/components/reporter-evidence-files";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { ReportStatusBadge } from "@/components/status-badge";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { SatgasAssignmentAction } from "@/components/workflow-actions/satgas-assignment-action";
import { useAuth } from "@/hooks/use-auth";
import { formatDateTime } from "@/lib/format";
import { formatPriorityLevel, formatReportType } from "@/lib/format-labels";
import { getReport, operationsQueryKeys } from "@/lib/operations-api";
import type { ReportReporter } from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/reports/$id")({
  component: ReportDetailPage,
  head: () => ({ meta: [{ title: "Complaint detail - SILAPPKASAL Admin" }] }),
});

function ReportDetailPage() {
  const { id } = Route.useParams();
  const { roleCode, user } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const reportQuery = useQuery({
    queryKey: operationsQueryKeys.report(id),
    queryFn: () => getReport(id),
    enabled: roleCode === "super_admin" || roleCode === "admin",
  });

  if (roleCode !== "super_admin" && roleCode !== "admin") {
    return <AccessDenied />;
  }

  if (reportQuery.isLoading) {
    return <ReportDetailSkeleton />;
  }

  if (reportQuery.isError || !reportQuery.data) {
    return <QueryErrorState message={t("dashboard:reports.couldNotLoad")} onRetry={() => reportQuery.refetch()} />;
  }

  const report = reportQuery.data;
  const isAnonymousReport = Boolean(report.is_anonymous || report.report_type === "anonymous");
  const reportCase = report.case ?? null;
  const activeAssignments = reportCase?.active_assignments ?? [];
  const assignmentMode = reportCase ? "assign-case" : "forward-report";
  const canManageAssignment = reportCase
    ? roleCode === "admin" && Boolean(user?.permissions?.includes("cases.assign_satgas"))
    : roleCode === "admin" && Boolean(user?.permissions?.includes("reports.forward"));
  const sensitiveOversightEnabled =
    roleCode === "super_admin" && report.submitted_details !== undefined;
  const canViewSubmittedDetails = report.submitted_details !== undefined;

  return (
    <div className="space-y-6">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/dashboard">{t("dashboard:nav.overview")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/dashboard/reports">{t("dashboard:reports.title")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>{report.registration_number}</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/dashboard/reports">
            <ArrowLeft className="mr-2 h-4 w-4" /> {t("dashboard:reports.allReports")}
          </Link>
        </Button>
        <div className="flex items-center gap-2">
          <h1 className="font-mono text-lg font-semibold">{report.registration_number}</h1>
          <ReportStatusBadge status={report.status} />
          {isAnonymousReport && (
            <Badge variant="outline" className="gap-1 text-muted-foreground">
              <Lock className="h-3 w-3" /> {t("dashboard:reports.anonymous")}
            </Badge>
          )}
        </div>
      </div>

      {roleCode === "super_admin" && (
        <Alert>
          <Lock className="h-4 w-4" />
          <AlertTitle>{t("dashboard:workflow.oversightReadOnly")}</AlertTitle>
          {!sensitiveOversightEnabled && (
            <AlertDescription>
              {t("dashboard:workflow.sensitiveOversightUnavailable")}
            </AlertDescription>
          )}
        </Alert>
      )}

      {report.status === "forwarded" && (
        <Alert className="border-info/30 bg-info/10 [&>svg]:text-info">
          <CheckCircle2 className="h-4 w-4" />
          <AlertTitle>{t("dashboard:reports.forwardedNoticeTitle")}</AlertTitle>
          <AlertDescription>{t("dashboard:reports.forwardedNotice")}</AlertDescription>
        </Alert>
      )}

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">{t("dashboard:reports.detailTitle")}</CardTitle>
            <CardDescription>{t("dashboard:reports.detailDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
            <Field label={t("dashboard:reports.registration")}>{report.registration_number}</Field>
            <Field label={t("dashboard:common.type")}>{formatReportType(t, report.report_type)}</Field>
            <Field label={t("dashboard:reports.reporter")}>{reporterDisplay(report.reporter, t)}</Field>
            <Field label={t("dashboard:common.category")}>{report.category?.name ?? t("dashboard:common.metadataUnavailable")}</Field>
            <Field label={t("dashboard:common.priority")}>
              {reportPriorityLabel(t, report.priority)}
            </Field>
            <Field label={t("dashboard:common.submitted")}>{formatDateTime(report.submitted_at, i18n.language)}</Field>
            <Field label={t("dashboard:common.forwarded")}>{formatDateTime(report.forwarded_at, i18n.language)}</Field>
            <Field label={t("dashboard:common.created")}>{formatDateTime(report.created_at, i18n.language)}</Field>
          </CardContent>
        </Card>

        {canViewSubmittedDetails && report.submitted_details && (
          <CollapsibleDataCard
            className="lg:col-span-2"
            title={t("dashboard:reportInputDetails.title")}
            description={t("dashboard:reportInputDetails.description")}
          >
            <ReportInputDetailsContent
              details={report.submitted_details}
              translationScope="dashboard"
            />
          </CollapsibleDataCard>
        )}

        <div className="min-w-0 space-y-4 lg:col-start-3 lg:row-start-1">
          <Card className="min-w-0">
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:cases.assignments")}</CardTitle>
              <CardDescription>{t("dashboard:cases.assignmentsDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="min-w-0 space-y-3">
              {reportCase ? (
                <>
                  <p className="min-w-0 break-words text-sm font-medium [overflow-wrap:anywhere]">
                    {t("dashboard:reports.assignmentCaseNumber", { number: reportCase.case_number })}
                  </p>
                  {activeAssignments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t("dashboard:cases.noAssignments")}</p>
                  ) : (
                    activeAssignments.map((assignment) => (
                      <div key={assignment.satgas_id} className="min-w-0 rounded-md border p-3 text-sm">
                        <p className="min-w-0 break-words font-medium [overflow-wrap:anywhere]">
                          {assignment.satgas_name ?? t("dashboard:common.metadataUnavailable")}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                          {assignment.is_lead
                            ? t("dashboard:cases.leadSatgas")
                            : t("dashboard:cases.assignedSatgas")}
                        </p>
                      </div>
                    ))
                  )}
                </>
              ) : (
                <p className="text-sm text-muted-foreground">
                  {t("dashboard:reports.assignmentPendingCase")}
                </p>
              )}

              {canManageAssignment && (
                <>
                  <SatgasAssignmentAction
                    mode={assignmentMode}
                    targetId={reportCase?.id ?? report.id}
                    reportId={report.id}
                    currentSatgasIds={activeAssignments.map((assignment) => assignment.satgas_id)}
                    currentLeadSatgasId={
                      activeAssignments.find((assignment) => assignment.is_lead)?.satgas_id ?? null
                    }
                  />
                  <p className="text-xs text-muted-foreground">{t("dashboard:reports.satgasHint")}</p>
                </>
              )}
            </CardContent>
          </Card>

        </div>
      </div>
      {sensitiveOversightEnabled && (
        <ReporterEvidenceFiles reportId={report.id} canDownload language={i18n.language} />
      )}
    </div>
  );
}

function ReportDetailSkeleton() {
  return (
    <div className="space-y-6" aria-busy="true" aria-live="polite">
      <Skeleton className="h-4 w-56" />
      <div className="flex flex-wrap items-center gap-3">
        <Skeleton className="h-8 w-28" />
        <Skeleton className="h-7 w-48" />
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-4 rounded-lg border p-5 lg:col-span-2">
          <Skeleton className="h-5 w-40" />
          <Skeleton className="h-4 w-64" />
          <div className="grid gap-4 sm:grid-cols-2">
            {Array.from({ length: 8 }).map((_, index) => (
              <div key={index} className="space-y-2">
                <Skeleton className="h-3 w-24" />
                <Skeleton className="h-4 w-40" />
              </div>
            ))}
          </div>
        </div>
        <div className="space-y-3 rounded-lg border p-5">
          <Skeleton className="h-5 w-28" />
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-9 w-full" />
        </div>
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

function reporterDisplay(reporter: ReportReporter | null | undefined, t: ReturnType<typeof useTranslation>["t"]) {
  if (!reporter) {
    return <span className="text-muted-foreground">{t("dashboard:common.metadataUnavailable")}</span>;
  }

  if ("masked" in reporter && reporter.masked === true) {
    return <span className="text-muted-foreground">{t("dashboard:reports.identityHidden")}</span>;
  }

  return "name" in reporter ? reporter.name : t("dashboard:common.metadataUnavailable");
}

function reportPriorityLabel(
  t: ReturnType<typeof useTranslation>["t"],
  priority: import("@/lib/operations-types").ReportPriorityProjection,
) {
  if (priority.availability === "assessed" && priority.level) {
    return formatPriorityLevel(t, priority.level);
  }

  return t(`dashboard:reports.priorityAvailability.${priority.availability}`);
}
