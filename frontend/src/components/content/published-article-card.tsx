import { Link } from "@tanstack/react-router";
import { ArrowUpRight, BookOpen, Landmark, LibraryBig } from "lucide-react";
import { useCallback, useState } from "react";
import { useTranslation } from "react-i18next";

import { AuthenticatedContentCover } from "@/components/content/authenticated-content-cover";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import type { PublishedArticle } from "@/lib/published-content-api";

interface PublishedArticleCardProps {
  article: PublishedArticle;
  featured?: boolean;
  className?: string;
  portal?: boolean;
}

const sectionVisuals = {
  education: {
    icon: BookOpen,
    surface: "from-sky-500/20 via-cyan-400/10 to-background",
    accent: "bg-sky-500/15 text-sky-800 dark:text-sky-200",
  },
  policy: {
    icon: Landmark,
    surface: "from-violet-500/20 via-indigo-400/10 to-background",
    accent: "bg-violet-500/15 text-violet-800 dark:text-violet-200",
  },
} as const;

export function PublishedArticleCard({
  article,
  featured = false,
  className,
  portal = false,
}: PublishedArticleCardProps) {
  const { t, i18n } = useTranslation("informationCenter");
  const [coverUnavailable, setCoverUnavailable] = useState(false);
  const handleCoverUnavailable = useCallback(() => setCoverUnavailable(true), []);
  const visual = sectionVisuals[article.section.code as keyof typeof sectionVisuals] ?? {
    icon: LibraryBig,
    surface: "from-primary/20 via-accent/10 to-background",
    accent: "bg-primary/10 text-primary",
  };
  const SectionIcon = visual.icon;
  const sectionLabel = article.section.label[i18n.language.startsWith("en") ? "en" : "id"];
  const hasSafeCover = Boolean(
    article.cover && article.cover.mime_type.startsWith("image/") && !coverUnavailable,
  );
  const publicationDate = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: "medium",
  }).format(new Date(article.published_at));
  const detailTo = article.section.code === "policy"
    ? "/portal/information-center/policies/$slug"
    : "/portal/information-center/education/$slug";

  return (
    <Link
      to={portal ? detailTo : "/dashboard/information-center/articles/$articleId"}
      params={portal ? { slug: article.slug } : { articleId: article.public_id }}
      aria-label={t("article.open", { title: article.title })}
      className={cn(
        "group flex min-h-11 min-w-0 flex-col overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-sm",
        "transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md",
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
        "motion-reduce:transform-none motion-reduce:transition-none",
        featured && "h-full",
        className,
      )}
    >
      <div
        className={cn("relative min-h-36 overflow-hidden bg-gradient-to-br p-5", visual.surface)}
      >
        {hasSafeCover && article.cover ? (
          <AuthenticatedContentCover
            publicId={article.cover.public_id}
            alt={article.cover.alt_text ?? article.title}
            className="absolute inset-0 h-full w-full object-cover"
            onUnavailable={handleCoverUnavailable}
          />
        ) : null}
        <div
          className="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-current/5"
          aria-hidden="true"
        />
        <div
          className="absolute -bottom-10 left-10 h-24 w-24 rounded-full border border-current/10"
          aria-hidden="true"
        />
        {!hasSafeCover && (
          <div
            className={cn(
              "relative flex h-12 w-12 items-center justify-center rounded-2xl",
              visual.accent,
            )}
          >
            <SectionIcon className="h-6 w-6" aria-hidden="true" />
          </div>
        )}
      </div>

      <div className="flex flex-1 flex-col p-5">
        <div className="mb-3 flex min-w-0 flex-wrap items-center gap-2">
          <Badge variant="secondary" className="max-w-full truncate">
            {article.category_name ?? article.category?.name ?? sectionLabel}
          </Badge>
          <span className="text-xs text-muted-foreground">{sectionLabel}</span>
        </div>
        <h3
          className={cn(
            "text-lg font-semibold leading-snug tracking-tight",
            featured && "sm:text-xl",
          )}
        >
          {article.title}
        </h3>
        {article.excerpt && (
          <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">
            {article.excerpt}
          </p>
        )}
        <div className="mt-auto flex min-w-0 items-center justify-between gap-3 pt-5 text-xs text-muted-foreground">
          <span>{publicationDate}</span>
          <span className="inline-flex min-h-11 items-center gap-1 font-medium text-primary">
            {t("article.read")}
            <ArrowUpRight
              className="h-4 w-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none"
              aria-hidden="true"
            />
          </span>
        </div>
      </div>
    </Link>
  );
}
