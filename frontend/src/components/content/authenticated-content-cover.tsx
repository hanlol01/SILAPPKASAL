import { AuthenticatedContentImage } from "@/components/content/authenticated-content-image";

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
  return (
    <AuthenticatedContentImage
      publicId={publicId}
      alt={alt}
      className={className}
      onUnavailable={onUnavailable}
    />
  );
}
