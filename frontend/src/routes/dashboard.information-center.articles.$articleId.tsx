import { useQuery } from "@tanstack/react-query";
import { ApiError } from "@/lib/api-client";
import { createFileRoute, Link } from "@tanstack/react-router";
import { ArrowLeft, BookOpen, Clock3, FileText, MessageCircleHeart } from "lucide-react";
import { useEffect, useRef } from "react";
import { useTranslation } from "react-i18next";

import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { PublishedArticleCard } from "@/components/content/published-article-card";
import { PublishedConsultationCard } from "@/components/content/published-consultation-card";
import { PublishedContentAttachment } from "@/components/content/published-content-attachment";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import {
  getPublishedArticle,
  getPublishedConsultation,
  publishedContentKeys,
} from "@/lib/published-content-api";

export const Route = createFileRoute("/dashboard/information-center/articles/$articleId")({
  component: PublishedArticlePage,
});

function PublishedArticlePage() {
  const { articleId } = Route.useParams();
  const { t, i18n } = useTranslation("informationCenter");
  const { user } = useAuth();
  const pageHeadingRef = useRef<HTMLHeadingElement | null>(null);
  const articleQuery = useQuery({
    queryKey: publishedContentKeys.article(user?.id, articleId),
    queryFn: ({ signal }) => getPublishedArticle(articleId, signal),
    staleTime: 2 * 60 * 1000,
    retry: (count, error) => !(error instanceof ApiError && error.status === 404) && count < 2,
  });
  const consultationQuery = useQuery({
    queryKey: publishedContentKeys.consultation(user?.id),
    queryFn: ({ signal }) => getPublishedConsultation(signal),
    enabled: Boolean(articleQuery.data?.consultation_cta_public_id),
    staleTime: 2 * 60 * 1000,
  });

  useEffect(() => {
    if (!articleQuery.isLoading) pageHeadingRef.current?.focus({ preventScroll: true });
  }, [articleQuery.data?.public_id, articleQuery.isError, articleQuery.isLoading]);

  if (articleQuery.isLoading) return <ArticleSkeleton />;
  if (articleQuery.error instanceof ApiError && articleQuery.error.status === 404) {
    return (
      <div className="mx-auto max-w-2xl py-16 text-center">
        <BookOpen className="mx-auto h-10 w-10 text-muted-foreground" aria-hidden="true" />
        <h1 ref={pageHeadingRef} tabIndex={-1} className="mt-4 text-2xl font-semibold outline-none">
          {t("article.notFoundTitle")}
        </h1>
        <p className="mt-2 text-muted-foreground">{t("article.notFoundDescription")}</p>
        <Button asChild className="mt-6">
          <Link to="/dashboard/information-center">{t("article.back")}</Link>
        </Button>
      </div>
    );
  }
  if (articleQuery.isError || !articleQuery.data)
    return (
      <QueryErrorState message={t("errors.article")} onRetry={() => void articleQuery.refetch()} />
    );

  const article = articleQuery.data;
  const sectionLabel = article.section.label[i18n.language.startsWith("en") ? "en" : "id"];
  const publishedDate = new Intl.DateTimeFormat(i18n.language, { dateStyle: "long" }).format(
    new Date(article.published_at),
  );
  const consultation = consultationQuery.data?.find(
    (item) => item.public_id === article.consultation_cta_public_id,
  );

  return (
    <article className="mx-auto w-full max-w-7xl pb-[max(1.5rem,env(safe-area-inset-bottom))]">
      <Button asChild variant="ghost" className="mb-5 min-h-11">
        <Link to="/dashboard/information-center">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t("article.back")}
        </Link>
      </Button>
      <header className="mx-auto max-w-3xl border-b pb-8">
        <div className="flex flex-wrap gap-2">
          <Badge>{article.category?.name ?? sectionLabel}</Badge>
          <Badge variant="outline">{sectionLabel}</Badge>
        </div>
        <h1
          ref={pageHeadingRef}
          tabIndex={-1}
          className="mt-5 text-3xl font-semibold leading-tight tracking-tight outline-none sm:text-4xl lg:text-5xl"
        >
          {article.title}
        </h1>
        {article.excerpt && (
          <p className="mt-4 text-lg leading-8 text-muted-foreground">{article.excerpt}</p>
        )}
        <div className="mt-5 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
          <span>{publishedDate}</span>
          {article.estimated_reading_minutes ? (
            <span className="inline-flex items-center gap-1.5">
              <Clock3 className="h-4 w-4" aria-hidden="true" />
              {t("article.readingTime", { count: article.estimated_reading_minutes })}
            </span>
          ) : null}
        </div>
      </header>

      <div className="mx-auto mt-8 max-w-3xl">
        <ContentDocumentPreview document={article.body ?? null} />
      </div>

      {article.attachments?.length ? (
        <section
          aria-labelledby="article-attachments-title"
          className="mx-auto mt-10 max-w-3xl space-y-4"
        >
          <div>
            <h2
              id="article-attachments-title"
              className="flex items-center gap-2 text-xl font-semibold"
            >
              <FileText className="h-5 w-5" aria-hidden="true" />
              {t("attachment.title")}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">{t("attachment.description")}</p>
          </div>
          {article.attachments.map((attachment) => (
            <PublishedContentAttachment key={attachment.public_id} attachment={attachment} />
          ))}
        </section>
      ) : null}

      {consultation && (
        <section
          aria-labelledby="article-consultation-title"
          className="mx-auto mt-10 max-w-3xl space-y-4"
        >
          <div>
            <h2
              id="article-consultation-title"
              className="flex items-center gap-2 text-xl font-semibold"
            >
              <MessageCircleHeart className="h-5 w-5" aria-hidden="true" />
              {t("article.consultationTitle")}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {t("article.consultationDescription")}
            </p>
          </div>
          <PublishedConsultationCard item={consultation} />
        </section>
      )}

      {article.related_articles?.length ? (
        <section aria-labelledby="related-articles-title" className="mt-12 space-y-5">
          <div>
            <h2 id="related-articles-title" className="text-2xl font-semibold">
              {t("article.related")}
            </h2>
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {article.related_articles.map((related) => (
              <PublishedArticleCard key={related.public_id} article={related} />
            ))}
          </div>
        </section>
      ) : null}
    </article>
  );
}

function ArticleSkeleton() {
  return (
    <div className="mx-auto max-w-3xl space-y-5 py-8" role="status" aria-live="polite">
      <Skeleton className="h-6 w-40" />
      <Skeleton className="h-12 w-full" />
      <Skeleton className="h-6 w-3/4" />
      <Skeleton className="h-96 w-full" />
    </div>
  );
}
