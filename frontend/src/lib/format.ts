/**
 * Shared formatting utilities for the SILAPPKASAL frontend.
 *
 * Previously duplicated across dashboard.reports.index.tsx and
 * dashboard.reports.$id.tsx — now a single source of truth.
 */

/**
 * Converts a snake_case or kebab-case API string into a human-readable label.
 *
 * @example label("under_review")  // "Under Review"
 * @example label("need-info")     // "Need Info"
 */
export function label(value: string): string {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

/**
 * Formats an ISO date string into a locale-aware display string.
 * Returns a dash for null/undefined values.
 */
export function formatDate(value: string | null | undefined): string {
  return value ? new Date(value).toLocaleString() : "—";
}
