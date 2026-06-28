import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Lock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
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
import { formatDate } from "@/lib/format";
import { portalReportTypeLabel } from "@/lib/portal-labels";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { useTranslation } from "react-i18next";

export const Route = createFileRoute("/portal/reports/$registrationNumber")({
  component: PortalReportDetailPage,
  head: () => ({
    meta: [
      { title: "Report Detail â€” SILAPPKASAL Portal" },
      {
        name: "description",
        content: "View the current status and details of your report.",
      },
    ],
  }),
});

function PortalReportDetailPage() {
  const { registrationNumber } = Route.useParams();

  return <PortalReportDetailContent registrationNumber={registrationNumber} />;
}

export function PortalReportDetailContent({
  registrationNumber,
}: {
  registrationNumber: string;
}) {
  const { t } = useTranslation(["portal", "common"]);
  const { roleCode } = useAuth();

  const reportQuery = useQuery({
    queryKey: portalQueryKeys.report(registrationNumber),
    queryFn: () => getPortalReport(registrationNumber),
    enabled: hasPortalAccess(roleCode),
    retry: false,
  });
  const reportMissing = reportQuery.isSuccess && !reportQuery.data;

  return (
    <div className="space-y-6">
      {/* Back navigation */}
      <Button
        variant="ghost"
        size="sm"
        asChild
      >
        <Link to="/portal/reports">
          <ArrowLeft className="mr-2 h-4 w-4" /> {t("common:back")}
        </Link>
      </Button>

      {/* Loading */}
      {reportQuery.isPending && <DetailSkeleton />}

      {/* Error */}
      {(reportQuery.isError || reportMissing) && (
        <QueryErrorState
          message={t("reportNotFoundOrNoAccess")}
          onRetry={() => reportQuery.refetch()}
        />
      )}

      {/* Success */}
      {reportQuery.isSuccess && reportQuery.data && <ReportDetail report={reportQuery.data} />}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Report detail content â€” renders only PortalReportDetail safe fields
// ---------------------------------------------------------------------------

interface ReportDetailProps {
  report: import("@/lib/portal-types").PortalReportDetail;
}

function ReportDetail({ report }: ReportDetailProps) {
  const { t, i18n } = useTranslation(["portal"]);
  // safely extract string category if backend accidentally returns an object
  const categoryLabel =
    typeof report.category === "object" && report.category !== null
      ? (report.category as { name?: string }).name
      : report.category;

  return (
    <>
      {/* Header */}
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="font-mono text-lg font-semibold">
          {report.registration_number}
        </h1>
        <PortalStatusBadge
          portalStatus={report.portal_status}
        />
        {report.report_type === "anonymous" && (
          <Badge variant="outline" className="gap-1 text-muted-foreground">
            <Lock className="h-3 w-3" />
            {t("anonymousReport")}
          </Badge>
        )}
      </div>

      {/* Detail card */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("reportInformation")}</CardTitle>
          <CardDescription>
            {t("reportDetailSubtitle")}
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
          <Field label={t("registrationNumber")}>
            {report.registration_number}
          </Field>
          <Field label={t("reportType")}>
            {portalReportTypeLabel(report.report_type, i18n.language)}
          </Field>
          <Field label={t("category")}>
            {categoryLabel ? categoryLabel : "â€”"}
          </Field>
          <Field label={t("status")}>
            <PortalStatusBadge
              portalStatus={report.portal_status}
            />
          </Field>
          <Field label={t("submitted")}>{formatDate(report.submitted_at, i18n.language)}</Field>
        </CardContent>
      </Card>
    </>
  );
}

// ---------------------------------------------------------------------------
// Field component â€” matches admin detail page pattern
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
// Loading skeleton â€” matches the detail card layout shape
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
          {Array.from({ length: 5 }).map((_, i) => (
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
