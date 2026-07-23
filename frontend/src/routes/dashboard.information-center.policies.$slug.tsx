import { createFileRoute } from "@tanstack/react-router";

import { ReporterArticleDetailPage } from "@/components/content/reporter-article-detail-page";

export const Route = createFileRoute("/dashboard/information-center/policies/$slug")({
  component: Page,
});

function Page() {
  return <ReporterArticleDetailPage slug={Route.useParams().slug} section="policy" area="dashboard" />;
}
