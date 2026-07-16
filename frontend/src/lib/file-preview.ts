const PREVIEW_MIME_TYPES = new Set(["application/pdf", "image/jpeg", "image/png"]);

export function normalizePreviewMimeType(value: string) {
  return value.split(";", 1)[0].trim().toLowerCase();
}

export function isPreviewableMimeType(value: unknown): value is string {
  return typeof value === "string" && PREVIEW_MIME_TYPES.has(normalizePreviewMimeType(value));
}
