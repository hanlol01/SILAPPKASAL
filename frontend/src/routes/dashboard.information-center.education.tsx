import { createFileRoute, Outlet, useRouterState } from "@tanstack/react-router";

import { ReporterArticleListPage } from "@/components/content/reporter-article-list-page";

export const Route = createFileRoute("/dashboard/information-center/education")({
  component: EducationRoute,
});

function EducationRoute() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  return pathname === "/dashboard/information-center/education" ? (
    <ReporterArticleListPage section="education" area="dashboard" />
  ) : <Outlet />;
}
