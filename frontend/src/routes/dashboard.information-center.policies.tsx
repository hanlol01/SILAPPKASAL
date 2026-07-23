import { createFileRoute, Outlet, useRouterState } from "@tanstack/react-router";

import { ReporterArticleListPage } from "@/components/content/reporter-article-list-page";

export const Route = createFileRoute("/dashboard/information-center/policies")({
  component: PoliciesRoute,
});

function PoliciesRoute() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  return pathname === "/dashboard/information-center/policies" ? (
    <ReporterArticleListPage section="policy" area="dashboard" />
  ) : <Outlet />;
}
