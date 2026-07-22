import { useQuery } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { ArrowLeft, BookOpen, Clock3, FileText } from "lucide-react";
import { useCallback, useState } from "react";
import { useTranslation } from "react-i18next";

import { AuthenticatedContentCover } from "@/components/content/authenticated-content-cover";
import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { PublishedArticleCard } from "@/components/content/published-article-card";
import { PublishedContentAttachment } from "@/components/content/published-content-attachment";
import { ReporterInformationBreadcrumb } from "@/components/content/reporter-information-breadcrumb";
import { ReporterInformationCta } from "@/components/content/reporter-information-cta";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import { getPublishedArticleBySlug, publishedContentKeys } from "@/lib/published-content-api";

export function ReporterArticleDetailPage({ slug, section }: { slug: string; section: "education" | "policy" }) {
  const { t, i18n } = useTranslation("informationCenter");
  const { user } = useAuth();
  const query = useQuery({ queryKey: publishedContentKeys.article(user?.id, `slug:${section}:${slug}`), queryFn: ({ signal }) => getPublishedArticleBySlug(section, slug, signal), staleTime: 2 * 60 * 1000, retry: (count, error) => !(error instanceof ApiError && error.status === 404) && count < 2 });
  const listTo = section === "education" ? "/portal/information-center/education" : "/portal/information-center/policies";
  const sectionTitle = t(`sections.${section}.title`);
  if (query.isLoading) return <div className="mx-auto max-w-4xl space-y-5"><Skeleton className="h-6 w-52" /><Skeleton className="h-80 rounded-3xl" /><Skeleton className="h-12 w-full" /><Skeleton className="h-64 w-full" /></div>;
  if (query.error instanceof ApiError && query.error.status === 404) return <div className="mx-auto max-w-2xl py-16 text-center"><BookOpen className="mx-auto h-10 w-10 text-muted-foreground" /><h1 className="mt-4 text-2xl font-semibold">{t("article.notFoundTitle")}</h1><p className="mt-2 text-muted-foreground">{t("article.notFoundDescription")}</p><Button asChild className="mt-6"><Link to={listTo}>{t("article.back")}</Link></Button></div>;
  if (query.isError || !query.data) return <QueryErrorState message={t("errors.article")} onRetry={() => void query.refetch()} />;
  const article = query.data;
  const date = new Intl.DateTimeFormat(i18n.language, { dateStyle: "long" }).format(new Date(article.published_at));
  return <article className="mx-auto w-full max-w-7xl space-y-8">
    <ReporterInformationBreadcrumb current={article.title} section={{ label: sectionTitle, to: listTo }} />
    <Button asChild variant="ghost" className="min-h-11"><Link to={listTo}><ArrowLeft className="h-4 w-4" />{t("article.backToSection", { section: sectionTitle })}</Link></Button>
    <ReporterArticleCover key={article.cover?.public_id ?? "fallback"} article={article} fallbackLabel={t("article.defaultCover", { title: article.title })} />
    <header className="mx-auto max-w-3xl border-b pb-8"><div className="flex flex-wrap gap-2"><Badge>{article.category_name ?? article.category?.name ?? sectionTitle}</Badge><Badge variant="outline">{sectionTitle}</Badge></div><h1 className="mt-5 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">{article.title}</h1>{article.excerpt && <p className="mt-4 text-lg leading-8 text-muted-foreground">{article.excerpt}</p>}<div className="mt-5 flex flex-wrap gap-4 text-sm text-muted-foreground"><span>{date}</span>{article.estimated_reading_minutes ? <span className="inline-flex items-center gap-1.5"><Clock3 className="h-4 w-4" />{t("article.readingTime", { count: article.estimated_reading_minutes })}</span> : null}</div></header>
    <div className="mx-auto max-w-3xl"><ContentDocumentPreview document={article.body ?? null} /></div>
    {article.attachments?.length ? <section className="mx-auto max-w-3xl space-y-4" aria-labelledby="portal-article-attachments"><h2 id="portal-article-attachments" className="flex items-center gap-2 text-xl font-semibold"><FileText className="h-5 w-5" />{t("attachment.title")}</h2>{article.attachments.map((attachment) => <PublishedContentAttachment key={attachment.public_id} attachment={attachment} />)}</section> : null}
    {article.related_articles?.length ? <section className="space-y-5"><h2 className="text-2xl font-semibold">{t("article.related")}</h2><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{article.related_articles.map((related) => <PublishedArticleCard key={related.public_id} article={related} portal />)}</div></section> : null}
    <ReporterInformationCta />
  </article>;
}

function ReporterArticleCover({ article, fallbackLabel }: { article: Awaited<ReturnType<typeof getPublishedArticleBySlug>>; fallbackLabel: string }) {
  const [coverUnavailable, setCoverUnavailable] = useState(false);
  const markUnavailable = useCallback(() => setCoverUnavailable(true), []);

  return <div className="relative aspect-video overflow-hidden rounded-3xl bg-primary/10">
    <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-sky-950 via-primary to-cyan-700 text-white" role="img" aria-label={fallbackLabel}>
      <div className="flex h-28 w-28 items-center justify-center rounded-3xl border border-white/30 bg-white/15 shadow-2xl backdrop-blur-sm"><BookOpen className="h-16 w-16" aria-hidden="true" /></div>
    </div>
    {article.cover && !coverUnavailable ? <AuthenticatedContentCover publicId={article.cover.public_id} alt={article.cover.alt_text ?? article.title} className="absolute inset-0 h-full w-full object-cover" onUnavailable={markUnavailable} /> : null}
  </div>;
}
