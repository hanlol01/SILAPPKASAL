import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  BookOpen,
  CircleHelp,
  ExternalLink,
  Info,
  Landmark,
  MessageCircleHeart,
  PlusCircle,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import {
  PortalSummaryCards,
  PortalSummaryCardsSkeleton,
} from "@/components/portal/portal-summary-cards";
import { PortalReportCard } from "@/components/portal/portal-report-card";
import { QueryErrorState } from "@/components/query-state";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { portalQueryKeys, getPortalSummary, getPortalReports } from "@/lib/portal-api";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { FeaturedArticleSection } from "@/components/content/featured-article-section";
import { canReadPublishedContent } from "@/lib/published-content-access";

export const Route = createFileRoute("/portal/")({
  component: PortalOverview,
  head: () => ({
    meta: [
      { title: "Beranda Portal - SILAPPKASAL" },
      {
        name: "description",
        content: "Ringkasan laporan dan aktivitas terakhir Anda.",
      },
    ],
  }),
});

function PortalOverview() {
  const { t } = useTranslation(["portal", "common", "informationCenter"]);
  const { roleCode, user } = useAuth();
  const portalAccessible = hasPortalAccess(roleCode);
  const publishedContentAccessible = canReadPublishedContent(user);

  const summaryQuery = useQuery({
    queryKey: portalQueryKeys.summary(),
    queryFn: getPortalSummary,
    enabled: portalAccessible,
  });

  const reportsQuery = useQuery({
    queryKey: portalQueryKeys.reports(),
    queryFn: () => getPortalReports(),
    enabled: portalAccessible,
  });

  const recentReports = (reportsQuery.data?.data ?? []).slice(0, 3);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("overview")}</h1>
        <p className="text-sm text-muted-foreground">{t("overviewSubtitle")}</p>
      </div>

      {summaryQuery.isLoading && <PortalSummaryCardsSkeleton />}

      {summaryQuery.isError && (
        <QueryErrorState message={t("summaryLoadError")} onRetry={() => summaryQuery.refetch()} />
      )}

      {summaryQuery.isSuccess && <PortalSummaryCards data={summaryQuery.data} />}

      <Card>
        <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <PlusCircle className="h-5 w-5" />
            </div>
            <div>
              <p className="font-medium">{t("overviewCtaTitle")}</p>
              <p className="mt-1 text-sm text-muted-foreground">{t("overviewCtaSubtitle")}</p>
            </div>
          </div>
          <Button asChild className="gap-1.5 sm:ml-4">
            <Link to="/portal/reports/new">
              {t("overviewCtaAction")} <ArrowRight className="h-4 w-4" />
            </Link>
          </Button>
        </CardContent>
      </Card>

      <CollapsibleDataCard
        title={t("recentReports")}
        description={t("recentReportsSubtitle")}
        expandLabel={t("collapsible.expand")}
        collapseLabel={t("collapsible.collapse")}
        contentClassName="space-y-3"
        headerAction={
          reportsQuery.isSuccess && recentReports.length > 0 ? (
            <Button asChild variant="outline" size="sm" className="gap-1.5">
              <Link to="/portal/reports">
                <ExternalLink className="h-3.5 w-3.5" />
                {t("viewAllReports")}
              </Link>
            </Button>
          ) : undefined
        }
      >
        {reportsQuery.isLoading && (
          <>
            {Array.from({ length: 3 }).map((_, index) => (
              <Card key={index}>
                <CardContent className="flex items-center gap-4 p-4">
                  <Skeleton className="h-10 w-10 rounded-lg" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-3 w-64" />
                  </div>
                  <Skeleton className="h-8 w-14" />
                </CardContent>
              </Card>
            ))}
          </>
        )}
        {reportsQuery.isError && (
          <QueryErrorState message={t("reportsLoadError")} onRetry={() => reportsQuery.refetch()} />
        )}
        {reportsQuery.isSuccess && recentReports.length === 0 && (
          <p className="text-sm text-muted-foreground">{t("noRecentReports")}</p>
        )}
        {reportsQuery.isSuccess && recentReports.length > 0 && (
          <div className="space-y-3">
            {recentReports.map((report) => (
              <PortalReportCard key={report.registration_number} report={report} />
            ))}
          </div>
        )}
      </CollapsibleDataCard>

      {publishedContentAccessible && (
        <section aria-labelledby="portal-information-shortcuts" className="space-y-4">
          <div>
            <h2 id="portal-information-shortcuts" className="text-xl font-semibold">
              {t("informationCenter:dashboard.title")}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {t("informationCenter:dashboard.description")}
            </p>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <InformationShortcut
              icon={BookOpen}
              title={t("informationCenter:sections.education.title")}
              description={t("informationCenter:sections.education.description")}
              search={{ section: "education" }}
            />
            <InformationShortcut
              icon={Landmark}
              title={t("informationCenter:sections.policy.title")}
              description={t("informationCenter:sections.policy.description")}
              search={{ section: "policy" }}
            />
            <InformationShortcut
              icon={CircleHelp}
              title={t("informationCenter:sections.faq.title")}
              description={t("informationCenter:sections.faq.description")}
              search={{ view: "faq" }}
            />
            <InformationShortcut
              icon={MessageCircleHeart}
              title={t("informationCenter:sections.consultation.title")}
              description={t("informationCenter:sections.consultation.description")}
              search={{ view: "consultation" }}
            />
          </div>
        </section>
      )}

      {publishedContentAccessible && <FeaturedArticleSection compact />}

      <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
        <Info className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
        <p>{t("overviewSlaNotice")}</p>
      </div>
    </div>
  );
}

function InformationShortcut({
  icon: Icon,
  title,
  description,
  search,
}: {
  icon: typeof BookOpen;
  title: string;
  description: string;
  search: { view?: "faq" | "consultation"; section?: "education" | "policy" };
}) {
  return (
    <Link
      to="/dashboard/information-center"
      search={search}
      className="group min-h-11 rounded-2xl border bg-card p-4 text-card-foreground shadow-sm transition-[border-color,box-shadow,transform] hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 motion-reduce:transform-none motion-reduce:transition-none"
    >
      <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
        <Icon className="h-5 w-5" aria-hidden="true" />
      </span>
      <span className="mt-3 block font-semibold">{title}</span>
      <span className="mt-1 block text-sm leading-5 text-muted-foreground">{description}</span>
    </Link>
  );
}
