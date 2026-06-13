import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import { FileText, Search, Inbox } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { QueryErrorState } from "@/components/query-state";
import { PortalReportCard } from "@/components/portal/portal-report-card";
import { portalQueryKeys, getPortalReports } from "@/lib/portal-api";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { useTranslation } from "react-i18next";

export const Route = createFileRoute("/portal/reports/")({
  component: MyReportsPage,
  head: () => ({
    meta: [
      { title: "My Reports — SafeCampus Portal" },
      {
        name: "description",
        content: "View and track all your submitted reports.",
      },
    ],
  }),
});

function MyReportsPage() {
  const { t } = useTranslation(["portal"]);
  const { roleCode } = useAuth();
  const [q, setQ] = useState("");

  const reportsQuery = useQuery({
    queryKey: portalQueryKeys.reports(),
    queryFn: () => getPortalReports(),
    enabled: hasPortalAccess(roleCode),
  });

  const filtered = useMemo(() => {
    const reports = reportsQuery.data?.data ?? [];
    if (!q) return reports;
    const needle = q.toLowerCase();
    return reports.filter((report) => {
      const haystack =
        `${report.registration_number} ${report.portal_status} ${report.category ?? ""} ${report.report_type}`.toLowerCase();
      return haystack.includes(needle);
    });
  }, [reportsQuery.data, q]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("myReports")}</h1>
        <p className="text-sm text-muted-foreground">
          {t("myReportsSubtitle")}
        </p>
      </div>

      {/* Search */}
      <div className="relative max-w-md">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          placeholder={t("searchPlaceholder")}
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="pl-9"
        />
      </div>

      {/* Loading */}
      {reportsQuery.isLoading && (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Card key={i}>
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
        </div>
      )}

      {/* Error */}
      {reportsQuery.isError && (
        <QueryErrorState
          message={t("reportsLoadError")}
          onRetry={() => reportsQuery.refetch()}
        />
      )}

      {/* Success */}
      {reportsQuery.isSuccess && (
        <>
          {filtered.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                {q ? (
                  <>
                    <Search className="h-8 w-8 text-muted-foreground/50" />
                    <div>
                      <p className="text-sm font-medium">{t("noMatchingReports")}</p>
                      <p className="text-sm text-muted-foreground">
                        {t("tryDifferentSearch")}
                      </p>
                    </div>
                  </>
                ) : (
                  <>
                    <Inbox className="h-8 w-8 text-muted-foreground/50" />
                    <div>
                      <p className="text-sm font-medium">{t("noReportsYet")}</p>
                      <p className="text-sm text-muted-foreground">
                        {t("noReportsSubmitted")}
                      </p>
                    </div>
                  </>
                )}
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {filtered.map((report) => (
                <PortalReportCard
                  key={report.registration_number}
                  report={report}
                />
              ))}
            </div>
          )}

          {/* Count */}
          {reportsQuery.data.meta && (
            <p className="text-sm text-muted-foreground">
              {t("showingReports", {
                count: filtered.length,
                total: reportsQuery.data.meta.total,
              })}
            </p>
          )}
        </>
      )}
    </div>
  );
}
