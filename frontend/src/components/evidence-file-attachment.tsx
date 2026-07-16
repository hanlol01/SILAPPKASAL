import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Download, FileText, Loader2, Upload } from "lucide-react";
import { useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { CompactFileUpload } from "@/components/compact-file-upload";
import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { ApiError } from "@/lib/api-client";
import { isPreviewableMimeType } from "@/lib/file-preview";
import { formatDateTime } from "@/lib/format";
import {
  downloadEvidenceFile,
  operationsQueryKeys,
  previewEvidenceFile,
  uploadEvidenceFile,
} from "@/lib/operations-api";
import type { EvidenceMetadata } from "@/lib/operations-types";

const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ALLOWED_EXTENSIONS = new Set(["pdf", "jpg", "jpeg", "png"]);
const ALLOWED_MIME_TYPES = new Set(["application/pdf", "image/jpeg", "image/png"]);

export function EvidenceFileAttachment({
  evidence,
  canUpload,
  canDownload,
  language,
}: {
  evidence: EvidenceMetadata;
  canUpload: boolean;
  canDownload: boolean;
  language: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const inputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [clientError, setClientError] = useState<string | null>(null);
  const attachment = evidence.file_attachment;
  const uploadAllowed = canUpload && evidence.status !== "archived" && !attachment;

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadEvidenceFile(evidence.id, file),
    onSuccess: async () => {
      clearSelection();

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: operationsQueryKeys.evidences(evidence.investigation_id),
        }),
        queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidence(evidence.id) }),
        queryClient.invalidateQueries({ queryKey: operationsQueryKeys.evidenceCustody(evidence.id) }),
      ]);
      toast.success(t("dashboard:sections.evidenceAttachment.uploadSuccess"));
    },
    onError: (error) => {
      toast.error(evidenceFileError(error, "upload", t));
    },
  });

  const downloadMutation = useMutation({
    mutationFn: () => downloadEvidenceFile(evidence.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: operationsQueryKeys.evidenceCustody(evidence.id),
      });
      toast.success(t("dashboard:sections.evidenceAttachment.downloadSuccess"));
    },
    onError: (error) => {
      toast.error(evidenceFileError(error, "download", t));
    },
  });

  function handleFileChange(file: File | null) {
    if (!file) {
      setSelectedFile(null);
      setClientError(null);
      return;
    }

    const validationKey = validateEvidenceFile(file);
    if (validationKey) {
      setSelectedFile(null);
      setClientError(t(`dashboard:sections.evidenceAttachment.${validationKey}`));
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
    <section className="min-w-0 border-t pt-3" aria-labelledby={`evidence-file-${evidence.id}`}>
      <div className="mb-2 flex min-w-0 items-center gap-2">
        <FileText className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <h4 id={`evidence-file-${evidence.id}`} className="min-w-0 break-words text-sm font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
          {t("dashboard:sections.evidenceAttachment.title")}
        </h4>
      </div>

      {attachment ? (
        <div className="min-w-0 space-y-3">
          <dl className="grid min-w-0 gap-x-4 gap-y-2 text-xs sm:grid-cols-2">
            <AttachmentDetail
              label={t("dashboard:sections.evidenceAttachment.fileName")}
              value={attachment.original_filename}
            />
            <AttachmentDetail
              label={t("dashboard:sections.evidenceAttachment.fileType")}
              value={formatMimeType(attachment.mime_type, t)}
            />
            <AttachmentDetail
              label={t("dashboard:sections.evidenceAttachment.fileSize")}
              value={formatByteSize(attachment.file_size, language)}
            />
            <AttachmentDetail
              label={t("dashboard:sections.evidenceAttachment.uploadedAt")}
              value={formatDateTime(attachment.uploaded_at, language)}
            />
            {attachment.uploaded_by?.name && (
              <AttachmentDetail
                label={t("dashboard:sections.evidenceAttachment.uploadedBy")}
                value={attachment.uploaded_by.name}
              />
            )}
          </dl>
          {canDownload && (
            <div className="flex min-w-0 flex-wrap gap-2">
              {isPreviewableMimeType(attachment.mime_type) && (
                <SecureFilePreviewDialog
                  fileKey={`${evidence.id}:${attachment.mime_type}:${attachment.original_filename}`}
                  expectedMimeType={attachment.mime_type}
                  loadPreview={(signal) => previewEvidenceFile(evidence.id, signal)}
                  onPreviewLoaded={() => {
                    void queryClient.invalidateQueries({
                      queryKey: operationsQueryKeys.evidenceCustody(evidence.id),
                    });
                  }}
                  onDownload={() => downloadMutation.mutate()}
                  downloadPending={downloadMutation.isPending}
                  labels={{
                    preview: t("dashboard:sections.evidenceAttachment.preview"),
                    title: t("dashboard:sections.evidenceAttachment.previewTitle", { name: attachment.original_filename }),
                    description: t("dashboard:sections.evidenceAttachment.previewDescription"),
                    loading: t("dashboard:sections.evidenceAttachment.previewLoading"),
                    error: t("dashboard:sections.evidenceAttachment.previewError"),
                    retry: t("dashboard:sections.evidenceAttachment.previewRetry"),
                    close: t("dashboard:sections.evidenceAttachment.previewClose"),
                    download: t("dashboard:sections.evidenceAttachment.download"),
                    downloading: t("dashboard:sections.evidenceAttachment.downloading"),
                    imageAlt: t("dashboard:sections.evidenceAttachment.previewImageAlt", { name: attachment.original_filename }),
                    pdfTitle: t("dashboard:sections.evidenceAttachment.previewPdfTitle", { name: attachment.original_filename }),
                    pdfFallback: t("dashboard:sections.evidenceAttachment.previewPdfFallback"),
                  }}
                />
              )}
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={downloadMutation.isPending}
                onClick={() => downloadMutation.mutate()}
              >
                {downloadMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                ) : (
                  <Download className="h-4 w-4" aria-hidden="true" />
                )}
                {downloadMutation.isPending
                  ? t("dashboard:sections.evidenceAttachment.downloading")
                  : t("dashboard:sections.evidenceAttachment.download")}
              </Button>
            </div>
          )}
        </div>
      ) : uploadAllowed ? (
        <div className="min-w-0 space-y-3">
          <CompactFileUpload
            inputId={`evidence-file-input-${evidence.id}`}
            inputRef={inputRef}
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            title={t("dashboard:sections.evidenceAttachment.chooseFile")}
            instructions={t("dashboard:sections.evidenceAttachment.formatsHelp")}
            chooseLabel={t("dashboard:sections.evidenceAttachment.chooseFile")}
            changeLabel={t("dashboard:sections.evidenceAttachment.changeFile")}
            removeLabel={t("dashboard:sections.evidenceAttachment.removeFile")}
            selectedLabel={t("dashboard:sections.evidenceAttachment.selectedFileLabel")}
            selectedFile={selectedFile}
            selectedDetails={selectedFile
              ? `${formatMimeType(selectedFile.type, t)} | ${formatByteSize(selectedFile.size, language)}`
              : undefined}
            error={clientError}
            disabled={uploadMutation.isPending}
            onFileChange={handleFileChange}
            onRemove={clearSelection}
          />
          <div className="flex min-w-0 flex-wrap gap-2">
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
                ? t("dashboard:sections.evidenceAttachment.uploading")
                : t("dashboard:sections.evidenceAttachment.upload")}
            </Button>
          </div>
        </div>
      ) : (
        <p className="min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
          {t("dashboard:sections.evidenceAttachment.noAttachment")}
        </p>
      )}
    </section>
  );
}

function AttachmentDetail({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="mt-0.5 min-w-0 break-words text-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">{value}</dd>
    </div>
  );
}

function validateEvidenceFile(file: File) {
  const extension = file.name.split(".").pop()?.toLowerCase() ?? "";

  if (!ALLOWED_EXTENSIONS.has(extension) || (file.type && !ALLOWED_MIME_TYPES.has(file.type))) {
    return "invalidFormat";
  }
  if (file.size < 1) return "emptyFile";
  if (file.size > MAX_FILE_SIZE) return "fileTooLarge";

  return null;
}

function evidenceFileError(error: unknown, action: "upload" | "download", t: TFunction) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t("dashboard:sections.evidenceAttachment.forbidden");
    if (error.status === 404) return t("dashboard:sections.evidenceAttachment.notFound");
    if (error.status === 409) return t("dashboard:sections.evidenceAttachment.conflict");
    if (error.status === 422) return t("dashboard:sections.evidenceAttachment.invalidFile");
    if (error.status === 429) return t("dashboard:sections.evidenceAttachment.rateLimited");
  }

  return t(`dashboard:sections.evidenceAttachment.${action}Error`);
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

  return t(`dashboard:sections.evidenceAttachment.mime.${key}`);
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
