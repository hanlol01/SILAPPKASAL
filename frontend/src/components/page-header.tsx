import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

interface PageHeaderProps {
  /** Page title rendered as an H1. */
  title: string;
  /** Optional supporting subtitle. */
  description?: string;
  /** Optional right-aligned actions slot (buttons, dropdowns, etc.). */
  actions?: ReactNode;
  className?: string;
}

/**
 * Shared page header that codifies the project's H1 ladder:
 *   - title:       text-2xl font-semibold tracking-tight
 *   - description: text-sm text-muted-foreground
 *
 * Promote local re-implementations of this pattern to this component so
 * every admin/portal surface shares the same heading rhythm (DSC-CMP-01).
 *
 * Example (list page):
 *   <PageHeader title={t("...")} description={t("...")} />
 *
 * Example with actions:
 *   <PageHeader title={t("...")} actions={<Button>...</Button>} />
 */
export function PageHeader({ title, description, actions, className }: PageHeaderProps) {
  if (actions) {
    return (
      <div className={cn("flex flex-wrap items-end justify-between gap-3", className)}>
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
        <div className="flex flex-wrap items-center gap-2">{actions}</div>
      </div>
    );
  }

  return (
    <div className={className}>
      <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
      {description && <p className="text-sm text-muted-foreground">{description}</p>}
    </div>
  );
}
