export interface PdfPreviewTab {
  closed: boolean;
  opener: unknown;
  document: { title: string; body: { textContent: string | null } };
  location: { replace: (url: string) => void };
  addEventListener: (type: "load", listener: () => void, options: { once: true }) => void;
  close: () => void;
}

interface PdfPreviewDependencies<T> {
  load: (signal: AbortSignal) => Promise<T>;
  validate: (response: T) => void;
  openTab: () => PdfPreviewTab | null;
  createObjectUrl: (response: T) => string;
  revokeObjectUrl: (url: string) => void;
  setTimer: (callback: () => void, delay: number) => number;
  clearTimer: (timer: number) => void;
  title: () => string;
  loadingText: () => string;
  onPending: (pending: boolean) => void;
  onPopupBlocked: () => void;
  onError: () => void;
  onLoaded?: () => void;
}

export type PdfPreviewResult = "opened" | "fallback" | "ignored" | "aborted" | "error";

export function createAuthenticatedPdfPreview<T>(dependencies: PdfPreviewDependencies<T>) {
  let controller: AbortController | null = null;
  let cleanupObjectUrl: (() => void) | null = null;

  function cleanup() {
    cleanupObjectUrl?.();
    cleanupObjectUrl = null;
  }

  async function open(): Promise<PdfPreviewResult> {
    if (controller) return "ignored";

    const previewTab = dependencies.openTab();
    if (!previewTab) {
      dependencies.onPopupBlocked();
      return "fallback";
    }

    previewTab.opener = null;
    previewTab.document.title = dependencies.title();
    previewTab.document.body.textContent = dependencies.loadingText();
    cleanup();
    const request = new AbortController();
    controller = request;
    dependencies.onPending(true);

    try {
      const response = await dependencies.load(request.signal);
      dependencies.validate(response);

      if (request.signal.aborted || previewTab.closed) {
        if (!previewTab.closed) previewTab.close();
        return "aborted";
      }

      const objectUrl = dependencies.createObjectUrl(response);
      let revoked = false;
      let fallbackTimer: number | null = null;
      let loadTimer: number | null = null;
      const revoke = () => {
        if (revoked) return;
        revoked = true;
        if (fallbackTimer !== null) dependencies.clearTimer(fallbackTimer);
        if (loadTimer !== null) dependencies.clearTimer(loadTimer);
        dependencies.revokeObjectUrl(objectUrl);
        if (cleanupObjectUrl === revoke) cleanupObjectUrl = null;
      };

      cleanupObjectUrl = revoke;
      previewTab.addEventListener(
        "load",
        () => {
          loadTimer = dependencies.setTimer(revoke, 30_000);
        },
        { once: true },
      );
      fallbackTimer = dependencies.setTimer(revoke, 300_000);
      previewTab.location.replace(`${objectUrl}#toolbar=1&navpanes=1&view=FitH`);
      dependencies.onLoaded?.();
      return "opened";
    } catch (error) {
      if (!previewTab.closed) previewTab.close();
      cleanup();
      if (
        request.signal.aborted ||
        (error instanceof DOMException && error.name === "AbortError")
      ) {
        return "aborted";
      }
      dependencies.onError();
      return "error";
    } finally {
      if (controller === request) controller = null;
      dependencies.onPending(false);
    }
  }

  function dispose() {
    controller?.abort();
    controller = null;
    cleanup();
    dependencies.onPending(false);
  }

  return { open, dispose };
}
