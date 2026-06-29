import { X } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";

interface FilterResetButtonProps {
  /** Whether at least one filter is currently active. The button is rendered only when true. */
  active: boolean;
  /** Reset handler. Should restore filters to project defaults AND return to page 1. */
  onReset: () => void;
  className?: string;
}

/**
 * Compact reset-filters affordance shown next to the filter controls.
 *
 * Visibility rule:
 * - Hidden when no filters are active (avoids visual noise on the empty default state).
 * - Visible when any filter differs from project defaults.
 *
 * Callers own the active-state computation so the semantics of "active" stay
 * page-specific (some pages have search-only filters, others have selects).
 */
export function FilterResetButton({ active, onReset, className }: FilterResetButtonProps) {
  const { t } = useTranslation(["dashboard"]);
  if (!active) return null;
  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      onClick={onReset}
      className={className}
    >
      <X className="mr-1 h-4 w-4" />
      {t("dashboard:filters.reset")}
    </Button>
  );
}
