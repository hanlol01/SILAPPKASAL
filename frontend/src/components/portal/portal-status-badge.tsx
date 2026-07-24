import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { useTranslation } from "react-i18next";
import {
  Clock,
  Eye,
  LoaderCircle,
  CheckCircle2,
  HelpCircle,
  OctagonX,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { portalStatusCode, portalStatusLabel } from "@/lib/portal-labels";

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
  switch (portalStatusCode(portalStatus)) {
    case "submitted":
      return "bg-info/15 text-info border-info/30";
    case "under_review":
      return "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning";
    case "in_process":
      return "bg-primary/15 text-primary border-primary/30";
    case "completed":
      return "bg-success/15 text-success border-success/30";
    case "cancelled_by_reporter":
    case "withdrawn":
      return "bg-destructive/15 text-destructive border-destructive/30";
    default:
      return "bg-muted text-muted-foreground border-border";
  }
}

/**
 * Maps known portal-safe display labels to leading icons.
 * Unknown labels get a generic HelpCircle icon.
 */
function statusIcon(portalStatus: string): LucideIcon {
  switch (portalStatusCode(portalStatus)) {
    case "submitted":
      return Clock;
    case "under_review":
      return Eye;
    case "in_process":
      return LoaderCircle;
    case "completed":
      return CheckCircle2;
    case "cancelled_by_reporter":
    case "withdrawn":
      return OctagonX;
    default:
      return HelpCircle;
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
  const label = portalStatusLabel(t, portalStatus);
  const Icon = statusIcon(portalStatus);
  const isInProcess = portalStatusCode(portalStatus) === "in_process";

  return (
    <Badge
      variant="outline"
      className={cn("gap-1 font-medium", statusTone(portalStatus), className)}
    >
      <Icon
        className={cn("h-3 w-3 shrink-0", isInProcess && "motion-safe:animate-spin motion-reduce:animate-none")}
        aria-hidden="true"
      />
      {label}
    </Badge>
  );
}
