import { createFileRoute } from "@tanstack/react-router";
import { ReporterArticleDetailPage } from "@/components/content/reporter-article-detail-page";
export const Route = createFileRoute("/portal/information-center/policies/$slug")({ component: Page });
function Page() { return <ReporterArticleDetailPage slug={Route.useParams().slug} section="policy" />; }
