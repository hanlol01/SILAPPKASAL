import { apiDownload, apiFetchBlob } from "@/lib/api-client";

export function fetchContentAttachment(publicId: string, signal?: AbortSignal) {
  return apiFetchBlob(`/content/attachments/${encodeURIComponent(publicId)}`, { signal });
}

export function downloadContentAttachment(publicId: string, filename: string) {
  return apiDownload(`/content/attachments/${encodeURIComponent(publicId)}`, filename);
}
