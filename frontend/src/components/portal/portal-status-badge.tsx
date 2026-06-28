import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { useTranslation } from "react-i18next";
import {
  Clock,
  Eye,
  Loader2,
  CheckCircle2,
  HelpCircle,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

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
 * Maps known portal-safe display labels to leading icons.
 * Unknown labels get a generic HelpCircle icon.
 */
function statusIcon(portalStatus: string): LucideIcon {
  switch (portalStatus.toLowerCase()) {
    case "submitted":
      return Clock;
    case "under review":
      return Eye;
    case "in process":
      return Loader2;
    case "completed":
      return CheckCircle2;
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
  const label = t(`portal:${portalStatus}`, { defaultValue: portalStatus });
  const Icon = statusIcon(portalStatus);

  return (
    <Badge
      variant="outline"
      className={cn("gap-1 font-medium", statusTone(portalStatus), className)}
    >
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {label}
    </Badge>
  );
}
