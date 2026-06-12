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

export const Route = createFileRoute("/portal/")({
  component: PortalOverview,
  head: () => ({
    meta: [
      { title: "Overview — SafeCampus Portal" },
      {
        name: "description",
        content: "View your report summary and recent activity.",
      },
    ],
  }),
});

function PortalOverview() {
  const { roleCode } = useAuth();

  const summaryQuery = useQuery({
    queryKey: portalQueryKeys.summary(),
    queryFn: getPortalSummary,
    enabled: hasPortalAccess(roleCode),
  });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Overview</h1>
        <p className="text-sm text-muted-foreground">
          Your reports at a glance.
        </p>
      </div>

      {summaryQuery.isLoading && <PortalSummaryCardsSkeleton />}

      {summaryQuery.isError && (
        <QueryErrorState
          message="Summary data could not be loaded."
          onRetry={() => summaryQuery.refetch()}
        />
      )}

      {summaryQuery.isSuccess && (
        <PortalSummaryCards data={summaryQuery.data} />
      )}
    </div>
  );
}
