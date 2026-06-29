import { cn } from "@/lib/utils";

/**
 * Neutral, theme-aware loading placeholder.
 *
 * Uses `bg-muted` so the pulse reads as "loading" rather than
 * "active/primary" (DSC-SKL-03). Animation is automatically suppressed
 * when the user prefers reduced motion via the global guard in
 * `styles.css`.
 */
function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("animate-pulse rounded-md bg-muted", className)} {...props} />;
}

export { Skeleton };
