import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { BookOpen, Search } from "lucide-react";
import { FormEvent, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";

import { PublishedArticleCard } from "@/components/content/published-article-card";
import { ReporterInformationBreadcrumb } from "@/components/content/reporter-information-breadcrumb";
import { ReporterInformationCta } from "@/components/content/reporter-information-cta";
import { EmptyState } from "@/components/empty-state";
import { ListPagination } from "@/components/list-pagination";
import { QueryErrorState } from "@/components/query-state";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { getPublishedArticleCategories, getPublishedArticles, publishedContentKeys, type PublishedContentFilters } from "@/lib/published-content-api";

export function ReporterArticleListPage({ section }: { section: "education" | "policy" }) {
  const { t } = useTranslation("informationCenter");
  const { user } = useAuth();
  const [input, setInput] = useState("");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("");
  const [page, setPage] = useState(1);
  const pageSize = 12;
  const filters = useMemo<PublishedContentFilters>(() => ({ section, article_category: category || undefined, search: search || undefined, page, per_page: pageSize }), [category, page, search, section]);
  const categories = useQuery({ queryKey: publishedContentKeys.articleCategories(user?.id, section), queryFn: ({ signal }) => getPublishedArticleCategories(section, signal), staleTime: 5 * 60 * 1000 });
  const articles = useQuery({ queryKey: publishedContentKeys.articles(user?.id, filters), queryFn: ({ signal }) => getPublishedArticles(filters, signal), placeholderData: keepPreviousData, staleTime: 2 * 60 * 1000 });
  const title = t(`sections.${section}.title`);
  const submit = (event: FormEvent) => { event.preventDefault(); setSearch(input.trim()); setPage(1); };

  return <div className="mx-auto w-full max-w-7xl space-y-7">
    <ReporterInformationBreadcrumb current={title} />
    <header className="rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-accent/10 p-6 sm:p-8">
      <p className="text-sm font-medium text-primary">{t("eyebrow")}</p><h1 className="mt-2 text-3xl font-semibold">{title}</h1><p className="mt-3 max-w-2xl text-muted-foreground">{t(`articleLists.${section}.description`)}</p>
    </header>
    <div className="grid gap-3 rounded-2xl border bg-card p-4 md:grid-cols-[minmax(0,1fr)_minmax(13rem,18rem)_auto] md:items-end">
      <form onSubmit={submit} className="contents"><div className="grid gap-2"><Label htmlFor={`${section}-search`}>{t("filters.searchLabel")}</Label><div className="relative"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input id={`${section}-search`} className="h-11 pl-9" value={input} maxLength={100} onChange={(e) => setInput(e.target.value)} /></div></div>
      <div className="grid gap-2"><Label htmlFor={`${section}-category`}>{t("filters.category")}</Label><Select value={category || "all"} onValueChange={(value) => { setCategory(value === "all" ? "" : value); setPage(1); }}><SelectTrigger id={`${section}-category`} className="h-11" aria-label={t("filters.category")}><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">{t("filters.allCategories")}</SelectItem>{(categories.data ?? []).map((name) => <SelectItem key={name} value={name}>{name}</SelectItem>)}</SelectContent></Select></div>
      <Button type="submit" className="min-h-11">{t("filters.searchAction")}</Button></form>
    </div>
    {articles.isLoading ? <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-80 rounded-2xl" />)}</div> : articles.isError ? <QueryErrorState message={t("errors.articles")} onRetry={() => void articles.refetch()} /> : !(articles.data?.data.length) ? <EmptyState icon={BookOpen} title={t("empty.articlesTitle")} description={t("empty.articlesDescription")} /> : <><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{articles.data.data.map((article) => <PublishedArticleCard key={article.public_id} article={article} portal />)}</div><ListPagination meta={articles.data.meta} page={page} pageSize={pageSize} onPageChange={setPage} onPageSizeChange={() => undefined} isFetching={articles.isFetching} hidePageSize /></>}
    <ReporterInformationCta />
  </div>;
}
