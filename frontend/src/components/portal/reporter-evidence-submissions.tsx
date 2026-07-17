import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, FileText, Loader2, Paperclip, RefreshCw, Upload } from "lucide-react";
import { useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { toast } from "sonner";
import { EmptyState } from "@/components/empty-state";
import { CompactFileUpload } from "@/components/compact-file-upload";
import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api-client";
import { isPreviewableMimeType } from "@/lib/file-preview";
import { formatDateTime } from "@/lib/format";
import {
  downloadPortalReportEvidenceFile,
  getPortalReportEvidenceFiles,
  portalQueryKeys,
  previewPortalReportEvidenceFile,
  uploadPortalReportEvidenceFile,
} from "@/lib/portal-api";
import type { PortalEvidenceFile } from "@/lib/portal-types";

const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ALLOWED_EXTENSIONS = new Set(["pdf", "jpg", "jpeg", "png"]);
const ALLOWED_MIME_TYPES = new Set(["application/pdf", "image/jpeg", "image/png"]);

export function ReporterEvidenceSubmissions({ registrationNumber }: { registrationNumber: string }) {
  const { t, i18n } = useTranslation(["portal"]);
  const queryClient = useQueryClient();
  const inputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [clientError, setClientError] = useState<string | null>(null);
  const filesQuery = useQuery({
    queryKey: portalQueryKeys.reportEvidenceFiles(registrationNumber),
    queryFn: () => getPortalReportEvidenceFiles(registrationNumber),
    retry: false,
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadPortalReportEvidenceFile(registrationNumber, file),
    onSuccess: async () => {
      clearSelection();
      await queryClient.invalidateQueries({
        queryKey: portalQueryKeys.reportEvidenceFiles(registrationNumber),
      });
      toast.success(t("evidenceFiles.uploadSuccess"));
    },
    onError: (error) => toast.error(fileActionError(error, "upload", t)),
  });

  const downloadMutation = useMutation({
    mutationFn: (id: string) => downloadPortalReportEvidenceFile(id),
    onSuccess: () => toast.success(t("evidenceFiles.downloadSuccess")),
    onError: (error) => toast.error(fileActionError(error, "download", t)),
  });

  const uploadAllowed = filesQuery.data?.meta.upload_allowed === true;
  const remainingSlots = filesQuery.data?.meta.remaining_slots ?? 0;

  function handleFileChange(file: File | null) {
    if (!file) {
      setSelectedFile(null);
      setClientError(null);
      return;
    }

    const validationKey = validateFile(file);
    if (validationKey) {
      setSelectedFile(null);
      setClientError(t(`evidenceFiles.${validationKey}`));
      if (inputRef.current) inputRef.current.value = "";
      return;
    }

    setSelectedFile(file);
    setClientError(null);
  }

  function clearSelection() {
    setSelectedFile(null);
    setClientError(null);
    if (inputRef.current) inputRef.current.value = "";
  }

  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle className="flex min-w-0 items-center gap-2 text-base">
          <Paperclip className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 break-words [overflow-wrap:anywhere]">
            {t("evidenceFiles.title")}
          </span>
        </CardTitle>
        <CardDescription>{t("evidenceFiles.description")}</CardDescription>
      </CardHeader>
      <CardContent className="min-w-0 space-y-5">
        {filesQuery.isPending ? (
          <EvidenceFilesSkeleton />
        ) : filesQuery.isError ? (
          <InlineError
            message={t("evidenceFiles.loadError")}
            retryLabel={t("evidenceFiles.retry")}
            onRetry={() => filesQuery.refetch()}
          />
        ) : (
          <>
            <div className="min-w-0 space-y-3 border-b pb-5">
              <div className="min-w-0 space-y-1 text-sm text-muted-foreground">
                <p className="break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
                  {t("evidenceFiles.optionalHelp")}
                </p>
              </div>

              {uploadAllowed ? (
                <div className="min-w-0 space-y-3">
                  <CompactFileUpload
                    inputId={`report-evidence-${registrationNumber}`}
                    inputRef={inputRef}
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    title={t("evidenceFiles.chooseFile")}
                    instructions={t("evidenceFiles.formatsHelp")}
                    chooseLabel={t("evidenceFiles.chooseFile")}
                    changeLabel={t("evidenceFiles.changeFile")}
                    removeLabel={t("evidenceFiles.removeFile")}
                    selectedLabel={t("evidenceFiles.selectedFileLabel")}
                    selectedFile={selectedFile}
                    selectedDetails={selectedFile
                      ? `${formatMimeType(selectedFile.type, t)} | ${formatByteSize(selectedFile.size, i18n.language)}`
                      : undefined}
                    error={clientError}
                    disabled={uploadMutation.isPending}
                    onFileChange={handleFileChange}
                    onRemove={clearSelection}
                  />
                  <div className="flex min-w-0 flex-wrap items-center gap-3">
                    <Button
                      type="button"
                      size="sm"
                      disabled={!selectedFile || uploadMutation.isPending}
                      onClick={() => selectedFile && uploadMutation.mutate(selectedFile)}
                    >
                      {uploadMutation.isPending ? (
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                      ) : (
                        <Upload className="h-4 w-4" aria-hidden="true" />
                      )}
                      {uploadMutation.isPending
                        ? t("evidenceFiles.uploading")
                        : t("evidenceFiles.upload")}
                    </Button>
                    <span className="text-xs text-muted-foreground">
                      {t("evidenceFiles.remainingSlots", { count: remainingSlots })}
                    </span>
                  </div>
                </div>
              ) : (
                <p className="min-w-0 break-words text-sm text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
                  {remainingSlots === 0
                    ? t("evidenceFiles.limitReached")
                    : t("evidenceFiles.uploadUnavailable")}
                </p>
              )}
            </div>

            <EvidenceFileList
              files={filesQuery.data.data}
              language={i18n.language}
              downloadingId={downloadMutation.isPending ? downloadMutation.variables : undefined}
              onDownload={(id) => downloadMutation.mutate(id)}
              t={t}
            />
          </>
        )}
      </CardContent>
    </Card>
  );
}

function EvidenceFileList({
  files,
  language,
  downloadingId,
  onDownload,
  t,
}: {
  files: PortalEvidenceFile[];
  language: string;
  downloadingId?: string;
  onDownload: (id: string) => void;
  t: TFunction;
}) {
  if (files.length === 0) {
    return (
      <EmptyState
        icon={FileText}
        title={t("evidenceFiles.emptyTitle")}
        description={t("evidenceFiles.emptyDescription")}
      />
    );
  }

  return (
    <div className="min-w-0 space-y-3">
      <h3 className="text-sm font-medium">{t("evidenceFiles.listTitle", { count: files.length })}</h3>
      {files.map((file) => (
        <div
          key={file.id}
          className="flex min-w-0 flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-start sm:justify-between"
        >
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
          <div className="flex min-w-0 flex-wrap gap-2 sm:justify-end">
            {isPreviewableMimeType(file.mime_type) && (
              <SecureFilePreviewDialog
                fileKey={`${file.id}:${file.mime_type}:${file.original_filename}`}
                expectedMimeType={file.mime_type}
                loadPreview={(signal) => previewPortalReportEvidenceFile(file.id, signal)}
                onDownload={() => onDownload(file.id)}
                downloadPending={downloadingId === file.id}
                labels={{
                  preview: t("evidenceFiles.preview"),
                  title: t("evidenceFiles.previewTitle", { name: file.original_filename }),
                  description: t("evidenceFiles.previewDescription"),
                  loading: t("evidenceFiles.previewLoading"),
                  error: t("evidenceFiles.previewError"),
                  retry: t("evidenceFiles.previewRetry"),
                  close: t("evidenceFiles.previewClose"),
                  download: t("evidenceFiles.download"),
                  downloading: t("evidenceFiles.downloading"),
                  imageAlt: t("evidenceFiles.previewImageAlt", { name: file.original_filename }),
                  pdfTitle: t("evidenceFiles.previewPdfTitle", { name: file.original_filename }),
                  pdfFallback: t("evidenceFiles.previewPdfFallback"),
                  zoomIn: t("evidenceFiles.previewZoomIn"),
                  zoomOut: t("evidenceFiles.previewZoomOut"),
                  resetZoom: t("evidenceFiles.previewResetZoom"),
                  fit: t("evidenceFiles.previewFit"),
                  controls: t("evidenceFiles.previewControls"),
                }}
              />
            )}
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={downloadingId === file.id}
              onClick={() => onDownload(file.id)}
            >
              {downloadingId === file.id ? (
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              ) : (
                <Download className="h-4 w-4" aria-hidden="true" />
              )}
              {downloadingId === file.id
                ? t("evidenceFiles.downloading")
                : t("evidenceFiles.download")}
            </Button>
          </div>
        </div>
      ))}
    </div>
  );
}

function InlineError({ message, retryLabel, onRetry }: { message: string; retryLabel: string; onRetry: () => void }) {
  return (
    <div className="flex min-w-0 flex-col items-start gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 sm:flex-row sm:items-center sm:justify-between">
      <p className="min-w-0 break-words text-sm text-destructive [overflow-wrap:anywhere] whitespace-pre-wrap">
        {message}
      </p>
      <Button type="button" variant="outline" size="sm" onClick={onRetry}>
        <RefreshCw className="h-4 w-4" aria-hidden="true" />
        {retryLabel}
      </Button>
    </div>
  );
}

function EvidenceFilesSkeleton() {
  return (
    <div className="space-y-3" aria-busy="true">
      <Skeleton className="h-4 w-3/4" />
      <Skeleton className="h-10 w-full" />
      <Skeleton className="h-20 w-full" />
    </div>
  );
}

function validateFile(file: File) {
  const parts = file.name.split(".");
  const extension = parts.pop()?.toLowerCase() ?? "";

  if (
    parts.length !== 1
    || !parts[0]
    || !ALLOWED_EXTENSIONS.has(extension)
    || (file.type && !ALLOWED_MIME_TYPES.has(file.type))
  ) {
    return "invalidFormat";
  }
  if (file.size < 1) return "emptyFile";
  if (file.size > MAX_FILE_SIZE) return "fileTooLarge";

  return null;
}

function fileActionError(error: unknown, action: "upload" | "download", t: TFunction) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t("evidenceFiles.forbidden");
    if (error.status === 404) return t("evidenceFiles.notFound");
    if (error.status === 409) return t("evidenceFiles.conflict");
    if (error.status === 422) return t("evidenceFiles.invalidFile");
    if (error.status === 429) return t("evidenceFiles.rateLimited");
  }

  return t(`evidenceFiles.${action}Error`);
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

  return t(`evidenceFiles.mime.${key}`);
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
