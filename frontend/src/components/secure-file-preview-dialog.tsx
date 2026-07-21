import {
  Download,
  Eye,
  FileWarning,
  Loader2,
  Maximize2,
  Minus,
  Plus,
  RefreshCw,
} from "lucide-react";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type KeyboardEvent,
  type PointerEvent,
} from "react";
import { toast } from "sonner";
import type { AuthenticatedBlobResponse } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { isPreviewableMimeType, normalizePreviewMimeType } from "@/lib/file-preview";
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
  zoomIn: string;
  zoomOut: string;
  resetZoom: string;
  fit: string;
  controls: string;
}

const MIN_IMAGE_ZOOM = 0.01;
const MAX_IMAGE_ZOOM = 4;
const IMAGE_ZOOM_LEVELS = [0.1, 0.25, 0.5, 0.75, 1, 1.5, 2, 3, 4] as const;

function validatePreviewResponse(response: AuthenticatedBlobResponse, expectedMimeType: string) {
  const responseMime = normalizePreviewMimeType(response.contentType);
  const blobMime = normalizePreviewMimeType(response.blob.type);

  if (
    !isPreviewableMimeType(responseMime) ||
    responseMime !== expectedMimeType ||
    blobMime !== expectedMimeType ||
    (response.contentLength !== null && response.contentLength !== response.blob.size) ||
    typeof URL.createObjectURL !== "function"
  ) {
    throw new Error("Preview response metadata is inconsistent");
  }

  return responseMime;
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
  onDownload?: () => void;
  downloadPending?: boolean;
  labels: SecureFilePreviewLabels;
}) {
  const [open, setOpen] = useState(false);
  const [status, setStatus] = useState<"idle" | "loading" | "ready" | "error">("idle");
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [resolvedMimeType, setResolvedMimeType] = useState<string | null>(null);
  const [naturalSize, setNaturalSize] = useState({ width: 0, height: 0 });
  const [fitScale, setFitScale] = useState(1);
  const [zoom, setZoom] = useState(1);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const [fitActive, setFitActive] = useState(true);
  const [pdfOpening, setPdfOpening] = useState(false);
  const abortRef = useRef<AbortController | null>(null);
  const pdfAbortRef = useRef<AbortController | null>(null);
  const pdfCleanupRef = useRef<(() => void) | null>(null);
  const objectUrlRef = useRef<string | null>(null);
  const requestRef = useRef(0);
  const openRef = useRef(false);
  const loadPreviewRef = useRef(loadPreview);
  const onPreviewLoadedRef = useRef(onPreviewLoaded);
  const imageViewportRef = useRef<HTMLDivElement | null>(null);
  const dragRef = useRef<{
    pointerId: number;
    startX: number;
    startY: number;
    originX: number;
    originY: number;
  } | null>(null);
  const normalizedExpectedMime = normalizePreviewMimeType(expectedMimeType);

  useEffect(() => {
    loadPreviewRef.current = loadPreview;
  }, [loadPreview]);

  useEffect(() => {
    onPreviewLoadedRef.current = onPreviewLoaded;
  }, [onPreviewLoaded]);

  useEffect(
    () => () => {
      pdfAbortRef.current?.abort();
      pdfCleanupRef.current?.();
      pdfCleanupRef.current = null;
    },
    [],
  );

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
    setNaturalSize({ width: 0, height: 0 });
    setFitScale(1);
    setZoom(1);
    setPan({ x: 0, y: 0 });
    setFitActive(true);
    dragRef.current = null;
    setStatus("loading");

    try {
      const response = await loadPreviewRef.current(controller.signal);

      if (controller.signal.aborted || requestId !== requestRef.current || !openRef.current) {
        return;
      }

      const responseMime = validatePreviewResponse(response, normalizedExpectedMime);

      const nextUrl = URL.createObjectURL(response.blob);

      if (controller.signal.aborted || requestId !== requestRef.current || !openRef.current) {
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
        controller.signal.aborted ||
        requestId !== requestRef.current ||
        !openRef.current ||
        (error instanceof DOMException && error.name === "AbortError")
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
      setNaturalSize({ width: 0, height: 0 });
      setFitScale(1);
      setZoom(1);
      setPan({ x: 0, y: 0 });
      setFitActive(true);
      dragRef.current = null;
      setStatus("idle");
    }

    setOpen(nextOpen);
  }

  async function openPdfPreview() {
    if (pdfAbortRef.current) return;

    const previewTab = window.open("about:blank", "_blank");

    if (!previewTab) {
      if (onDownload) {
        onDownload();
      } else {
        toast.error(labels.error);
      }
      return;
    }

    previewTab.opener = null;
    previewTab.document.title = labels.pdfTitle;
    previewTab.document.body.textContent = labels.loading;

    pdfCleanupRef.current?.();
    pdfCleanupRef.current = null;
    const controller = new AbortController();
    pdfAbortRef.current = controller;
    setPdfOpening(true);

    try {
      const response = await loadPreviewRef.current(controller.signal);
      validatePreviewResponse(response, "application/pdf");

      if (controller.signal.aborted || previewTab.closed) {
        if (!previewTab.closed) previewTab.close();
        return;
      }

      const nextUrl = URL.createObjectURL(response.blob);
      let revoked = false;
      let fallbackTimer = 0;

      const revokePdfUrl = () => {
        if (revoked) return;
        revoked = true;
        window.clearTimeout(fallbackTimer);
        URL.revokeObjectURL(nextUrl);
        if (pdfCleanupRef.current === revokePdfUrl) pdfCleanupRef.current = null;
      };

      pdfCleanupRef.current = revokePdfUrl;

      previewTab.addEventListener(
        "load",
        () => {
          window.setTimeout(revokePdfUrl, 30_000);
        },
        { once: true },
      );
      fallbackTimer = window.setTimeout(revokePdfUrl, 300_000);
      previewTab.location.replace(`${nextUrl}#toolbar=1&navpanes=1&view=FitH`);

      try {
        onPreviewLoadedRef.current?.();
      } catch {
        // Opening the private preview must not depend on secondary cache refreshes.
      }
    } catch (error) {
      if (!previewTab.closed) previewTab.close();

      if (
        !controller.signal.aborted &&
        !(error instanceof DOMException && error.name === "AbortError")
      ) {
        toast.error(labels.error);
      }
    } finally {
      if (pdfAbortRef.current === controller) {
        pdfAbortRef.current = null;
      }
      setPdfOpening(false);
    }
  }

  const clampZoom = useCallback(
    (value: number) => Math.min(MAX_IMAGE_ZOOM, Math.max(MIN_IMAGE_ZOOM, value)),
    [],
  );

  const clampPan = useCallback(
    (nextPan: { x: number; y: number }, nextZoom = zoom) => {
      const viewport = imageViewportRef.current;
      if (!viewport || !naturalSize.width || !naturalSize.height) return { x: 0, y: 0 };

      const bounds = viewport.getBoundingClientRect();
      const maxX = Math.max(0, (naturalSize.width * nextZoom - bounds.width) / 2);
      const maxY = Math.max(0, (naturalSize.height * nextZoom - bounds.height) / 2);

      return {
        x: Math.min(maxX, Math.max(-maxX, nextPan.x)),
        y: Math.min(maxY, Math.max(-maxY, nextPan.y)),
      };
    },
    [naturalSize.height, naturalSize.width, zoom],
  );

  useEffect(() => {
    const viewport = imageViewportRef.current;
    if (
      !viewport ||
      status !== "ready" ||
      !resolvedMimeType?.startsWith("image/") ||
      !naturalSize.width
    ) {
      return;
    }

    const updateFit = () => {
      const bounds = viewport.getBoundingClientRect();
      const nextFit = clampZoom(
        Math.min(
          1,
          Math.max(1, bounds.width - 24) / naturalSize.width,
          Math.max(1, bounds.height - 24) / naturalSize.height,
        ),
      );
      setFitScale(nextFit);
      if (fitActive) {
        setZoom(nextFit);
        setPan({ x: 0, y: 0 });
      }
    };

    updateFit();
    const observer = new ResizeObserver(updateFit);
    observer.observe(viewport);
    return () => observer.disconnect();
  }, [clampZoom, fitActive, naturalSize.height, naturalSize.width, resolvedMimeType, status]);

  useEffect(() => {
    setPan((current) => clampPan(current, zoom));
  }, [clampPan, zoom]);

  function changeZoom(nextZoom: number) {
    const boundedZoom = clampZoom(nextZoom);
    setFitActive(false);
    setZoom(boundedZoom);
    setPan((current) => clampPan(current, boundedZoom));
  }

  function stepZoom(direction: "in" | "out") {
    const levels = Array.from(new Set([fitScale, ...IMAGE_ZOOM_LEVELS])).sort((a, b) => a - b);
    const epsilon = 0.001;
    const nextZoom =
      direction === "in"
        ? (levels.find((level) => level > zoom + epsilon) ?? MAX_IMAGE_ZOOM)
        : ([...levels].reverse().find((level) => level < zoom - epsilon) ?? fitScale);

    changeZoom(nextZoom);
  }

  function resetZoom() {
    setFitActive(false);
    setZoom(1);
    setPan({ x: 0, y: 0 });
  }

  function fitImage() {
    setFitActive(true);
    setZoom(fitScale);
    setPan({ x: 0, y: 0 });
  }

  function handlePointerDown(event: PointerEvent<HTMLDivElement>) {
    if (
      event.pointerType === "touch" ||
      status !== "ready" ||
      !resolvedMimeType?.startsWith("image/")
    )
      return;

    dragRef.current = {
      pointerId: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      originX: pan.x,
      originY: pan.y,
    };
    event.currentTarget.setPointerCapture(event.pointerId);
    event.preventDefault();
  }

  function handlePointerMove(event: PointerEvent<HTMLDivElement>) {
    const drag = dragRef.current;
    if (!drag || drag.pointerId !== event.pointerId || event.pointerType === "touch") return;

    setPan(
      clampPan({
        x: drag.originX + event.clientX - drag.startX,
        y: drag.originY + event.clientY - drag.startY,
      }),
    );
    event.preventDefault();
  }

  function handlePointerEnd(event: PointerEvent<HTMLDivElement>) {
    if (dragRef.current?.pointerId !== event.pointerId) return;
    dragRef.current = null;
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }
  }

  function handleImageKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const panStep = 24;
    if (event.key === "+" || event.key === "=") stepZoom("in");
    else if (event.key === "-") stepZoom("out");
    else if (event.key === "0") resetZoom();
    else if (event.key.toLowerCase() === "f") fitImage();
    else if (event.key === "ArrowLeft")
      setPan((current) => clampPan({ ...current, x: current.x + panStep }));
    else if (event.key === "ArrowRight")
      setPan((current) => clampPan({ ...current, x: current.x - panStep }));
    else if (event.key === "ArrowUp")
      setPan((current) => clampPan({ ...current, y: current.y + panStep }));
    else if (event.key === "ArrowDown")
      setPan((current) => clampPan({ ...current, y: current.y - panStep }));
    else return;

    event.preventDefault();
  }

  if (!isPreviewableMimeType(normalizedExpectedMime)) {
    return null;
  }

  if (normalizedExpectedMime === "application/pdf") {
    return (
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={pdfOpening}
        onClick={() => void openPdfPreview()}
      >
        {pdfOpening ? (
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
        ) : (
          <Eye className="h-4 w-4" aria-hidden="true" />
        )}
        {pdfOpening ? labels.loading : labels.preview}
      </Button>
    );
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" size="sm">
          <Eye className="h-4 w-4" aria-hidden="true" />
          {labels.preview}
        </Button>
      </DialogTrigger>
      <DialogContent className="left-0 top-0 flex h-dvh max-h-none w-screen max-w-none min-w-0 translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-none border-0 p-0 sm:left-[50%] sm:top-[50%] sm:h-[92dvh] sm:w-[96vw] sm:max-w-7xl sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-lg sm:border">
        <DialogHeader className="min-w-0 shrink-0 border-b bg-background px-4 py-3 pr-12 sm:px-6 sm:py-4 sm:pr-12">
          <DialogTitle className="min-w-0 break-words text-base [overflow-wrap:anywhere] sm:text-lg">
            {labels.title}
          </DialogTitle>
          <DialogDescription className="min-w-0 break-words [overflow-wrap:anywhere]">
            {labels.description}
          </DialogDescription>
        </DialogHeader>

        <div
          ref={imageViewportRef}
          className="relative flex min-h-0 min-w-0 flex-1 items-center justify-center overflow-hidden bg-neutral-950 p-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:p-5"
          tabIndex={status === "ready" && resolvedMimeType?.startsWith("image/") ? 0 : undefined}
          onKeyDown={handleImageKeyDown}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerEnd}
          onPointerCancel={handlePointerEnd}
        >
          {status === "loading" && (
            <div
              className="flex min-w-0 flex-col items-center gap-3 text-center text-sm text-white/70"
              role="status"
            >
              <Loader2 className="h-6 w-6 animate-spin" aria-hidden="true" />
              <span className="break-words [overflow-wrap:anywhere]">{labels.loading}</span>
            </div>
          )}

          {status === "error" && (
            <div className="flex max-w-md min-w-0 flex-col items-center gap-3 rounded-md border border-destructive/30 bg-background p-4 text-center">
              <FileWarning className="h-6 w-6 text-destructive" aria-hidden="true" />
              <p
                className="min-w-0 break-words text-sm text-destructive [overflow-wrap:anywhere]"
                role="alert"
              >
                {labels.error}
              </p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void requestPreview()}
              >
                <RefreshCw className="h-4 w-4" aria-hidden="true" />
                {labels.retry}
              </Button>
            </div>
          )}

          {status === "ready" && objectUrl && resolvedMimeType?.startsWith("image/") && (
            <img
              src={objectUrl}
              alt={labels.imageAlt}
              draggable={false}
              onLoad={(event) =>
                setNaturalSize({
                  width: event.currentTarget.naturalWidth,
                  height: event.currentTarget.naturalHeight,
                })
              }
              className="absolute left-1/2 top-1/2 max-w-none cursor-grab select-none object-contain active:cursor-grabbing motion-safe:transition-transform motion-safe:duration-150"
              style={{
                width: naturalSize.width || undefined,
                height: naturalSize.height || undefined,
                transform: `translate(-50%, -50%) translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
                touchAction: "auto",
              }}
            />
          )}

          {status === "ready" && objectUrl && resolvedMimeType?.startsWith("image/") && (
            <div
              className="absolute bottom-3 left-1/2 z-10 flex max-w-[calc(100%-1.5rem)] -translate-x-1/2 items-center gap-1 rounded-md border bg-background/95 p-1 shadow-lg backdrop-blur sm:bottom-4"
              aria-label={labels.controls}
              onPointerDown={(event) => event.stopPropagation()}
            >
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => stepZoom("out")}
                aria-label={labels.zoomOut}
                title={labels.zoomOut}
              >
                <Minus className="h-4 w-4" aria-hidden="true" />
              </Button>
              <span
                className="w-12 shrink-0 text-center text-xs tabular-nums text-muted-foreground"
                aria-live="polite"
              >
                {Math.round(zoom * 100)}%
              </span>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => stepZoom("in")}
                aria-label={labels.zoomIn}
                title={labels.zoomIn}
              >
                <Plus className="h-4 w-4" aria-hidden="true" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={resetZoom}
                aria-label={labels.resetZoom}
                title={labels.resetZoom}
              >
                100%
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={fitImage}
                aria-label={labels.fit}
                title={labels.fit}
              >
                <Maximize2 className="h-4 w-4" aria-hidden="true" />
              </Button>
            </div>
          )}
        </div>

        <DialogFooter className="min-w-0 shrink-0 border-t bg-background px-4 py-3 [&_button]:w-full sm:px-6 sm:[&_button]:w-auto">
          <DialogClose asChild>
            <Button type="button" variant="outline">
              {labels.close}
            </Button>
          </DialogClose>
          {onDownload && (
            <Button type="button" onClick={onDownload} disabled={downloadPending}>
              {downloadPending ? (
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              ) : (
                <Download className="h-4 w-4" aria-hidden="true" />
              )}
              {downloadPending ? labels.downloading : labels.download}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
