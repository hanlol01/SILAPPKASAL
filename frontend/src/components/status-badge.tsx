import type { CaseStatus } from "@/types";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

const styles: Record<CaseStatus, string> = {
  received: "bg-info/15 text-info border-info/30",
  verification: "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning",
  investigation: "bg-primary/15 text-primary border-primary/30 dark:text-primary",
  mediation: "bg-accent/30 text-accent-foreground border-accent/40",
  resolved: "bg-success/15 text-success border-success/30",
  closed: "bg-muted text-muted-foreground border-border",
};

const labels: Record<CaseStatus, string> = {
  received: "Received",
  verification: "Verification",
  investigation: "Investigation",
  mediation: "Mediation",
  resolved: "Resolved",
  closed: "Closed",
};

export function StatusBadge({ status, className }: { status: CaseStatus; className?: string }) {
  return (
    <Badge variant="outline" className={cn("font-medium capitalize", styles[status], className)}>
      {labels[status]}
    </Badge>
  );
}
