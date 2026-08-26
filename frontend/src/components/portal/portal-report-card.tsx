import { Link } from "@tanstack/react-router";
import { ExternalLink, FileText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { PortalStatusBadge } from "@/components/portal/portal-status-badge";
import { PortalReportTypeBadge } from "@/components/portal/portal-report-type-badge";
import { formatDate } from "@/lib/format";
import { formatReportCategory } from "@/lib/format-labels";
import type { PortalReport } from "@/lib/portal-types";
import { useTranslation } from "react-i18next";

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
  const { t, i18n } = useTranslation(["portal", "common", "dashboard"]);

  return (
    <Card className="transition-colors hover:bg-muted/40">
      <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
        <div className="flex min-w-0 flex-1 items-start gap-3 sm:items-center sm:gap-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FileText className="h-5 w-5" aria-hidden="true" />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <span className="min-w-0 font-mono text-sm font-medium [overflow-wrap:anywhere]">
                {report.registration_number}
              </span>
              <PortalStatusBadge portalStatus={report.portal_status} />
              <PortalReportTypeBadge reportType={report.report_type} />
            </div>
            <div className="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs leading-5 text-muted-foreground">
              {report.category && <span>{formatReportCategory(t, report.category)}</span>}
              <span>
                {t("portal:submittedDate", {
                  date: formatDate(report.submitted_at, i18n.language),
                })}
              </span>
            </div>
          </div>
        </div>

        <Button size="sm" className="w-full shrink-0 gap-1.5 sm:w-auto" asChild>
          <Link
            to="/portal/reports/$registrationNumber"
            params={{ registrationNumber: report.registration_number }}
          >
            <ExternalLink className="h-3.5 w-3.5" />
            {t("common:view")}
          </Link>
        </Button>
      </CardContent>
    </Card>
  );
}
