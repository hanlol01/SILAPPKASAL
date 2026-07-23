import { useCallback, useEffect, useRef, useState } from "react";

import { fetchContentAttachment } from "@/lib/content-attachment-api";

interface AuthenticatedContentImageProps {
  publicId: string;
  alt: string;
  className?: string;
  onUnavailable?: () => void;
}

export function AuthenticatedContentImage({
  publicId,
  alt,
  className,
  onUnavailable,
}: AuthenticatedContentImageProps) {
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const objectUrlRef = useRef<string | null>(null);
  const onUnavailableRef = useRef(onUnavailable);

  useEffect(() => {
    onUnavailableRef.current = onUnavailable;
  }, [onUnavailable]);

  const revokeObjectUrl = useCallback(() => {
    if (!objectUrlRef.current) return;

    URL.revokeObjectURL(objectUrlRef.current);
    objectUrlRef.current = null;
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    revokeObjectUrl();
    setObjectUrl(null);

    void fetchContentAttachment(publicId, controller.signal)
      .then((response) => {
        const blobType = response.blob.type.split(";", 1)[0].trim().toLowerCase();
        const invalidResponse =
          !response.contentType.startsWith("image/") ||
          blobType !== response.contentType ||
          (response.contentLength !== null && response.contentLength !== response.blob.size);
        if (controller.signal.aborted) return;
        if (invalidResponse) {
          onUnavailableRef.current?.();
          return;
        }

        objectUrlRef.current = URL.createObjectURL(response.blob);
        setObjectUrl(objectUrlRef.current);
      })
      .catch((error: unknown) => {
        if (
          controller.signal.aborted ||
          (error instanceof DOMException && error.name === "AbortError")
        ) {
          return;
        }
        onUnavailableRef.current?.();
      });

    return () => {
      controller.abort();
      revokeObjectUrl();
    };
  }, [publicId, revokeObjectUrl]);

  if (!objectUrl) return null;

  return (
    <img
      alt={alt}
      className={className}
      src={objectUrl}
      onError={() => {
        revokeObjectUrl();
        setObjectUrl(null);
        onUnavailableRef.current?.();
      }}
    />
  );
}
