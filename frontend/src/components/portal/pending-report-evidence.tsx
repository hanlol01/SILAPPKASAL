import { FileText, FileUp, Trash2 } from "lucide-react";
import { useRef, useState } from "react";
import { useTranslation } from "react-i18next";

import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { isPreviewableMimeType } from "@/lib/file-preview";
import {
  MAX_REPORT_EVIDENCE_FILES,
  REPORT_EVIDENCE_FILE_ACCEPT,
  reportEvidenceFileKey,
  validateReportEvidenceFile,
} from "@/lib/report-evidence-file";

export function PendingReportEvidence({
  files,
  onChange,
  disabled = false,
}: {
  files: File[];
  onChange: (files: File[]) => void;
  disabled?: boolean;
}) {
  const { t, i18n } = useTranslation("portal");
  const inputRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);
  const remainingSlots = Math.max(0, MAX_REPORT_EVIDENCE_FILES - files.length);

  function addFiles(selectedFiles: File[]) {
    if (selectedFiles.length === 0) return;

    const existingKeys = new Set(files.map(reportEvidenceFileKey));
    const nextFiles = [...files];
    let nextError: string | null = null;

    for (const file of selectedFiles) {
      if (nextFiles.length >= MAX_REPORT_EVIDENCE_FILES) {
        nextError = t("evidenceFiles.limitReached");
        break;
      }

      const validationKey = validateReportEvidenceFile(file);
      if (validationKey) {
        nextError = t(`evidenceFiles.${validationKey}`);
        continue;
      }

      const key = reportEvidenceFileKey(file);
      if (existingKeys.has(key)) {
        nextError = t("evidenceFiles.duplicateQueuedFile");
        continue;
      }

      existingKeys.add(key);
      nextFiles.push(file);
    }

    onChange(nextFiles);
    setError(nextError);
    if (inputRef.current) inputRef.current.value = "";
  }

  function removeFile(file: File) {
    const key = reportEvidenceFileKey(file);
    onChange(files.filter((candidate) => reportEvidenceFileKey(candidate) !== key));
    setError(null);
  }

  return (
    <section className="min-w-0 space-y-3 border-t pt-5" aria-labelledby="pending-evidence-title">
      <div className="min-w-0 space-y-1">
        <h3 id="pending-evidence-title" className="flex items-center gap-2 text-sm font-semibold">
          <FileUp className="h-4 w-4 shrink-0" aria-hidden="true" />
          {t("evidenceFiles.beforeSubmitTitle")}
          <span className="font-normal text-muted-foreground">({t("optional")})</span>
        </h3>
        <p className="text-sm text-muted-foreground">
          {t("evidenceFiles.beforeSubmitDescription")}
        </p>
      </div>

      <Input
        ref={inputRef}
        id="new-report-evidence-files"
        type="file"
        multiple
        accept={REPORT_EVIDENCE_FILE_ACCEPT}
        className="sr-only"
        disabled={disabled || remainingSlots === 0}
        aria-describedby="new-report-evidence-help"
        onChange={(event) => addFiles(Array.from(event.target.files ?? []))}
      />

      <div className="flex min-w-0 flex-col gap-3 rounded-md border border-dashed bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex min-w-0 items-start gap-3">
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground">
            <FileUp className="h-4 w-4" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <p className="text-sm font-medium">{t("evidenceFiles.chooseBeforeSubmit")}</p>
            <p id="new-report-evidence-help" className="text-xs text-muted-foreground">
              {t("evidenceFiles.formatsHelp")}
            </p>
          </div>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-full shrink-0 sm:w-auto"
          disabled={disabled || remainingSlots === 0}
          onClick={() => inputRef.current?.click()}
        >
          {t("evidenceFiles.chooseFile")}
        </Button>
      </div>

      <p className="text-xs text-muted-foreground">
        {t("evidenceFiles.queuedSummary", { count: files.length, remaining: remainingSlots })}
      </p>

      {files.length > 0 && (
        <div className="min-w-0 space-y-2" aria-live="polite">
          {files.map((file) => (
            <div
              key={reportEvidenceFileKey(file)}
              className="flex min-w-0 flex-col gap-3 rounded-md border bg-background p-3 sm:flex-row sm:items-start"
            >
              <div className="flex min-w-0 flex-1 items-start gap-3">
                <FileText className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                  <p className="break-words text-sm font-medium [overflow-wrap:anywhere]">
                    {file.name}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {formatByteSize(file.size, i18n.language)}
                  </p>
                </div>
              </div>
              <div className="flex w-full shrink-0 items-center justify-end gap-1 sm:w-auto">
                {isPreviewableMimeType(file.type) && (
                  <SecureFilePreviewDialog
                    fileKey={reportEvidenceFileKey(file)}
                    expectedMimeType={file.type}
                    loadPreview={(signal) => localFilePreview(file, signal)}
                    labels={{
                      preview: t("evidenceFiles.preview"),
                      title: t("evidenceFiles.previewTitle", { name: file.name }),
                      description: t("evidenceFiles.localPreviewDescription"),
                      loading: t("evidenceFiles.previewLoading"),
                      error: t("evidenceFiles.previewError"),
                      retry: t("evidenceFiles.previewRetry"),
                      close: t("evidenceFiles.previewClose"),
                      download: t("evidenceFiles.download"),
                      downloading: t("evidenceFiles.downloading"),
                      imageAlt: t("evidenceFiles.previewImageAlt", { name: file.name }),
                      pdfTitle: t("evidenceFiles.previewPdfTitle", { name: file.name }),
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
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 shrink-0"
                  disabled={disabled}
                  aria-label={t("evidenceFiles.removeQueuedFile", { name: file.name })}
                  title={t("evidenceFiles.removeFile")}
                  onClick={() => removeFile(file)}
                >
                  <Trash2 className="h-4 w-4" aria-hidden="true" />
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}

      {error && <p className="text-xs text-destructive" role="alert">{error}</p>}
    </section>
  );
}

async function localFilePreview(file: File, signal: AbortSignal) {
  if (signal.aborted) throw new DOMException("Preview aborted", "AbortError");

  return {
    blob: file,
    contentType: file.type,
    contentLength: file.size,
    filename: file.name,
  };
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
