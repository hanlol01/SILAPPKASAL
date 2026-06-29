import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

interface TableSkeletonProps {
  rows?: number;
  columns?: number;
  className?: string;
}

/**
 * Generic table skeleton. Renders a header row and `rows` body rows,
 * each with `columns` skeleton cells. Use this for admin tables instead
 * of hand-rolled `Array.from({ length: n })` patterns (DSC-SKL-02).
 */
export function TableSkeleton({ rows = 5, columns = 4, className }: TableSkeletonProps) {
  return (
    <div className={cn("overflow-hidden rounded-md border", className)} aria-busy="true" aria-live="polite">
      <table className="w-full text-sm">
        <thead className="bg-muted/50">
          <tr>
            {Array.from({ length: columns }).map((_, index) => (
              <th key={`th-${index}`} className="p-3 text-left">
                <Skeleton className="h-3 w-20" />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: rows }).map((_, rowIndex) => (
            <tr key={`tr-${rowIndex}`} className="border-t">
              {Array.from({ length: columns }).map((_, colIndex) => (
                <td key={`td-${rowIndex}-${colIndex}`} className="p-3">
                  <Skeleton className="h-4 w-full max-w-32" />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

interface CardListSkeletonProps {
  count?: number;
  className?: string;
}

/**
 * Generic card-list skeleton. Renders `count` placeholder cards with an
 * icon spot, two text lines, and a trailing action chip. Used by
 * dashboard sections that show "recent items" lists (DSC-SKL-02).
 */
export function CardListSkeleton({ count = 3, className }: CardListSkeletonProps) {
  return (
    <div className={cn("space-y-2", className)} aria-busy="true" aria-live="polite">
      {Array.from({ length: count }).map((_, index) => (
        <div key={index} className="flex items-center gap-3 rounded-lg border p-3">
          <Skeleton className="h-9 w-9 rounded-md" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-3 w-64" />
          </div>
          <Skeleton className="h-8 w-20" />
        </div>
      ))}
    </div>
  );
}
