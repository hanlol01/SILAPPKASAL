import { useQuery } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { ArrowRight, BookOpen } from "lucide-react";
import { useCallback, useState } from "react";
import { useTranslation } from "react-i18next";

import { AuthenticatedContentCover } from "@/components/content/authenticated-content-cover";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { getFeaturedContent, publishedContentKeys } from "@/lib/published-content-api";

const filters = { section: "education" as const, limit: 1 };

export function EducationSpotlight() {
  const { t } = useTranslation("informationCenter");
  const { user } = useAuth();
  const query = useQuery({
    queryKey: publishedContentKeys.featured(user?.id, filters),
    queryFn: ({ signal }) => getFeaturedContent(filters, signal),
    enabled: Boolean(user?.permissions?.includes("content.read.published")),
    staleTime: 2 * 60 * 1000,
  });

  if (query.isLoading) return <Skeleton className="h-72 rounded-3xl" />;
  const article = query.data?.[0];
  if (!article) return null;

  return (
    <section aria-labelledby="education-spotlight-title" className="space-y-4">
      <div>
        <h2 id="education-spotlight-title" className="text-xl font-semibold">
          {t("spotlight.title")}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{t("spotlight.description")}</p>
      </div>
      <Card className="overflow-hidden rounded-3xl">
        <CardContent className="grid p-0 md:grid-cols-[minmax(0,1.1fr)_minmax(18rem,0.9fr)]">
          <Link
            to="/portal/information-center/education/$slug"
            params={{ slug: article.slug }}
            className="group relative min-h-64 overflow-hidden bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
            aria-label={t("article.open", { title: article.title })}
          >
            <EducationSpotlightCover key={article.cover?.public_id ?? "fallback"} article={article} />
            <span className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent" aria-hidden="true" />
          </Link>
          <div className="flex flex-col justify-center p-6 sm:p-8">
            <Badge className="w-fit">{article.category_name ?? article.category?.name ?? t("sections.education.title")}</Badge>
            <h3 className="mt-4 text-2xl font-semibold leading-tight">{article.title}</h3>
            {article.excerpt && <p className="mt-3 line-clamp-4 text-sm leading-6 text-muted-foreground">{article.excerpt}</p>}
            <div className="mt-6 flex flex-wrap gap-3">
              <Button asChild>
                <Link to="/portal/information-center/education/$slug" params={{ slug: article.slug }}>
                  {t("spotlight.read")} <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <Button asChild variant="outline">
                <Link to="/portal/information-center/education">
                  <BookOpen className="h-4 w-4" /> {t("spotlight.all")}
                </Link>
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </section>
  );
}

function EducationSpotlightCover({ article }: { article: Awaited<ReturnType<typeof getFeaturedContent>>[number] }) {
  const [coverUnavailable, setCoverUnavailable] = useState(false);
  const markUnavailable = useCallback(() => setCoverUnavailable(true), []);

  return <>
    <span className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-sky-950 via-blue-800 to-cyan-600" aria-hidden="true">
      <span className="flex h-24 w-24 items-center justify-center rounded-3xl border border-white/30 bg-white/15 text-white shadow-2xl backdrop-blur-sm transition-transform duration-300 group-hover:scale-[1.02] motion-reduce:transition-none">
        <BookOpen className="h-12 w-12" />
      </span>
    </span>
    {article.cover && !coverUnavailable ? <AuthenticatedContentCover
      publicId={article.cover.public_id}
      alt={article.cover.alt_text ?? article.title}
      className="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.02] motion-reduce:transition-none"
      onUnavailable={markUnavailable}
    /> : null}
  </>;
}
