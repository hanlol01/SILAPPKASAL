import { FileText, Loader2 } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { Button } from "@/components/ui/button";
import { downloadContentAttachment, fetchContentAttachment } from "@/lib/content-attachment-api";
import type { ContentAttachment } from "@/lib/content-management-api";

export function PublishedContentAttachment({ attachment }: { attachment: ContentAttachment }) {
  const { t } = useTranslation("informationCenter");
  const [downloading, setDownloading] = useState(false);

  async function download() {
    if (downloading) return;
    setDownloading(true);
    try {
      await downloadContentAttachment(attachment.public_id, attachment.filename);
      toast.success(t("attachment.downloadSuccess"));
    } catch {
      toast.error(t("attachment.error"));
    } finally {
      setDownloading(false);
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 items-center gap-3">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <FileText className="h-5 w-5" aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <p className="truncate text-sm font-medium" title={attachment.filename}>
            {attachment.filename}
          </p>
          <p className="text-xs text-muted-foreground">{t("attachment.pdf")}</p>
        </div>
      </div>
      <div className="flex min-h-11 flex-wrap items-center gap-2">
        <SecureFilePreviewDialog
          fileKey={attachment.public_id}
          expectedMimeType="application/pdf"
          loadPreview={(signal) => fetchContentAttachment(attachment.public_id, signal)}
          onDownload={() => void download()}
          downloadPending={downloading}
          labels={{
            preview: t("attachment.preview"),
            title: t("attachment.previewTitle", { name: attachment.filename }),
            description: t("attachment.previewDescription"),
            loading: t("attachment.loading"),
            error: t("attachment.error"),
            retry: t("attachment.retry"),
            close: t("attachment.close"),
            download: t("attachment.download"),
            downloading: t("attachment.downloading"),
            imageAlt: attachment.alt_text ?? attachment.filename,
            pdfTitle: attachment.filename,
            pdfFallback: t("attachment.fallback"),
            zoomIn: t("attachment.zoomIn"),
            zoomOut: t("attachment.zoomOut"),
            resetZoom: t("attachment.resetZoom"),
            fit: t("attachment.fit"),
            controls: t("attachment.controls"),
          }}
        />
        <Button
          type="button"
          variant="outline"
          onClick={() => void download()}
          disabled={downloading}
          className="min-h-11"
        >
          {downloading && (
            <Loader2
              className="h-4 w-4 animate-spin motion-reduce:animate-none"
              aria-hidden="true"
            />
          )}
          {downloading ? t("attachment.downloading") : t("attachment.download")}
        </Button>
      </div>
    </div>
  );
}
