import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { useTranslation } from "react-i18next";
import { Eye, Lock, ShieldCheck } from "lucide-react";
import type { LucideIcon } from "lucide-react";

/**
 * Reporter-safe badge for report type (open / confidential / anonymous).
 *
 * Mirrors the PortalStatusBadge pattern: icon + localized label + semantic color.
 * Privacy-sensitive types use lock / shield icons for visual reinforcement.
 */

interface ReportTypeVisual {
  tone: string;
  icon: LucideIcon;
}

const typeVisuals: Record<string, ReportTypeVisual> = {
  open: {
    tone: "bg-info/15 text-info border-info/30",
    icon: Eye,
  },
  confidential: {
    tone: "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning",
    icon: ShieldCheck,
  },
  anonymous: {
    tone: "bg-muted text-muted-foreground border-border",
    icon: Lock,
  },
};

const defaultVisual: ReportTypeVisual = {
  tone: "bg-muted text-muted-foreground border-border",
  icon: Eye,
};

function normalized(value: string): string {
  return value.trim().toLowerCase().replace(/[-\s]+/g, "_");
}

interface PortalReportTypeBadgeProps {
  reportType: string;
  className?: string;
}

export function PortalReportTypeBadge({
  reportType,
  className,
}: PortalReportTypeBadgeProps) {
  const { t } = useTranslation(["portal"]);
  const key = normalized(reportType);
  const visual = typeVisuals[key] ?? defaultVisual;
  const Icon = visual.icon;
  const label = t(`portal:reportTypes.${key}`, { defaultValue: reportType });

  return (
    <Badge
      variant="outline"
      className={cn("gap-1 font-medium", visual.tone, className)}
    >
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {label}
    </Badge>
  );
}
