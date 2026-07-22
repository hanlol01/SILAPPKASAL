import { createFileRoute, Outlet, useRouterState } from "@tanstack/react-router";
import { ReporterArticleListPage } from "@/components/content/reporter-article-list-page";

export const Route = createFileRoute("/portal/information-center/education")({
  component: EducationRoute,
});

function EducationRoute() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  return pathname === "/portal/information-center/education" ? (
    <ReporterArticleListPage section="education" />
  ) : (
    <Outlet />
  );
}
