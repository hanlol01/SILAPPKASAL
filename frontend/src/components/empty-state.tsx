import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description: string;
  action?: ReactNode;
  className?: string;
}

/**
 * Shared empty state component for admin lists and portal pages.
 *
 * Supports two flavors at every level of density:
 * - Filtered empty: "No results match the current filter" + suggestion to clear filters
 * - Truly empty: domain-specific copy from i18n
 *
 * Variants:
 * - `<EmptyState>` (default): dashed bordered card with circular icon badge.
 *   Use for full-page or full-table empty states.
 * - `<EmptyState.Inline>`: tighter padding, smaller icon, no circular badge.
 *   Use inside detail-page sections that already sit inside a Card.
 * - `<EmptyState.Chart>`: centered text in a fixed-height container.
 *   Use inside chart slots that need to preserve the chart geometry.
 *
 * All variants share the same prop API for consistency.
 */
function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed px-4 py-16 text-center",
        className,
      )}
    >
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
        <Icon className="h-6 w-6 text-muted-foreground" aria-hidden="true" />
      </div>
      <div>
        <p className="text-sm font-medium">{title}</p>
        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      </div>
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}

function EmptyStateInline({ icon: Icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-4 py-8 text-center",
        className,
      )}
    >
      <Icon className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
      <div>
        <p className="text-sm font-medium">{title}</p>
        <p className="mt-1 text-xs text-muted-foreground">{description}</p>
      </div>
      {action && <div className="mt-1">{action}</div>}
    </div>
  );
}

function EmptyStateChart({ icon: Icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        "flex h-full min-h-[12rem] flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-4 py-6 text-center",
        className,
      )}
    >
      <Icon className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
      <div>
        <p className="text-sm font-medium">{title}</p>
        <p className="mt-1 text-xs text-muted-foreground">{description}</p>
      </div>
      {action && <div className="mt-1">{action}</div>}
    </div>
  );
}

EmptyState.Inline = EmptyStateInline;
EmptyState.Chart = EmptyStateChart;

export { EmptyState };
