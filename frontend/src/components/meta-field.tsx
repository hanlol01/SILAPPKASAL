import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

interface MetaFieldProps {
  /** Small uppercase label sitting above the value. */
  label: string;
  /** Value content. Strings, JSX nodes, and components are all welcome. */
  children: ReactNode;
  /** Override the wrapper's className. Use sparingly. */
  className?: string;
}

/**
 * Shared read-only key/value pair used across detail pages.
 *
 * Codifies the typography ladder for inline metadata fields:
 *   - label: text-xs uppercase tracking-wide text-muted-foreground
 *   - value: text-sm whitespace-pre-wrap
 *
 * Promote local `Field`, `MobileField`, `InfoRow`, etc. components to
 * this one so detail pages share the same density (DSC-CMP-04).
 */
export function MetaField({ label, children, className }: MetaFieldProps) {
  return (
    <div className={className}>
      <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
      <div className="mt-1 whitespace-pre-wrap text-sm">{children}</div>
    </div>
  );
}

/**
 * Compact variant for dense table cards and mobile fallbacks.
 */
export function MetaFieldCompact({ label, children, className }: MetaFieldProps) {
  return (
    <div className={className}>
      <div className={cn("text-[11px] uppercase tracking-wide text-muted-foreground")}>{label}</div>
      <div className="mt-0.5 text-sm">{children}</div>
    </div>
  );
}
