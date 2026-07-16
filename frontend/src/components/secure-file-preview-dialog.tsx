import { Download, Eye, FileWarning, Loader2, RefreshCw } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import type { AuthenticatedBlobResponse } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import {
  isPreviewableMimeType,
  normalizePreviewMimeType,
} from "@/lib/file-preview";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";

export interface SecureFilePreviewLabels {
  preview: string;
  title: string;
  description: string;
  loading: string;
  error: string;
  retry: string;
  close: string;
  download: string;
  downloading: string;
  imageAlt: string;
  pdfTitle: string;
  pdfFallback: string;
}

export function SecureFilePreviewDialog({
  fileKey,
  expectedMimeType,
  loadPreview,
  onPreviewLoaded,
  onDownload,
  downloadPending = false,
  labels,
}: {
  fileKey: string;
  expectedMimeType: string;
  loadPreview: (signal: AbortSignal) => Promise<AuthenticatedBlobResponse>;
  onPreviewLoaded?: () => void;
  onDownload: () => void;
  downloadPending?: boolean;
  labels: SecureFilePreviewLabels;
}) {
  const [open, setOpen] = useState(false);
  const [status, setStatus] = useState<"idle" | "loading" | "ready" | "error">("idle");
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [resolvedMimeType, setResolvedMimeType] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const objectUrlRef = useRef<string | null>(null);
  const requestRef = useRef(0);
  const openRef = useRef(false);
  const loadPreviewRef = useRef(loadPreview);
  const onPreviewLoadedRef = useRef(onPreviewLoaded);
  const normalizedExpectedMime = normalizePreviewMimeType(expectedMimeType);

  useEffect(() => {
    loadPreviewRef.current = loadPreview;
  }, [loadPreview]);

  useEffect(() => {
    onPreviewLoadedRef.current = onPreviewLoaded;
  }, [onPreviewLoaded]);

  const revokeObjectUrl = useCallback(() => {
    if (objectUrlRef.current) {
      URL.revokeObjectURL(objectUrlRef.current);
      objectUrlRef.current = null;
    }
  }, []);

  const requestPreview = useCallback(async () => {
    const requestId = requestRef.current + 1;
    requestRef.current = requestId;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    revokeObjectUrl();
    setObjectUrl(null);
    setResolvedMimeType(null);
    setStatus("loading");

    try {
      const response = await loadPreviewRef.current(controller.signal);
      const responseMime = normalizePreviewMimeType(response.contentType);
      const blobMime = normalizePreviewMimeType(response.blob.type);

      if (
        controller.signal.aborted
        || requestId !== requestRef.current
        || !openRef.current
      ) {
        return;
      }

      if (
        !isPreviewableMimeType(responseMime)
        || responseMime !== normalizedExpectedMime
        || blobMime !== normalizedExpectedMime
        || (response.contentLength !== null && response.contentLength !== response.blob.size)
        || typeof URL.createObjectURL !== "function"
      ) {
        throw new Error("Preview response metadata is inconsistent");
      }

      const nextUrl = URL.createObjectURL(response.blob);

      if (
        controller.signal.aborted
        || requestId !== requestRef.current
        || !openRef.current
      ) {
        URL.revokeObjectURL(nextUrl);
        return;
      }

      objectUrlRef.current = nextUrl;
      setObjectUrl(nextUrl);
      setResolvedMimeType(responseMime);
      setStatus("ready");

      try {
        onPreviewLoadedRef.current?.();
      } catch {
        // Preview rendering must not depend on secondary cache refreshes.
      }
    } catch (error) {
      if (
        controller.signal.aborted
        || requestId !== requestRef.current
        || !openRef.current
        || (error instanceof DOMException && error.name === "AbortError")
      ) {
        return;
      }

      revokeObjectUrl();
      setObjectUrl(null);
      setResolvedMimeType(null);
      setStatus("error");
    } finally {
      if (abortRef.current === controller) {
        abortRef.current = null;
      }
    }
  }, [normalizedExpectedMime, revokeObjectUrl]);

  useEffect(() => {
    if (!open) return;

    void requestPreview();

    return () => {
      requestRef.current += 1;
      abortRef.current?.abort();
      abortRef.current = null;
      revokeObjectUrl();
    };
  }, [fileKey, open, requestPreview, revokeObjectUrl]);

  function handleOpenChange(nextOpen: boolean) {
    openRef.current = nextOpen;

    if (!nextOpen) {
      requestRef.current += 1;
      abortRef.current?.abort();
      abortRef.current = null;
      revokeObjectUrl();
      setObjectUrl(null);
      setResolvedMimeType(null);
      setStatus("idle");
    }

    setOpen(nextOpen);
  }

  if (!isPreviewableMimeType(normalizedExpectedMime)) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" size="sm">
          <Eye className="h-4 w-4" aria-hidden="true" />
          {labels.preview}
        </Button>
      </DialogTrigger>
      <DialogContent className="flex max-h-[90dvh] w-[calc(100vw-2rem)] max-w-5xl min-w-0 flex-col gap-0 overflow-hidden p-0">
        <DialogHeader className="min-w-0 border-b px-4 py-4 pr-12 sm:px-6 sm:py-5 sm:pr-12">
          <DialogTitle className="min-w-0 break-words text-base [overflow-wrap:anywhere] sm:text-lg">
            {labels.title}
          </DialogTitle>
          <DialogDescription className="min-w-0 break-words [overflow-wrap:anywhere]">
            {labels.description}
          </DialogDescription>
        </DialogHeader>

        <div className="flex min-h-64 min-w-0 flex-1 items-center justify-center overflow-auto bg-muted/30 p-3 sm:p-5">
          {status === "loading" && (
            <div className="flex min-w-0 flex-col items-center gap-3 text-center text-sm text-muted-foreground" role="status">
              <Loader2 className="h-6 w-6 animate-spin" aria-hidden="true" />
              <span className="break-words [overflow-wrap:anywhere]">{labels.loading}</span>
            </div>
          )}

          {status === "error" && (
            <div className="flex max-w-md min-w-0 flex-col items-center gap-3 rounded-md border border-destructive/30 bg-background p-4 text-center">
              <FileWarning className="h-6 w-6 text-destructive" aria-hidden="true" />
              <p className="min-w-0 break-words text-sm text-destructive [overflow-wrap:anywhere]" role="alert">
                {labels.error}
              </p>
              <Button type="button" variant="outline" size="sm" onClick={() => void requestPreview()}>
                <RefreshCw className="h-4 w-4" aria-hidden="true" />
                {labels.retry}
              </Button>
            </div>
          )}

          {status === "ready" && objectUrl && resolvedMimeType?.startsWith("image/") && (
            <img
              src={objectUrl}
              alt={labels.imageAlt}
              className="max-h-[65dvh] max-w-full object-contain"
            />
          )}

          {status === "ready" && objectUrl && resolvedMimeType === "application/pdf" && (
            <div className="w-full min-w-0 space-y-2">
              <iframe
                src={objectUrl}
                title={labels.pdfTitle}
                sandbox=""
                referrerPolicy="no-referrer"
                className="h-[60dvh] min-h-80 w-full min-w-0 rounded-md border bg-background"
              />
              <p className="text-center text-xs text-muted-foreground">{labels.pdfFallback}</p>
            </div>
          )}
        </div>

        <DialogFooter className="min-w-0 border-t px-4 py-3 sm:px-6">
          <DialogClose asChild>
            <Button type="button" variant="outline">{labels.close}</Button>
          </DialogClose>
          <Button type="button" onClick={onDownload} disabled={downloadPending}>
            {downloadPending ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <Download className="h-4 w-4" aria-hidden="true" />
            )}
            {downloadPending ? labels.downloading : labels.download}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
