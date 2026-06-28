import type { CaseStatus } from "@/types";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { formatCaseStatus } from "@/lib/format-labels";
import { useTranslation } from "react-i18next";
import {
  Inbox,
  Search,
  FileSearch,
  Scale,
  CheckCircle2,
  Lock,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

const styles: Record<CaseStatus, string> = {
  received: "bg-info/15 text-info border-info/30",
  verification: "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning",
  investigation: "bg-primary/15 text-primary border-primary/30 dark:text-primary",
  mediation: "bg-accent/30 text-accent-foreground border-accent/40",
  resolved: "bg-success/15 text-success border-success/30",
  closed: "bg-muted text-muted-foreground border-border",
};

const icons: Record<CaseStatus, LucideIcon> = {
  received: Inbox,
  verification: Search,
  investigation: FileSearch,
  mediation: Scale,
  resolved: CheckCircle2,
  closed: Lock,
};

export function StatusBadge({ status, className }: { status: CaseStatus; className?: string }) {
  const { t } = useTranslation(["dashboard"]);
  const Icon = icons[status];

  return (
    <Badge variant="outline" className={cn("gap-1 font-medium capitalize", styles[status], className)}>
      <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
      {formatCaseStatus(t, status)}
    </Badge>
  );
}
