import type { LucideIcon } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

/**
 * Shared vertical progress timeline used by the internal case detail page
 * and the reporter portal report detail page.
 *
 * Rendering rules:
 * - One event per row: icon circle + title + timestamp + optional description.
 * - Events are expected pre-sorted (oldest first); missing stages are simply
 *   absent — callers must never fabricate future events.
 * - The most recent (last) event is visually emphasized; earlier events use
 *   the success tone to read as completed steps.
 */

export interface ProgressTimelineEvent {
  id: string;
  title: string;
  /** Pre-formatted, locale-aware timestamp text. */
  timestamp?: string | null;
  description?: string | null;
  icon: LucideIcon;
}

export function ProgressTimeline({
  events,
  className,
}: {
  events: ProgressTimelineEvent[];
  className?: string;
}) {
  return (
    <ol className={cn("m-0 list-none p-0", className)}>
      {events.map((event, index) => {
        const isLast = index === events.length - 1;
        const Icon = event.icon;

        return (
          <li key={event.id} className="relative flex gap-3 pb-6 last:pb-0">
            {!isLast && (
              <span
                aria-hidden="true"
                className="absolute left-4 top-8 h-[calc(100%-1.25rem)] w-px -translate-x-1/2 bg-border"
              />
            )}
            <span
              className={cn(
                "flex h-8 w-8 shrink-0 items-center justify-center rounded-full border",
                isLast
                  ? "border-primary/30 bg-primary/15 text-primary"
                  : "border-success/30 bg-success/15 text-success",
              )}
            >
              <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1 pt-1">
              <div className={cn("text-sm", isLast ? "font-semibold" : "font-medium")}>
                {event.title}
              </div>
              {event.timestamp && (
                <div className="mt-0.5 text-xs text-muted-foreground">{event.timestamp}</div>
              )}
              {event.description && (
                <div className="mt-1 text-xs text-muted-foreground">{event.description}</div>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}

export function ProgressTimelineSkeleton({ rows = 4 }: { rows?: number }) {
  return (
    <div className="space-y-6" aria-busy="true" aria-live="polite">
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="flex gap-3">
          <Skeleton className="h-8 w-8 rounded-full" />
          <div className="flex-1 space-y-2 pt-1">
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-3 w-28" />
          </div>
        </div>
      ))}
    </div>
  );
}
