import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Lock } from "lucide-react";
import { useTranslation } from "react-i18next";
import { AccessDenied } from "@/components/access-denied";
import { BreakGlassRequestDialog } from "@/components/admin/break-glass-request-dialog";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
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
import { SatgasAssignmentAction } from "@/components/workflow-actions/satgas-assignment-action";
import { useAuth } from "@/hooks/use-auth";
import { formatDateTime } from "@/lib/format";
import { formatReportStatus, formatReportType } from "@/lib/format-labels";
import { getReport, operationsQueryKeys } from "@/lib/operations-api";
import type { ReportReporter } from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/reports/$id")({
  component: ReportDetailPage,
  head: () => ({ meta: [{ title: "Report detail - SILAPPKASAL Admin" }] }),
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
    return <div className="py-12 text-center text-sm text-muted-foreground">{t("dashboard:reports.loading")}</div>;
  }

  if (reportQuery.isError || !reportQuery.data) {
    return <QueryErrorState message={t("dashboard:reports.couldNotLoad")} onRetry={() => reportQuery.refetch()} />;
  }

  const report = reportQuery.data;
  const canRequestBreakGlass = Boolean(user?.permissions?.includes("privacy.request_break_glass"));
  const isAnonymousReport = Boolean(report.is_anonymous || report.report_type === "anonymous");

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
          <Badge variant="outline">{formatReportStatus(t, report.status)}</Badge>
          {isAnonymousReport && (
            <Badge variant="outline" className="gap-1 text-muted-foreground">
              <Lock className="h-3 w-3" /> {t("dashboard:reports.anonymous")}
            </Badge>
          )}
        </div>
      </div>

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
            <Field label={t("dashboard:common.priority")}>{report.priority?.name ?? "-"}</Field>
            <Field label={t("dashboard:common.submitted")}>{formatDateTime(report.submitted_at, i18n.language)}</Field>
            <Field label={t("dashboard:common.forwarded")}>{formatDateTime(report.forwarded_at, i18n.language)}</Field>
            <Field label={t("dashboard:common.created")}>{formatDateTime(report.created_at, i18n.language)}</Field>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("dashboard:common.actions")}</CardTitle>
            <CardDescription>{t("dashboard:reports.actionsDesc")}</CardDescription>
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
              {t("dashboard:reports.satgasHint")}
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

function reporterDisplay(reporter: ReportReporter | null | undefined, t: ReturnType<typeof useTranslation>["t"]) {
  if (!reporter) {
    return <span className="text-muted-foreground">{t("dashboard:common.metadataUnavailable")}</span>;
  }

  if ("masked" in reporter && reporter.masked === true) {
    return <span className="text-muted-foreground">{t("dashboard:reports.identityHidden")}</span>;
  }

  return "name" in reporter ? reporter.name : t("dashboard:common.metadataUnavailable");
}
