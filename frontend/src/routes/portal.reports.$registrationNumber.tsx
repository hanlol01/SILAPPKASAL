import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import {
  ArrowLeft,
  CheckCircle2,
  Download,
  Eye,
  FileCheck2,
  Gavel,
  HelpCircle,
  HeartHandshake,
  Paperclip,
  OctagonX,
  SearchCheck,
  Send,
  ShieldCheck,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { TFunction } from "i18next";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import { ReportInputDetailsContent } from "@/components/report-input-details";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
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
import { CancelComplaintDialog } from "@/components/portal/cancel-complaint-dialog";
import { FormalWithdrawalWizard } from "@/components/portal/formal-withdrawal-wizard";
import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import {
  ProgressTimeline,
  ProgressTimelineSkeleton,
  type ProgressTimelineEvent,
} from "@/components/progress-timeline";
import {
  portalQueryKeys,
  getPortalReport,
  getPortalReportHandlingProgress,
  getPortalReportTimeline,
  downloadPortalCaseClosureDocument,
  previewPortalCaseClosureDocument,
} from "@/lib/portal-api";
import type { PortalHandlingState, PortalTimelineEvent } from "@/lib/portal-types";
import { formatDate, formatDateTime } from "@/lib/format";
import { formatReportCategory } from "@/lib/format-labels";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

export const Route = createFileRoute("/portal/reports/$registrationNumber")({
  component: PortalReportDetailPage,
  head: () => ({
    meta: [
      { title: "Complaint Detail — SILAPPKASAL Portal" },
      {
        name: "description",
        content: "View the current status and details of your complaint.",
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
          <HandlingProgressSection
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
  const { t, i18n } = useTranslation(["portal", "dashboard"]);
  const isCompleted = report.portal_status.toLowerCase() === "completed";
  const collapseLabels = {
    expandLabel: t("portal:collapsible.expand"),
    collapseLabel: t("portal:collapsible.collapse"),
  };

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
        {report.withdrawal_capabilities.can_cancel ? (
          <CancelComplaintDialog registrationNumber={report.registration_number} />
        ) : report.portal_status !== "withdrawn" ? (
          (report.withdrawal_capabilities.can_request_withdrawal ||
            report.withdrawal_capabilities.active_withdrawal ||
            report.withdrawal_capabilities.latest_withdrawal) && (
            <FormalWithdrawalWizard
              registrationNumber={report.registration_number}
              canRequestWithdrawal={report.withdrawal_capabilities.can_request_withdrawal}
              activeWithdrawal={
                report.withdrawal_capabilities.latest_withdrawal ??
                report.withdrawal_capabilities.active_withdrawal
              }
            />
          )
        ) : null}
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

      <CollapsibleDataCard
        title={t("portal:reportInformation")}
        description={t("portal:reportDetailSubtitle")}
        contentClassName="grid gap-4 text-sm sm:grid-cols-2"
        {...collapseLabels}
      >
          <Field label={t("portal:registrationNumber")}>
            {report.registration_number}
          </Field>
          <Field label={t("portal:reportType")}>
            <PortalReportTypeBadge reportType={report.report_type} />
          </Field>
          <Field label={t("portal:category")}>
            {report.category
              ? formatReportCategory(t, report.category)
              : t("portal:submittedDetails.empty")}
          </Field>
          <Field label={t("portal:status")}>
            <PortalStatusBadge
              portalStatus={report.portal_status}
            />
          </Field>
          <Field label={t("portal:submitted")}>
            {formatDate(report.submitted_at, i18n.language)}
          </Field>
      </CollapsibleDataCard>

      <CollapsibleDataCard
        title={t("portal:submittedDetails.title")}
        description={t("portal:submittedDetails.description")}
        {...collapseLabels}
      >
        <ReportInputDetailsContent details={report.submitted_details} translationScope="portal" />
      </CollapsibleDataCard>
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
  pengaduan_dibatalkan: OctagonX,
  pengaduan_dicabut: OctagonX,
  permohonan_pencabutan_dibuat: Send,
  dokumen_pencabutan_disiapkan: FileCheck2,
  surat_pencabutan_diunggah: FileCheck2,
  pencabutan_dikirim_untuk_verifikasi: ShieldCheck,
  permohonan_pencabutan_dibatalkan: OctagonX,
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
    <CollapsibleDataCard
      title={t("timeline.title")}
      description={t("timeline.desc")}
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
    >
        {timelineQuery.isPending && <ProgressTimelineSkeleton rows={3} />}
        {timelineQuery.isError && (
          <p className="text-sm text-muted-foreground">{t("timeline.error")}</p>
        )}
        {timelineQuery.isSuccess && events.length === 0 && (
          <p className="text-sm text-muted-foreground">{t("timeline.empty")}</p>
        )}
        {timelineQuery.isSuccess && events.length > 0 && <ProgressTimeline events={events} />}
    </CollapsibleDataCard>
  );
}

function PortalFinalSummaryCard({
  summary,
  language,
  t,
}: {
  summary: NonNullable<import("@/lib/portal-types").PortalReportHandlingProgress["final_summary"]>;
  language: string;
  t: TFunction;
}) {
  return (
    <CollapsibleDataCard
      icon={CheckCircle2}
      title={t("finalSummary.title")}
      description={t("finalSummary.description")}
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
    >
      {summary.state === "legacy_completion" ? (
        <p className="text-sm text-muted-foreground">{t("finalSummary.legacyCompletion")}</p>
      ) : (
        <div className="grid min-w-0 gap-4 sm:grid-cols-2">
          <ProgressFact label={t("finalSummary.fields.outcome")} value={summary.outcome_label} />
          <ProgressFact label={t("finalSummary.fields.completionDate")} value={formatDate(summary.completion_date, language)} />
          <FinalNarrative label={t("finalSummary.fields.officialStatement")} value={summary.official_statement} />
          <FinalNarrative label={t("finalSummary.fields.investigationSummary")} value={summary.investigation_summary} />
          <FinalNarrative label={t("finalSummary.fields.recommendationResult")} value={summary.recommendation_result} />
          <FinalNarrative label={t("finalSummary.fields.decisionResult")} value={summary.decision_result} />
          <FinalNarrative label={t("finalSummary.fields.recoveryResult")} value={summary.recovery_result} />
          <FinalNarrative label={t("finalSummary.fields.actionsCompleted")} value={summary.actions_completed} />
          <FinalNarrative label={t("finalSummary.fields.actionsUncompleted")} value={summary.actions_uncompleted} />
          <FinalNarrative label={t("finalSummary.fields.followUpOrReferral")} value={summary.follow_up_or_referral} />
          <FinalNarrative label={t("finalSummary.fields.closingExplanation")} value={summary.closing_explanation} />
        </div>
      )}
    </CollapsibleDataCard>
  );
}

function FinalNarrative({ label, value }: { label: string; value?: string | null }) {
  if (!value) return null;
  return (
    <div className="min-w-0 sm:col-span-2">
      <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 whitespace-pre-wrap break-words text-sm [overflow-wrap:anywhere]">{value}</div>
    </div>
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

function HandlingProgressSection({
  registrationNumber,
  enabled,
}: {
  registrationNumber: string;
  enabled: boolean;
}) {
  const { t, i18n } = useTranslation(["portal"]);
  const progressQuery = useQuery({
    queryKey: portalQueryKeys.reportHandlingProgress(registrationNumber),
    queryFn: () => getPortalReportHandlingProgress(registrationNumber),
    enabled,
    retry: false,
  });
  const progress = progressQuery.data;
  const empty = t("handlingProgress.empty");

  return (
    <div className="space-y-4">
    <CollapsibleDataCard
      icon={ShieldCheck}
      title={t("handlingProgress.title")}
      description={t("handlingProgress.description")}
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
    >
      {progressQuery.isPending && <ProgressTimelineSkeleton rows={5} />}
      {progressQuery.isError && (
        <div className="space-y-3">
          <p className="text-sm text-muted-foreground">{t("handlingProgress.error")}</p>
          <Button type="button" variant="outline" size="sm" onClick={() => progressQuery.refetch()}>
            {t("handlingProgress.retry")}
          </Button>
        </div>
      )}
      {progress && (
        <div className="min-w-0 space-y-4">
          <div className="flex min-w-0 flex-wrap items-center gap-2 text-sm">
            <span className="text-muted-foreground">{t("handlingProgress.caseState")}</span>
            <ProgressStateBadge state={progress.case.state} />
          </div>
          <Accordion type="multiple" defaultValue={["investigation"]} className="min-w-0">
            <ProgressAccordionItem
              value="investigation"
              icon={SearchCheck}
              title={t("handlingProgress.sections.investigation")}
              state={progress.investigation.state}
            >
              <ProgressFact label={t("handlingProgress.fields.startedAt")} value={formatSafeDateTime(progress.investigation.started_at, i18n.language, empty)} />
              <ProgressFact label={t("handlingProgress.fields.completedAt")} value={formatSafeDateTime(progress.investigation.completed_at, i18n.language, empty)} />
              <ProgressFact label={t("handlingProgress.fields.activityCount")} value={String(progress.investigation.activity_count)} />
            </ProgressAccordionItem>
            <ProgressAccordionItem
              value="recommendation"
              icon={FileCheck2}
              title={t("handlingProgress.sections.recommendation")}
              state={progress.recommendation.state}
            >
              <ProgressFact label={t("handlingProgress.fields.submittedAt")} value={formatSafeDateTime(progress.recommendation.submitted_at, i18n.language, empty)} />
              <ProgressFact label={t("handlingProgress.fields.reviewedAt")} value={formatSafeDateTime(progress.recommendation.reviewed_at, i18n.language, empty)} />
              {progress.recommendation.approved_at && (
                <ProgressFact label={t("handlingProgress.fields.approvedAt")} value={formatDateTime(progress.recommendation.approved_at, i18n.language)} />
              )}
            </ProgressAccordionItem>
            <ProgressAccordionItem
              value="decision"
              icon={Gavel}
              title={t("handlingProgress.sections.decision")}
              state={progress.decision.state}
            >
              <ProgressFact label={t("handlingProgress.fields.decisionDate")} value={progress.decision.decision_date ? formatDate(progress.decision.decision_date, i18n.language) : empty} />
              <ProgressFact label={t("handlingProgress.fields.finalizedAt")} value={formatSafeDateTime(progress.decision.finalized_at, i18n.language, empty)} />
            </ProgressAccordionItem>
            <ProgressAccordionItem
              value="recovery"
              icon={HeartHandshake}
              title={t("handlingProgress.sections.recovery")}
              state={progress.recovery.state}
            >
              <ProgressFact label={t("handlingProgress.fields.startedAt")} value={formatSafeDateTime(progress.recovery.started_at, i18n.language, empty)} />
              <ProgressFact label={t("handlingProgress.fields.completedAt")} value={formatSafeDateTime(progress.recovery.completed_at, i18n.language, empty)} />
              {progress.recovery.discontinued_at && (
                <ProgressFact label={t("handlingProgress.fields.discontinuedAt")} value={formatDateTime(progress.recovery.discontinued_at, i18n.language)} />
              )}
              <ProgressFact label={t("handlingProgress.fields.monitoringCount")} value={String(progress.monitoring.count)} />
              <ProgressFact label={t("handlingProgress.fields.latestMonitoring")} value={progress.monitoring.latest_at ? formatDate(progress.monitoring.latest_at, i18n.language) : empty} />
            </ProgressAccordionItem>
            <ProgressAccordionItem
              value="evidence"
              icon={Paperclip}
              title={t("handlingProgress.sections.evidence")}
              state={progress.case.available ? progress.case.state : "unavailable"}
            >
              <ProgressFact label={t("handlingProgress.fields.supportingFileCount")} value={String(progress.evidence.reporter_supporting_file_count)} />
              <ProgressFact label={t("handlingProgress.fields.internalEvidenceCount")} value={String(progress.evidence.internal_evidence_count)} />
              <p className="col-span-full text-xs text-muted-foreground">
                {t("handlingProgress.evidencePrivacy")}
              </p>
            </ProgressAccordionItem>
          </Accordion>
        </div>
      )}
    </CollapsibleDataCard>
    {progress?.final_summary && (
      <PortalFinalSummaryCard summary={progress.final_summary} language={i18n.language} t={t} />
    )}
    {progress?.closure_document?.available && (
      <PortalClosureDocumentCard
        registrationNumber={progress.registration_number}
        documentNumber={progress.closure_document.document_number}
        issuedAt={progress.closure_document.issued_at}
        language={i18n.language}
        t={t}
      />
    )}
    </div>
  );
}

function PortalClosureDocumentCard({
  registrationNumber,
  documentNumber,
  issuedAt,
  language,
  t,
}: {
  registrationNumber: string;
  documentNumber?: string | null;
  issuedAt?: string | null;
  language: string;
  t: TFunction;
}) {
  const downloadMutation = useMutation({
    mutationFn: () => downloadPortalCaseClosureDocument(registrationNumber),
    onError: () => toast.error(t("closureDocument.downloadError")),
  });

  return (
    <CollapsibleDataCard
      icon={FileCheck2}
      title={t("closureDocument.title")}
      description={t("closureDocument.description")}
      expandLabel={t("collapsible.expand")}
      collapseLabel={t("collapsible.collapse")}
    >
      <div className="flex min-w-0 flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          {documentNumber && <p className="break-words text-sm font-medium [overflow-wrap:anywhere]">{documentNumber}</p>}
          {issuedAt && <p className="mt-1 text-xs text-muted-foreground">{t("closureDocument.issuedAt", { date: formatDateTime(issuedAt, language) })}</p>}
        </div>
        <div className="flex shrink-0 flex-wrap gap-2">
          <SecureFilePreviewDialog
            fileKey={`closure-${registrationNumber}`}
            expectedMimeType="application/pdf"
            loadPreview={(signal) => previewPortalCaseClosureDocument(registrationNumber, signal)}
            onDownload={() => downloadMutation.mutate()}
            downloadPending={downloadMutation.isPending}
            labels={{
              preview: t("closureDocument.preview"), title: t("closureDocument.title"), description: t("closureDocument.description"),
              loading: t("common.loading"), error: t("closureDocument.downloadError"), retry: t("common.retry"), close: t("common.close"),
              download: t("closureDocument.download"), downloading: t("common.loading"), imageAlt: t("closureDocument.title"),
              pdfTitle: t("closureDocument.title"), pdfFallback: t("closureDocument.pdfFallback"), zoomIn: t("closureDocument.zoomIn"), zoomOut: t("closureDocument.zoomOut"), resetZoom: t("closureDocument.reset"), fit: t("closureDocument.fit"), controls: t("closureDocument.controls"),
            }}
          />
          <Button variant="outline" onClick={() => downloadMutation.mutate()} disabled={downloadMutation.isPending}><Download className="h-4 w-4" />{t("closureDocument.download")}</Button>
        </div>
      </div>
    </CollapsibleDataCard>
  );
}

function ProgressAccordionItem({
  value,
  icon: Icon,
  title,
  state,
  children,
}: {
  value: string;
  icon: LucideIcon;
  title: string;
  state: PortalHandlingState | "not_started" | "ongoing" | "completed";
  children: React.ReactNode;
}) {
  return (
    <AccordionItem value={value} className="min-w-0 last:border-b-0">
      <AccordionTrigger className="min-w-0 gap-3 hover:no-underline">
        <span className="flex min-w-0 flex-1 items-center gap-2">
          <Icon className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
          <span className="min-w-0 break-words text-left [overflow-wrap:anywhere]">{title}</span>
          <ProgressStateBadge state={state} />
        </span>
      </AccordionTrigger>
      <AccordionContent>
        <div className="grid min-w-0 gap-3 rounded-md bg-muted/30 p-3 sm:grid-cols-2">
          {children}
        </div>
      </AccordionContent>
    </AccordionItem>
  );
}

function ProgressStateBadge({
  state,
}: {
  state: PortalHandlingState | "not_started" | "ongoing" | "completed";
}) {
  const { t } = useTranslation(["portal"]);

  return (
    <Badge variant="outline" className="shrink-0 whitespace-nowrap">
      {t(`handlingProgress.states.${state}`)}
    </Badge>
  );
}

function ProgressFact({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 break-words [overflow-wrap:anywhere]">{value}</div>
    </div>
  );
}

function formatSafeDateTime(value: string | null, language: string, empty: string) {
  return value ? formatDateTime(value, language) : empty;
}
