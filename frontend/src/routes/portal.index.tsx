import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Info, PlusCircle } from "lucide-react";
import { useTranslation } from "react-i18next";
import {
  PortalSummaryCards,
  PortalSummaryCardsSkeleton,
} from "@/components/portal/portal-summary-cards";
import { PortalReportCard } from "@/components/portal/portal-report-card";
import { QueryErrorState } from "@/components/query-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import {
  portalQueryKeys,
  getPortalSummary,
  getPortalReports,
} from "@/lib/portal-api";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";

export const Route = createFileRoute("/portal/")({
  component: PortalOverview,
  head: () => ({
    meta: [
      { title: "Beranda Portal - SILAPPKASAL" },
      {
        name: "description",
        content: "Ringkasan laporan dan aktivitas terakhir Anda.",
      },
    ],
  }),
});

function PortalOverview() {
  const { t } = useTranslation(["portal", "common"]);
  const { roleCode } = useAuth();
  const portalAccessible = hasPortalAccess(roleCode);

  const summaryQuery = useQuery({
    queryKey: portalQueryKeys.summary(),
    queryFn: getPortalSummary,
    enabled: portalAccessible,
  });

  const reportsQuery = useQuery({
    queryKey: portalQueryKeys.reports(),
    queryFn: () => getPortalReports(),
    enabled: portalAccessible,
  });

  const recentReports = (reportsQuery.data?.data ?? []).slice(0, 3);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("overview")}</h1>
        <p className="text-sm text-muted-foreground">{t("overviewSubtitle")}</p>
      </div>

      {summaryQuery.isLoading && <PortalSummaryCardsSkeleton />}

      {summaryQuery.isError && (
        <QueryErrorState
          message={t("summaryLoadError")}
          onRetry={() => summaryQuery.refetch()}
        />
      )}

      {summaryQuery.isSuccess && <PortalSummaryCards data={summaryQuery.data} />}

      <Card>
        <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <PlusCircle className="h-5 w-5" />
            </div>
            <div>
              <p className="font-medium">{t("overviewCtaTitle")}</p>
              <p className="mt-1 text-sm text-muted-foreground">{t("overviewCtaSubtitle")}</p>
            </div>
          </div>
          <Button asChild className="sm:ml-4">
            <Link to="/portal/reports/new">{t("overviewCtaAction")}</Link>
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
          <div>
            <CardTitle className="text-base">{t("recentReports")}</CardTitle>
            <CardDescription>{t("recentReportsSubtitle")}</CardDescription>
          </div>
          {reportsQuery.isSuccess && recentReports.length > 0 && (
            <Button asChild variant="ghost" size="sm">
              <Link to="/portal/reports">{t("viewAllReports")}</Link>
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-3">
          {reportsQuery.isLoading && (
            <>
              {Array.from({ length: 3 }).map((_, index) => (
                <Card key={index}>
                  <CardContent className="flex items-center gap-4 p-4">
                    <Skeleton className="h-10 w-10 rounded-lg" />
                    <div className="flex-1 space-y-2">
                      <Skeleton className="h-4 w-40" />
                      <Skeleton className="h-3 w-64" />
                    </div>
                    <Skeleton className="h-8 w-14" />
                  </CardContent>
                </Card>
              ))}
            </>
          )}
          {reportsQuery.isError && (
            <QueryErrorState
              message={t("reportsLoadError")}
              onRetry={() => reportsQuery.refetch()}
            />
          )}
          {reportsQuery.isSuccess && recentReports.length === 0 && (
            <p className="text-sm text-muted-foreground">{t("noRecentReports")}</p>
          )}
          {reportsQuery.isSuccess && recentReports.length > 0 && (
            <div className="space-y-3">
              {recentReports.map((report) => (
                <PortalReportCard key={report.registration_number} report={report} />
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
        <Info className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
        <p>{t("overviewSlaNotice")}</p>
      </div>
    </div>
  );
}
