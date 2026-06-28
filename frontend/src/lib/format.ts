import { format, isValid, parseISO } from "date-fns";
import { enUS, id as idLocale } from "date-fns/locale";

/**
 * Converts a snake_case or kebab-case API string into a human-readable label.
 *
 * @example label("under_review")  // "Under Review"
 * @example label("need-info")     // "Need Info"
 */
export function label(value: string): string {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function localeFor(language?: string) {
  return language?.toLowerCase().startsWith("en") ? enUS : idLocale;
}

function parseDate(value: string | null | undefined) {
  if (!value) return null;

  const parsed = parseISO(value);
  if (isValid(parsed)) return parsed;

  const fallback = new Date(value);
  return isValid(fallback) ? fallback : null;
}

export function formatDate(value: string | null | undefined, language?: string): string {
  const date = parseDate(value);
  return date ? format(date, "dd MMM yyyy", { locale: localeFor(language) }) : "-";
}

export function formatDateTime(value: string | null | undefined, language?: string): string {
  const date = parseDate(value);
  return date ? format(date, "dd MMM yyyy HH:mm", { locale: localeFor(language) }) : "-";
}
