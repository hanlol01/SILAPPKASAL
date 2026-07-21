import { useEffect, useState } from "react";

import { fetchContentAttachment } from "@/lib/content-attachment-api";

interface AuthenticatedContentCoverProps {
  publicId: string;
  alt: string;
  className?: string;
  onUnavailable?: () => void;
}

export function AuthenticatedContentCover({
  publicId,
  alt,
  className,
  onUnavailable,
}: AuthenticatedContentCoverProps) {
  const [objectUrl, setObjectUrl] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    let activeUrl: string | null = null;

    void fetchContentAttachment(publicId, controller.signal)
      .then((response) => {
        const blobType = response.blob.type.split(";", 1)[0].trim().toLowerCase();
        const invalidResponse =
          !response.contentType.startsWith("image/") ||
          blobType !== response.contentType ||
          (response.contentLength !== null && response.contentLength !== response.blob.size);
        if (controller.signal.aborted) return;
        if (invalidResponse) {
          onUnavailable?.();
          return;
        }
        activeUrl = URL.createObjectURL(response.blob);
        setObjectUrl(activeUrl);
      })
      .catch((error: unknown) => {
        if (
          controller.signal.aborted ||
          (error instanceof DOMException && error.name === "AbortError")
        )
          return;
        onUnavailable?.();
      });

    return () => {
      controller.abort();
      if (activeUrl) URL.revokeObjectURL(activeUrl);
    };
  }, [onUnavailable, publicId]);

  if (!objectUrl) return null;

  return <img src={objectUrl} alt={alt} className={className} onError={onUnavailable} />;
}
