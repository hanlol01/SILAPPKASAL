import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { PAGE_SIZE_OPTIONS, paginationRange, type PaginationLike } from "@/lib/list-controls";

interface ListPaginationProps {
  meta: PaginationLike | undefined | null;
  page: number;
  pageSize: number;
  onPageChange: (page: number) => void;
  onPageSizeChange: (size: number) => void;
  isFetching?: boolean;
  className?: string;
  hidePageSize?: boolean;
}

/**
 * Shared pagination control for dashboard list pages.
 *
 * Visual language:
 * - Left:  "Showing X-Y of Z" range copy (or empty when no data).
 * - Right: page-size select + prev/next buttons + "Page A of B".
 *
 * Behavior:
 * - Disables prev on first page and next on last page.
 * - Disables controls while fetching to prevent racing pages.
 * - Caller owns state; this component is presentational.
 */
export function ListPagination({
  meta,
  page,
  pageSize,
  onPageChange,
  onPageSizeChange,
  isFetching,
  className,
  hidePageSize = false,
}: ListPaginationProps) {
  const { t } = useTranslation(["dashboard"]);
  const lastPage = meta?.last_page ?? 1;
  const total = meta?.total ?? 0;
  const range = paginationRange(meta);
  const canPrev = page > 1 && !isFetching;
  const canNext = page < lastPage && !isFetching;

  return (
    <div
      className={cn(
        "flex flex-col gap-3 border-t pt-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between",
        className,
      )}
    >
      <div className="min-h-[1.25rem]">
        {range
          ? t("dashboard:pagination.rangeOf", { from: range.from, to: range.to, total })
          : total > 0
            ? t("dashboard:pagination.totalOnly", { total })
            : ""}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {!hidePageSize && (
          <label className="flex items-center gap-2">
            <span className="text-xs uppercase tracking-wide">
              {t("dashboard:pagination.perPage")}
            </span>
            <Select
              value={String(pageSize)}
              onValueChange={(value) => onPageSizeChange(Number(value))}
              disabled={isFetching}
            >
              <SelectTrigger className="h-8 w-[80px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PAGE_SIZE_OPTIONS.map((option) => (
                  <SelectItem key={option} value={String(option)}>
                    {option}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </label>
        )}
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            onClick={() => onPageChange(page - 1)}
            disabled={!canPrev}
            aria-label={t("dashboard:pagination.previous")}
          >
            <ChevronLeft className="h-4 w-4" />
            <span className="sr-only sm:not-sr-only sm:ml-1">
              {t("dashboard:pagination.previous")}
            </span>
          </Button>
          <span className="px-2 text-xs">
            {t("dashboard:pagination.pageOf", {
              current: meta?.current_page ?? page,
              last: Math.max(1, lastPage),
            })}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => onPageChange(page + 1)}
            disabled={!canNext}
            aria-label={t("dashboard:pagination.next")}
          >
            <span className="sr-only sm:not-sr-only sm:mr-1">{t("dashboard:pagination.next")}</span>
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  );
}
