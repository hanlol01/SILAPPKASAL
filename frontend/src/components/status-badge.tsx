import type { CaseStatus } from "@/types";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { formatCaseStatus } from "@/lib/format-labels";
import { useTranslation } from "react-i18next";

const styles: Record<CaseStatus, string> = {
  received: "bg-info/15 text-info border-info/30",
  verification: "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning",
  investigation: "bg-primary/15 text-primary border-primary/30 dark:text-primary",
  mediation: "bg-accent/30 text-accent-foreground border-accent/40",
  resolved: "bg-success/15 text-success border-success/30",
  closed: "bg-muted text-muted-foreground border-border",
};

export function StatusBadge({ status, className }: { status: CaseStatus; className?: string }) {
  const { t } = useTranslation(["dashboard"]);

  return (
    <Badge variant="outline" className={cn("font-medium capitalize", styles[status], className)}>
      {formatCaseStatus(t, status)}
    </Badge>
  );
}
