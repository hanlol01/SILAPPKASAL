import { apiFetchBlob } from "@/lib/api-client";

export function fetchContentAttachment(publicId: string, signal?: AbortSignal) {
  return apiFetchBlob(`/content/attachments/${encodeURIComponent(publicId)}`, { signal });
}
