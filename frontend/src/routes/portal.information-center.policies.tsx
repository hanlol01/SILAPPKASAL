import { createFileRoute, Outlet, useRouterState } from "@tanstack/react-router";
import { ReporterArticleListPage } from "@/components/content/reporter-article-list-page";

export const Route = createFileRoute("/portal/information-center/policies")({
  component: PoliciesRoute,
});

function PoliciesRoute() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  return pathname === "/portal/information-center/policies" ? (
    <ReporterArticleListPage section="policy" />
  ) : (
    <Outlet />
  );
}
