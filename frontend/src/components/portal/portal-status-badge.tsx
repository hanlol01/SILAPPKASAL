import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { label as humanize } from "@/lib/format";

/**
 * Reporter-safe status badge for the portal.
 *
 * Displays `status_label` if provided by the backend (already human-readable).
 * Falls back to humanizing the raw `status` code via the shared label() utility.
 * This ensures reporters never see raw snake_case status codes in the UI.
 *
 * Uses a generic visual style — no hardcoded status-to-color map.
 * The portal API returns a limited set of reporter-visible statuses,
 * and we avoid assuming what those codes will be.
 */

/**
 * Maps known portal-safe status codes to color tones.
 * Unknown statuses get a neutral muted style.
 */
function statusTone(status: string): string {
  switch (status) {
    case "submitted":
    case "received":
      return "bg-info/15 text-info border-info/30";
    case "under_review":
    case "in_progress":
      return "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning";
    case "forwarded":
      return "bg-primary/15 text-primary border-primary/30";
    case "completed":
    case "resolved":
    case "closed":
      return "bg-success/15 text-success border-success/30";
    case "rejected":
    case "need_info":
      return "bg-destructive/15 text-destructive border-destructive/30";
    default:
      return "bg-muted text-muted-foreground border-border";
  }
}

interface PortalStatusBadgeProps {
  /** Raw status code from the API. */
  status: string;
  /** Human-readable label from the backend, if available. */
  statusLabel?: string | null;
  className?: string;
}

export function PortalStatusBadge({
  status,
  statusLabel,
  className,
}: PortalStatusBadgeProps) {
  const displayText = statusLabel || humanize(status);

  return (
    <Badge
      variant="outline"
      className={cn("font-medium", statusTone(status), className)}
    >
      {displayText}
    </Badge>
  );
}
