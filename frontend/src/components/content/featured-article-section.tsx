import type { CarouselApi } from "@/components/ui/carousel";
import { useQuery } from "@tanstack/react-query";
import { Sparkles } from "lucide-react";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";

import { PublishedArticleCard } from "@/components/content/published-article-card";
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from "@/components/ui/carousel";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { getFeaturedContent, publishedContentKeys } from "@/lib/published-content-api";

export function FeaturedArticleSection({ compact = false }: { compact?: boolean }) {
  const { t } = useTranslation("informationCenter");
  const { user } = useAuth();
  const [api, setApi] = useState<CarouselApi>();
  const [selected, setSelected] = useState(0);
  const query = useQuery({
    queryKey: publishedContentKeys.featured(user?.id),
    queryFn: ({ signal }) => getFeaturedContent(signal),
    enabled: Boolean(user?.permissions?.includes("content.read.published")),
    staleTime: 2 * 60 * 1000,
  });

  useEffect(() => {
    if (!api) return;
    const update = () => setSelected(api.selectedScrollSnap());
    update();
    api.on("select", update);
    api.on("reInit", update);
    return () => {
      api.off("select", update);
      api.off("reInit", update);
    };
  }, [api]);

  if (query.isLoading) {
    return (
      <section aria-labelledby="featured-content-title" className="space-y-4">
        <FeaturedHeading compact={compact} />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: compact ? 2 : 3 }).map((_, index) => (
            <Skeleton key={index} className="h-96 rounded-2xl" />
          ))}
        </div>
      </section>
    );
  }

  if (query.isError) {
    return (
      <section aria-labelledby="featured-content-title" className="space-y-4">
        <FeaturedHeading compact={compact} />
        <div
          className="rounded-2xl border border-dashed p-5 text-sm text-muted-foreground"
          role="status"
        >
          {t("featured.error")}
        </div>
      </section>
    );
  }

  const articles = query.data ?? [];
  if (articles.length === 0) {
    return (
      <section aria-labelledby="featured-content-title" className="space-y-4">
        <FeaturedHeading compact={compact} />
        <div className="flex items-center gap-3 rounded-2xl border border-dashed p-5 text-sm text-muted-foreground">
          <Sparkles className="h-5 w-5 shrink-0" aria-hidden="true" />
          <p>{t("featured.empty")}</p>
        </div>
      </section>
    );
  }

  return (
    <section aria-labelledby="featured-content-title" className="min-w-0 space-y-4">
      <FeaturedHeading compact={compact} />
      <Carousel
        setApi={setApi}
        opts={{ align: "start", loop: false }}
        aria-label={t("featured.carouselLabel")}
      >
        <CarouselContent className="items-stretch">
          {articles.map((article) => (
            <CarouselItem key={article.public_id} className="basis-[88%] sm:basis-1/2 lg:basis-1/3">
              <PublishedArticleCard article={article} featured className="h-full" />
            </CarouselItem>
          ))}
        </CarouselContent>
        {articles.length > 1 && (
          <div className="mt-4 flex items-center justify-between gap-4">
            <div
              className="flex gap-1.5"
              role="status"
              aria-live="polite"
              aria-label={t("featured.position", { current: selected + 1, total: articles.length })}
            >
              {articles.map((article, index) => (
                <span
                  key={article.public_id}
                  className={
                    index === selected
                      ? "h-2 w-6 rounded-full bg-primary"
                      : "h-2 w-2 rounded-full bg-muted-foreground/30"
                  }
                  aria-hidden="true"
                />
              ))}
            </div>
            <div className="flex min-h-11 items-center gap-2">
              <CarouselPrevious
                className="static h-11 w-11 translate-x-0 translate-y-0"
                aria-label={t("featured.previous")}
              />
              <CarouselNext
                className="static h-11 w-11 translate-x-0 translate-y-0"
                aria-label={t("featured.next")}
              />
            </div>
          </div>
        )}
      </Carousel>
    </section>
  );
}

function FeaturedHeading({ compact }: { compact: boolean }) {
  const { t } = useTranslation("informationCenter");
  return (
    <div>
      <h2
        id="featured-content-title"
        className={compact ? "text-xl font-semibold" : "text-2xl font-semibold"}
      >
        {t("featured.title")}
      </h2>
      <p className="mt-1 text-sm text-muted-foreground">{t("featured.description")}</p>
    </div>
  );
}
