import { useQuery } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { ArrowLeft, MessageCircleHeart } from "lucide-react";
import { useTranslation } from "react-i18next";

import { DashboardInformationBreadcrumb } from "@/components/content/dashboard-information-breadcrumb";
import { DashboardInformationManagementCta } from "@/components/content/dashboard-information-management-cta";
import { PublishedConsultationCard } from "@/components/content/published-consultation-card";
import { ReporterInformationBreadcrumb } from "@/components/content/reporter-information-breadcrumb";
import { ReporterInformationCta } from "@/components/content/reporter-information-cta";
import { EmptyState } from "@/components/empty-state";
import { QueryErrorState } from "@/components/query-state";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { getPublishedConsultation, publishedContentKeys } from "@/lib/published-content-api";

export function InformationConsultationPage({ area }: { area: "portal" | "dashboard" }) {
  const { t } = useTranslation("informationCenter");
  const { user } = useAuth();
  const query = useQuery({ queryKey: publishedContentKeys.consultation(user?.id), queryFn: ({ signal }) => getPublishedConsultation(signal), staleTime: 2 * 60 * 1000 });
  const home = area === "portal" ? "/portal/information-center" : "/dashboard/information-center";
  const title = t("sections.consultation.title");

  return <div className="mx-auto w-full max-w-7xl space-y-7">
    {area === "portal" ? <ReporterInformationBreadcrumb current={title} /> : <DashboardInformationBreadcrumb current={title} />}
    <Button asChild variant="ghost" className="min-h-11"><Link to={home}><ArrowLeft className="h-4 w-4" />Kembali ke Information Center</Link></Button>
    <header className="rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-accent/10 p-6 sm:p-8"><p className="text-sm font-medium text-primary">{t("eyebrow")}</p><h1 className="mt-2 text-3xl font-semibold">{title}</h1><p className="mt-3 max-w-2xl text-muted-foreground">{t("views.consultation.description")}</p></header>
    {query.isLoading ? <div className="grid gap-5 lg:grid-cols-2"><Skeleton className="h-96 rounded-2xl" /><Skeleton className="h-96 rounded-2xl" /></div> : query.isError ? <QueryErrorState message={t("errors.consultation")} onRetry={() => void query.refetch()} /> : !(query.data?.length) ? <EmptyState icon={MessageCircleHeart} title={t("empty.consultationTitle")} description={t("empty.consultationDescription")} /> : <div className={query.data.length > 1 ? "grid gap-5 lg:grid-cols-2" : "grid gap-5"}>{query.data.map((item) => <PublishedConsultationCard key={item.public_id} item={item} />)}</div>}
    {area === "portal" ? <ReporterInformationCta /> : <DashboardInformationManagementCta />}
  </div>;
}
