/**
 * Shared helpers for dashboard list pages.
 *
 * Standardizes pagination defaults and filter-reset behavior so every list
 * page exposes the same productivity baseline (PB-08, PB-09).
 *
 * Page size: 15 is the project-standard production page size, balancing
 * scan-ability with request cost. Backend pagination meta drives navigation.
 */

export const DEFAULT_PAGE_SIZE = 15;

export const PAGE_SIZE_OPTIONS = [10, 15, 25, 50] as const;

export type PageSize = (typeof PAGE_SIZE_OPTIONS)[number];

export interface PaginationLike {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

/**
 * Clamp the requested page within [1, lastPage].
 * Falls back to 1 when lastPage is missing or invalid.
 */
export function clampPage(page: number, lastPage: number | undefined | null): number {
  if (!Number.isFinite(page) || page < 1) return 1;
  if (!lastPage || lastPage < 1) return 1;
  return Math.min(Math.max(1, Math.floor(page)), lastPage);
}

/**
 * Normalize a possibly-undefined page-size value to a known option.
 */
export function normalizePageSize(value: number | undefined | null): PageSize {
  if (!value) return DEFAULT_PAGE_SIZE;
  const match = PAGE_SIZE_OPTIONS.find((option) => option === value);
  return match ?? DEFAULT_PAGE_SIZE;
}

/**
 * Compute the inclusive [from, to] range a page covers for "Showing X-Y of Z" copy.
 * Returns nulls when the meta is empty or invalid.
 */
export function paginationRange(
  meta: PaginationLike | undefined | null,
): { from: number; to: number } | null {
  if (!meta || meta.total <= 0) return null;
  const from = (meta.current_page - 1) * meta.per_page + 1;
  const to = Math.min(meta.current_page * meta.per_page, meta.total);
  if (from < 1 || to < from) return null;
  return { from, to };
}
