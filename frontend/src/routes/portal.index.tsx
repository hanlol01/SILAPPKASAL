import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import {
  PortalSummaryCards,
  PortalSummaryCardsSkeleton,
} from "@/components/portal/portal-summary-cards";
import { QueryErrorState } from "@/components/query-state";
import { portalQueryKeys, getPortalSummary } from "@/lib/portal-api";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { useTranslation } from "react-i18next";

export const Route = createFileRoute("/portal/")({
  component: PortalOverview,
  head: () => ({
    meta: [
      { title: "Overview â€” SILAPPKASAL Portal" },
      {
        name: "description",
        content: "View your report summary and recent activity.",
      },
    ],
  }),
});

function PortalOverview() {
  const { t } = useTranslation(["portal"]);
  const { roleCode } = useAuth();

  const summaryQuery = useQuery({
    queryKey: portalQueryKeys.summary(),
    queryFn: getPortalSummary,
    enabled: hasPortalAccess(roleCode),
  });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("overview")}</h1>
        <p className="text-sm text-muted-foreground">
          {t("overviewSubtitle")}
        </p>
      </div>

      {summaryQuery.isLoading && <PortalSummaryCardsSkeleton />}

      {summaryQuery.isError && (
        <QueryErrorState
          message={t("summaryLoadError")}
          onRetry={() => summaryQuery.refetch()}
        />
      )}

      {summaryQuery.isSuccess && (
        <PortalSummaryCards data={summaryQuery.data} />
      )}
    </div>
  );
}
