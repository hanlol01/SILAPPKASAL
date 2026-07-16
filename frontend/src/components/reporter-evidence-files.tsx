import { useMutation, useQuery } from "@tanstack/react-query";
import { Download, FileText, Loader2, Paperclip, RefreshCw } from "lucide-react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { toast } from "sonner";
import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api-client";
import { formatDateTime } from "@/lib/format";
import {
  downloadCaseReporterEvidenceFile,
  getCaseReporterEvidenceFiles,
  operationsQueryKeys,
} from "@/lib/operations-api";
import type { ReporterEvidenceFile } from "@/lib/operations-types";

export function ReporterEvidenceFiles({
  caseId,
  canDownload,
  language,
}: {
  caseId: string | number;
  canDownload: boolean;
  language: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const filesQuery = useQuery({
    queryKey: operationsQueryKeys.reporterEvidenceFiles(caseId),
    queryFn: () => getCaseReporterEvidenceFiles(caseId),
    retry: false,
  });
  const downloadMutation = useMutation({
    mutationFn: (id: string) => downloadCaseReporterEvidenceFile(id),
    onSuccess: () => toast.success(t("dashboard:cases.reporterEvidence.downloadSuccess")),
    onError: (error) => toast.error(downloadError(error, t)),
  });

  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle className="flex min-w-0 items-center gap-2 text-base">
          <Paperclip className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 break-words [overflow-wrap:anywhere]">
            {t("dashboard:cases.reporterEvidence.title")}
          </span>
        </CardTitle>
        <CardDescription>{t("dashboard:cases.reporterEvidence.description")}</CardDescription>
      </CardHeader>
      <CardContent className="min-w-0">
        {filesQuery.isPending ? (
          <div className="space-y-3" aria-busy="true">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-20 w-full" />
          </div>
        ) : filesQuery.isError ? (
          <div className="flex min-w-0 flex-col items-start gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="min-w-0 break-words text-sm text-destructive [overflow-wrap:anywhere] whitespace-pre-wrap">
              {t("dashboard:cases.reporterEvidence.loadError")}
            </p>
            <Button type="button" variant="outline" size="sm" onClick={() => filesQuery.refetch()}>
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
              {t("dashboard:cases.reporterEvidence.retry")}
            </Button>
          </div>
        ) : filesQuery.data.length === 0 ? (
          <EmptyState
            icon={FileText}
            title={t("dashboard:cases.reporterEvidence.emptyTitle")}
            description={t("dashboard:cases.reporterEvidence.emptyDescription")}
          />
        ) : (
          <div className="min-w-0 space-y-3">
            <p className="text-xs text-muted-foreground">
              {t("dashboard:cases.reporterEvidence.count", { count: filesQuery.data.length })}
            </p>
            {filesQuery.data.map((file) => (
              <ReporterFileRow
                key={file.id}
                file={file}
                language={language}
                canDownload={canDownload}
                downloading={downloadMutation.isPending && downloadMutation.variables === file.id}
                onDownload={() => downloadMutation.mutate(file.id)}
                t={t}
              />
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function ReporterFileRow({
  file,
  language,
  canDownload,
  downloading,
  onDownload,
  t,
}: {
  file: ReporterEvidenceFile;
  language: string;
  canDownload: boolean;
  downloading: boolean;
  onDownload: () => void;
  t: TFunction;
}) {
  return (
    <div className="flex min-w-0 flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-start sm:justify-between">
      <div className="flex min-w-0 items-start gap-3">
        <FileText className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <div className="min-w-0 space-y-1">
          <p className="min-w-0 break-words text-sm font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
            {file.original_filename}
          </p>
          <p className="min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere]">
            {formatMimeType(file.mime_type, t)} | {formatByteSize(file.file_size, language)} | {formatDateTime(file.uploaded_at, language)}
          </p>
        </div>
      </div>
      {canDownload && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-full shrink-0 sm:w-auto"
          disabled={downloading}
          onClick={onDownload}
        >
          {downloading ? (
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
          ) : (
            <Download className="h-4 w-4" aria-hidden="true" />
          )}
          {downloading
            ? t("dashboard:cases.reporterEvidence.downloading")
            : t("dashboard:cases.reporterEvidence.download")}
        </Button>
      )}
    </div>
  );
}

function downloadError(error: unknown, t: TFunction) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t("dashboard:cases.reporterEvidence.forbidden");
    if (error.status === 404) return t("dashboard:cases.reporterEvidence.notFound");
  }

  return t("dashboard:cases.reporterEvidence.downloadError");
}

function formatMimeType(mimeType: string, t: TFunction) {
  const key =
    mimeType === "application/pdf"
      ? "pdf"
      : mimeType === "image/jpeg"
        ? "jpeg"
        : mimeType === "image/png"
          ? "png"
          : "other";

  return t(`dashboard:cases.reporterEvidence.mime.${key}`);
}

function formatByteSize(value: number, language: string) {
  if (!Number.isFinite(value) || value < 0) return "-";
  const units = ["B", "KB", "MB"];
  let size = value;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  return `${new Intl.NumberFormat(language, { maximumFractionDigits: 1 }).format(size)} ${units[unitIndex]}`;
}
