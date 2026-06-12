import { useNavigate } from "@tanstack/react-router";
import { FileText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { PortalStatusBadge } from "@/components/portal/portal-status-badge";
import { label as humanize, formatDate } from "@/lib/format";
import type { PortalReport } from "@/lib/portal-types";

interface PortalReportCardProps {
  report: PortalReport;
}

/**
 * A single report card for the "My Reports" list.
 *
 * Shows only reporter-safe fields — no internal IDs, no workflow details.
 * Uses registration_number as the visible identifier and navigation key.
 */
export function PortalReportCard({ report }: PortalReportCardProps) {
  const navigate = useNavigate();

  return (
    <Card className="transition-colors hover:bg-muted/40">
      <CardContent className="flex items-center gap-4 p-4">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <FileText className="h-5 w-5" />
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-mono text-sm font-medium">
              {report.registration_number}
            </span>
            <PortalStatusBadge
              portalStatus={report.portal_status}
            />
          </div>
          <div className="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
            <span>{humanize(report.report_type)}</span>
            {report.category && <span>{report.category}</span>}
            <span>Submitted {formatDate(report.submitted_at)}</span>
          </div>
        </div>

        <Button
          variant="ghost"
          size="sm"
          className="shrink-0"
          onClick={() =>
            navigate({
              to: "/portal/reports/$registrationNumber" as "/",
              params: { registrationNumber: report.registration_number },
            })
          }
        >
          View
        </Button>
      </CardContent>
    </Card>
  );
}
