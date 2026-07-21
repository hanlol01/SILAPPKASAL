import { FileText } from "lucide-react";
import { useTranslation } from "react-i18next";

import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { fetchContentAttachment } from "@/lib/content-attachment-api";
import type { ContentAttachment } from "@/lib/content-management-api";

export function AuthenticatedContentAttachment({ attachment }: { attachment: ContentAttachment }) {
  const { t } = useTranslation("contentGovernance");

  return (
    <div className="flex min-w-0 flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 items-center gap-2">
        <FileText className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <span className="truncate text-sm" title={attachment.filename}>
          {attachment.filename}
        </span>
      </div>
      <SecureFilePreviewDialog
        fileKey={attachment.public_id}
        expectedMimeType="application/pdf"
        loadPreview={(signal) => fetchContentAttachment(attachment.public_id, signal)}
        labels={{
          preview: t("review.attachmentOpen"),
          title: `${t("review.attachmentTitle")}: ${attachment.filename}`,
          description: t("review.attachmentDescription"),
          loading: t("review.attachmentLoading"),
          error: t("review.attachmentError"),
          retry: t("review.attachmentRetry"),
          close: t("review.attachmentClose"),
          download: t("review.attachmentDownload"),
          downloading: t("review.attachmentDownloading"),
          imageAlt: attachment.alt_text ?? attachment.filename,
          pdfTitle: attachment.filename,
          pdfFallback: t("review.attachmentFallback"),
          zoomIn: t("review.zoomIn"),
          zoomOut: t("review.zoomOut"),
          resetZoom: t("review.resetZoom"),
          fit: t("review.fitPreview"),
          controls: t("review.previewControls"),
        }}
      />
    </div>
  );
}
