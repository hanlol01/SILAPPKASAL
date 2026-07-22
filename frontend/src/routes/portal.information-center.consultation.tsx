import { useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { MessageCircleHeart } from "lucide-react";
import { useTranslation } from "react-i18next";
import { PublishedConsultationCard } from "@/components/content/published-consultation-card";
import { ReporterInformationBreadcrumb } from "@/components/content/reporter-information-breadcrumb";
import { ReporterInformationCta } from "@/components/content/reporter-information-cta";
import { EmptyState } from "@/components/empty-state";
import { QueryErrorState } from "@/components/query-state";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { getPublishedConsultation, publishedContentKeys } from "@/lib/published-content-api";

export const Route = createFileRoute("/portal/information-center/consultation")({ component: ReporterConsultationPage });
function ReporterConsultationPage() {
  const { t } = useTranslation("informationCenter"); const { user } = useAuth();
  const query = useQuery({ queryKey: publishedContentKeys.consultation(user?.id), queryFn: ({ signal }) => getPublishedConsultation(signal), staleTime: 2 * 60 * 1000 });
  return <div className="mx-auto w-full max-w-7xl space-y-7"><ReporterInformationBreadcrumb current={t("sections.consultation.title")} />
    <header className="rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-accent/10 p-6 sm:p-8"><p className="text-sm font-medium text-primary">{t("eyebrow")}</p><h1 className="mt-2 text-3xl font-semibold">{t("sections.consultation.title")}</h1><p className="mt-3 max-w-2xl text-muted-foreground">{t("views.consultation.description")}</p></header>
    {query.isLoading ? <div className="grid gap-5 lg:grid-cols-2"><Skeleton className="h-96 rounded-2xl" /><Skeleton className="h-96 rounded-2xl" /></div> : query.isError ? <QueryErrorState message={t("errors.consultation")} onRetry={() => void query.refetch()} /> : !(query.data?.length) ? <EmptyState icon={MessageCircleHeart} title={t("empty.consultationTitle")} description={t("empty.consultationDescription")} /> : <div className={query.data.length > 1 ? "grid gap-5 lg:grid-cols-2" : "grid gap-5"}>{query.data.map((item) => <PublishedConsultationCard key={item.public_id} item={item} />)}</div>}
    <ReporterInformationCta />
  </div>;
}
