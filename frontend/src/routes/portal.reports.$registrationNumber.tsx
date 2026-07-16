import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowLeft,
  CheckCircle2,
  Eye,
  HelpCircle,
  Send,
  ShieldCheck,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { TFunction } from "i18next";
import { Button } from "@/components/ui/button";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
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
import { PortalReportTypeBadge } from "@/components/portal/portal-report-type-badge";
import { ReporterEvidenceSubmissions } from "@/components/portal/reporter-evidence-submissions";
import {
  ProgressTimeline,
  ProgressTimelineSkeleton,
  type ProgressTimelineEvent,
} from "@/components/progress-timeline";
import { portalQueryKeys, getPortalReport, getPortalReportTimeline } from "@/lib/portal-api";
import type { PortalTimelineEvent } from "@/lib/portal-types";
import { formatDate } from "@/lib/format";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { useTranslation } from "react-i18next";

export const Route = createFileRoute("/portal/reports/$registrationNumber")({
  component: PortalReportDetailPage,
  head: () => ({
    meta: [
      { title: "Report Detail — SILAPPKASAL Portal" },
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
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/portal">{t("overview")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/portal/reports">{t("myReports")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>{registrationNumber}</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>
      {/* Back navigation */}
      <Button
        variant="outline"
        size="sm"
        className="gap-1.5"
        asChild
      >
        <Link to="/portal/reports">
          <ArrowLeft className="h-3.5 w-3.5" /> {t("common:back")}
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
      {reportQuery.isSuccess && reportQuery.data && (
        <>
          <ReportDetail report={reportQuery.data} />
          <ReporterEvidenceSubmissions registrationNumber={registrationNumber} />
          <ReportProgressSection
            registrationNumber={registrationNumber}
            enabled={hasPortalAccess(roleCode)}
          />
        </>
      )}
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
  const { t, i18n } = useTranslation(["portal"]);
  // safely extract string category if backend accidentally returns an object
  const categoryLabel =
    typeof report.category === "object" && report.category !== null
      ? (report.category as { name?: string }).name
      : report.category;
  const isCompleted = report.portal_status.toLowerCase() === "completed";

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
        <PortalReportTypeBadge reportType={report.report_type} />
      </div>

      {/* Safe final completion message — shown only for the reporter-safe Completed status */}
      {isCompleted && (
        <Card className="border-success/30 bg-success/10">
          <CardContent className="flex items-start gap-3 p-4">
            <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-success" aria-hidden="true" />
            <div>
              <p className="text-sm font-medium">{t("completionTitle")}</p>
              <p className="mt-1 text-sm text-muted-foreground">{t("completionMessage")}</p>
            </div>
          </CardContent>
        </Card>
      )}

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
            <PortalReportTypeBadge reportType={report.report_type} />
          </Field>
          <Field label={t("category")}>
            {categoryLabel ? categoryLabel : "—"}
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

// ---------------------------------------------------------------------------
// Report progress section — consumes ONLY the reporter-safe timeline payload.
// The frontend never reconstructs progress from internal case data.
// ---------------------------------------------------------------------------

const TIMELINE_STAGE_ICONS: Record<string, LucideIcon> = {
  laporan_dikirim: Send,
  laporan_ditinjau: Eye,
  proses_penanganan: ShieldCheck,
  selesai: CheckCircle2,
};

function ReportProgressSection({
  registrationNumber,
  enabled,
}: {
  registrationNumber: string;
  enabled: boolean;
}) {
  const { t, i18n } = useTranslation(["portal"]);

  const timelineQuery = useQuery({
    queryKey: portalQueryKeys.reportTimeline(registrationNumber),
    queryFn: () => getPortalReportTimeline(registrationNumber),
    enabled,
    retry: false,
  });

  const events = (timelineQuery.data?.events ?? []).map((event) =>
    safeStageEvent(t, i18n.language, event),
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("timeline.title")}</CardTitle>
        <CardDescription>{t("timeline.desc")}</CardDescription>
      </CardHeader>
      <CardContent>
        {timelineQuery.isPending && <ProgressTimelineSkeleton rows={3} />}
        {timelineQuery.isError && (
          <p className="text-sm text-muted-foreground">{t("timeline.error")}</p>
        )}
        {timelineQuery.isSuccess && events.length === 0 && (
          <p className="text-sm text-muted-foreground">{t("timeline.empty")}</p>
        )}
        {timelineQuery.isSuccess && events.length > 0 && <ProgressTimeline events={events} />}
      </CardContent>
    </Card>
  );
}

function safeStageEvent(
  t: TFunction,
  language: string,
  event: PortalTimelineEvent,
): ProgressTimelineEvent {
  const known = Object.prototype.hasOwnProperty.call(TIMELINE_STAGE_ICONS, event.stage);
  const key = known ? event.stage : "unknown";
  const description = t(`timeline.stages.${key}.desc`, { defaultValue: "" });

  return {
    id: `${event.stage}-${event.occurred_at ?? ""}`,
    title: t(`timeline.stages.${key}.title`),
    timestamp: event.occurred_at ? formatDate(event.occurred_at, language) : null,
    description: description || null,
    icon: TIMELINE_STAGE_ICONS[event.stage] ?? HelpCircle,
  };
}
