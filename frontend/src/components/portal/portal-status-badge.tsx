import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";

/**
 * Reporter-safe status badge for the portal.
 *
 * Accepts `portalStatus` — the backend-curated, human-readable status label
 * (e.g. "Submitted", "Under Review", "In Process", "Completed").
 * No raw status codes are expected; the backend is the single source of truth
 * for reporter-visible status text.
 */

/**
 * Maps known portal-safe display labels to color tones.
 * Comparison is case-insensitive. Unknown labels get a neutral muted style.
 */
function statusTone(portalStatus: string): string {
  switch (portalStatus.toLowerCase()) {
    case "submitted":
      return "bg-info/15 text-info border-info/30";
    case "under review":
      return "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning";
    case "in process":
      return "bg-primary/15 text-primary border-primary/30";
    case "completed":
      return "bg-success/15 text-success border-success/30";
    default:
      return "bg-muted text-muted-foreground border-border";
  }
}

/**
 * Maps raw backend portal_status text to deterministic i18n keys or translations.
 */
function getStatusLabel(status: string, t: TFunction): string {
  switch (status.toLowerCase()) {
    case "submitted":
      return "Dikirim";
    case "under review":
      return "Dalam Peninjauan";
    case "in process":
      return "Sedang Diproses";
    case "completed":
      return "Selesai";
    default:
      return status;
  }
}

interface PortalStatusBadgeProps {
  /** Backend-curated reporter-safe status label. */
  portalStatus: string;
  className?: string;
}

export function PortalStatusBadge({
  portalStatus,
  className,
}: PortalStatusBadgeProps) {
  const { t } = useTranslation(["portal"]);
  return (
    <Badge
      variant="outline"
      className={cn("font-medium", statusTone(portalStatus), className)}
    >
      {getStatusLabel(portalStatus, t)}
    </Badge>
  );
}
