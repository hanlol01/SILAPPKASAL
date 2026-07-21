import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import {
  BookOpen,
  CircleHelp,
  Filter,
  Landmark,
  LibraryBig,
  MessageCircleHeart,
  Search,
} from "lucide-react";
import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { useTranslation } from "react-i18next";

import { FeaturedArticleSection } from "@/components/content/featured-article-section";
import { PublishedArticleCard } from "@/components/content/published-article-card";
import { PublishedConsultationCard } from "@/components/content/published-consultation-card";
import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { EmptyState } from "@/components/empty-state";
import { ListPagination } from "@/components/list-pagination";
import { QueryErrorState } from "@/components/query-state";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import {
  getPublishedArticles,
  getPublishedCategories,
  getPublishedConsultation,
  getPublishedFaqs,
  publishedContentKeys,
  type PublishedContentFilters,
} from "@/lib/published-content-api";

type ReaderView = "articles" | "faq" | "consultation";
interface InformationCenterSearch {
  view?: ReaderView;
  section?: string;
  category?: string;
  q?: string;
  page?: number;
  open?: string;
}

const views: ReaderView[] = ["articles", "faq", "consultation"];
const sectionCodes = ["education", "policy"];
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export const Route = createFileRoute("/dashboard/information-center/")({
  validateSearch: (raw: Record<string, unknown>): InformationCenterSearch => ({
    view: views.find((value) => value === raw.view),
    section:
      typeof raw.section === "string" && sectionCodes.includes(raw.section)
        ? raw.section
        : undefined,
    category:
      typeof raw.category === "string" && UUID_PATTERN.test(raw.category)
        ? raw.category
        : undefined,
    q: typeof raw.q === "string" ? raw.q.slice(0, 100) : undefined,
    page: Number.isInteger(Number(raw.page)) && Number(raw.page) > 0 ? Number(raw.page) : undefined,
    open: typeof raw.open === "string" ? raw.open : undefined,
  }),
  component: InformationCenterPage,
  head: () => ({ meta: [{ title: "Pusat Informasi - SILAPPKASAL" }] }),
});

function InformationCenterPage() {
  const { t } = useTranslation("informationCenter");
  const { user } = useAuth();
  const search = Route.useSearch();
  const navigate = Route.useNavigate();
  const view = search.view ?? "articles";
  const page = search.page ?? 1;
  const pageSize = 12;
  const pageHeadingRef = useRef<HTMLHeadingElement | null>(null);
  const [searchInput, setSearchInput] = useState(search.q ?? "");
  const [filterOpen, setFilterOpen] = useState(false);

  useEffect(() => pageHeadingRef.current?.focus({ preventScroll: true }), []);
  useEffect(() => setSearchInput(search.q ?? ""), [search.q]);

  const filters = useMemo<PublishedContentFilters>(
    () => ({
      section: view === "articles" ? search.section : undefined,
      category: search.category,
      search: search.q,
      page,
      per_page: pageSize,
    }),
    [page, search.category, search.q, search.section, view],
  );

  const categorySection = view === "faq" ? "faq" : search.section;
  const categoriesQuery = useQuery({
    queryKey: publishedContentKeys.categories(user?.id, categorySection),
    queryFn: ({ signal }) => getPublishedCategories(categorySection, signal),
    staleTime: 5 * 60 * 1000,
  });
  const articlesQuery = useQuery({
    queryKey: publishedContentKeys.articles(user?.id, filters),
    queryFn: ({ signal }) => getPublishedArticles(filters, signal),
    enabled: view === "articles",
    placeholderData: keepPreviousData,
    staleTime: 2 * 60 * 1000,
  });
  const faqsQuery = useQuery({
    queryKey: publishedContentKeys.faqs(user?.id, filters),
    queryFn: ({ signal }) => getPublishedFaqs(filters, signal),
    enabled: view === "faq",
    placeholderData: keepPreviousData,
    staleTime: 2 * 60 * 1000,
  });
  const consultationQuery = useQuery({
    queryKey: publishedContentKeys.consultation(user?.id),
    queryFn: ({ signal }) => getPublishedConsultation(signal),
    enabled: view === "consultation",
    staleTime: 2 * 60 * 1000,
  });
  const visibleCategories = (categoriesQuery.data ?? []).filter((category) =>
    view === "faq"
      ? category.section_code === "faq"
      : sectionCodes.includes(category.section_code ?? ""),
  );

  function updateSearch(next: Partial<InformationCenterSearch>) {
    void navigate({
      search: (current) => ({ ...current, ...next }),
      replace: true,
    });
  }

  function submitSearch(event: FormEvent) {
    event.preventDefault();
    updateSearch({ q: searchInput.trim() || undefined, page: undefined });
  }

  return (
    <div className="mx-auto w-full max-w-7xl space-y-8 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
      <header className="rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-accent/10 px-5 py-8 sm:px-8 lg:px-10">
        <div className="max-w-3xl">
          <p className="text-sm font-medium text-primary">{t("eyebrow")}</p>
          <h1
            ref={pageHeadingRef}
            tabIndex={-1}
            className="mt-2 text-3xl font-semibold tracking-tight focus:outline-none sm:text-4xl"
          >
            {t("title")}
          </h1>
          <p className="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
            {t("introduction")}
          </p>
        </div>
      </header>

      <section aria-labelledby="information-sections-title" className="space-y-4">
        <div>
          <h2 id="information-sections-title" className="text-2xl font-semibold">
            {t("sections.title")}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">{t("sections.description")}</p>
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <ShortcutCard
            icon={BookOpen}
            title={t("sections.education.title")}
            description={t("sections.education.description")}
            search={{ section: "education" }}
          />
          <ShortcutCard
            icon={Landmark}
            title={t("sections.policy.title")}
            description={t("sections.policy.description")}
            search={{ section: "policy" }}
          />
          <ShortcutCard
            icon={CircleHelp}
            title={t("sections.faq.title")}
            description={t("sections.faq.description")}
            search={{ view: "faq" }}
          />
          <ShortcutCard
            icon={MessageCircleHeart}
            title={t("sections.consultation.title")}
            description={t("sections.consultation.description")}
            search={{ view: "consultation" }}
          />
        </div>
      </section>

      <FeaturedArticleSection />

      <section aria-labelledby="reader-results-title" className="space-y-5">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 id="reader-results-title" className="text-2xl font-semibold">
              {t(`views.${view}.title`)}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">{t(`views.${view}.description`)}</p>
          </div>
          {view !== "consultation" && (
            <form onSubmit={submitSearch} className="flex min-w-0 gap-2" role="search">
              <Label htmlFor="information-search" className="sr-only">
                {t("filters.searchLabel")}
              </Label>
              <div className="relative min-w-0 flex-1 lg:w-80">
                <Search
                  className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                  aria-hidden="true"
                />
                <Input
                  id="information-search"
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                  maxLength={100}
                  placeholder={t("filters.searchPlaceholder")}
                  className="h-11 pl-9"
                />
              </div>
              <Button type="submit" className="min-h-11">
                {t("filters.search")}
              </Button>
            </form>
          )}
        </div>

        {view !== "consultation" && (
          <>
            <div className="hidden items-end gap-3 md:flex">
              <FilterFields
                view={view}
                section={search.section}
                category={search.category}
                categories={visibleCategories}
                onSection={(section) =>
                  updateSearch({ section, category: undefined, page: undefined })
                }
                onCategory={(category) => updateSearch({ category, page: undefined })}
                t={t}
              />
              <Button
                variant="ghost"
                className="min-h-11"
                onClick={() =>
                  void navigate({ search: { view: view === "articles" ? undefined : view } })
                }
              >
                {t("filters.reset")}
              </Button>
            </div>
            <Sheet open={filterOpen} onOpenChange={setFilterOpen}>
              <SheetTrigger asChild>
                <Button variant="outline" className="min-h-11 md:hidden">
                  <Filter className="h-4 w-4" aria-hidden="true" />
                  {t("filters.open")}
                </Button>
              </SheetTrigger>
              <SheetContent
                side="bottom"
                className="rounded-t-3xl pb-[max(1.5rem,env(safe-area-inset-bottom))]"
              >
                <SheetHeader>
                  <SheetTitle>{t("filters.title")}</SheetTitle>
                  <SheetDescription>{t("filters.description")}</SheetDescription>
                </SheetHeader>
                <div className="mt-5 grid gap-4">
                  <FilterFields
                    view={view}
                    section={search.section}
                    category={search.category}
                    categories={visibleCategories}
                    onSection={(section) =>
                      updateSearch({ section, category: undefined, page: undefined })
                    }
                    onCategory={(category) => updateSearch({ category, page: undefined })}
                    t={t}
                  />
                  <Button onClick={() => setFilterOpen(false)} className="min-h-11">
                    {t("filters.apply")}
                  </Button>
                </div>
              </SheetContent>
            </Sheet>
          </>
        )}

        {view === "articles" && (
          <ArticleResults
            query={articlesQuery}
            page={page}
            pageSize={pageSize}
            onPage={(next) => updateSearch({ page: next })}
            t={t}
          />
        )}
        {view === "faq" && (
          <FaqResults
            query={faqsQuery}
            open={search.open}
            page={page}
            pageSize={pageSize}
            onOpen={(value) => updateSearch({ open: value || undefined })}
            onPage={(next) => updateSearch({ page: next })}
            t={t}
          />
        )}
        {view === "consultation" && <ConsultationResults query={consultationQuery} t={t} />}
      </section>
    </div>
  );
}

function ShortcutCard({
  icon: Icon,
  title,
  description,
  search,
}: {
  icon: typeof BookOpen;
  title: string;
  description: string;
  search: InformationCenterSearch;
}) {
  return (
    <Link
      to="/dashboard/information-center"
      search={search}
      className="group min-h-11 rounded-2xl border bg-card p-5 text-left shadow-sm transition-[border-color,box-shadow,transform] hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 motion-reduce:transform-none motion-reduce:transition-none"
    >
      <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
        <Icon className="h-5 w-5" aria-hidden="true" />
      </span>
      <span className="mt-4 block font-semibold">{title}</span>
      <span className="mt-1 block text-sm leading-5 text-muted-foreground">{description}</span>
    </Link>
  );
}

function FilterFields({
  view,
  section,
  category,
  categories,
  onSection,
  onCategory,
  t,
}: {
  view: ReaderView;
  section?: string;
  category?: string;
  categories: Array<{ public_id: string; name: string }>;
  onSection: (value?: string) => void;
  onCategory: (value?: string) => void;
  t: (key: string) => string;
}) {
  return (
    <>
      {view === "articles" && (
        <div className="grid gap-2">
          <Label>{t("filters.section")}</Label>
          <Select
            value={section ?? "all"}
            onValueChange={(value) => onSection(value === "all" ? undefined : value)}
          >
            <SelectTrigger className="h-11 min-w-48">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("filters.allSections")}</SelectItem>
              <SelectItem value="education">{t("sections.education.title")}</SelectItem>
              <SelectItem value="policy">{t("sections.policy.title")}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      )}
      <div className="grid gap-2">
        <Label>{t("filters.category")}</Label>
        <Select
          value={category ?? "all"}
          onValueChange={(value) => onCategory(value === "all" ? undefined : value)}
        >
          <SelectTrigger className="h-11 min-w-56">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t("filters.allCategories")}</SelectItem>
            {categories.map((item) => (
              <SelectItem key={item.public_id} value={item.public_id}>
                {item.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </>
  );
}

type QueryResult<T> = {
  isLoading: boolean;
  isError: boolean;
  isFetching: boolean;
  data?: T;
  refetch: () => unknown;
};

function ArticleResults({
  query,
  page,
  pageSize,
  onPage,
  t,
}: {
  query: QueryResult<{
    data: import("@/lib/published-content-api").PublishedArticle[];
    meta?: import("@/lib/api-types").PaginationMeta;
  }>;
  page: number;
  pageSize: number;
  onPage: (page: number) => void;
  t: (key: string) => string;
}) {
  if (query.isLoading) return <ResultSkeleton />;
  if (query.isError)
    return <QueryErrorState message={t("errors.articles")} onRetry={() => void query.refetch()} />;
  const articles = query.data?.data ?? [];
  if (!articles.length)
    return (
      <EmptyState
        icon={LibraryBig}
        title={t("empty.articlesTitle")}
        description={t("empty.articlesDescription")}
      />
    );
  return (
    <>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {articles.map((article) => (
          <PublishedArticleCard key={article.public_id} article={article} />
        ))}
      </div>
      <ListPagination
        meta={query.data?.meta}
        page={page}
        pageSize={pageSize}
        onPageChange={onPage}
        onPageSizeChange={() => undefined}
        isFetching={query.isFetching}
        hidePageSize
      />
    </>
  );
}

function FaqResults({
  query,
  open,
  page,
  pageSize,
  onOpen,
  onPage,
  t,
}: {
  query: QueryResult<{
    data: import("@/lib/published-content-api").PublishedFaq[];
    meta?: import("@/lib/api-types").PaginationMeta;
  }>;
  open?: string;
  page: number;
  pageSize: number;
  onOpen: (value: string) => void;
  onPage: (page: number) => void;
  t: (key: string) => string;
}) {
  if (query.isLoading) return <ResultSkeleton />;
  if (query.isError)
    return <QueryErrorState message={t("errors.faq")} onRetry={() => void query.refetch()} />;
  const faqs = query.data?.data ?? [];
  if (!faqs.length)
    return (
      <EmptyState
        icon={CircleHelp}
        title={t("empty.faqTitle")}
        description={t("empty.faqDescription")}
      />
    );
  return (
    <>
      <Card>
        <CardContent className="p-4 sm:p-6">
          <Accordion type="single" collapsible value={open} onValueChange={onOpen}>
            {faqs.map((faq) => (
              <AccordionItem key={faq.public_id} value={faq.public_id}>
                <AccordionTrigger className="min-h-11 text-base">{faq.question}</AccordionTrigger>
                <AccordionContent>
                  <ContentDocumentPreview document={faq.answer} />
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </CardContent>
      </Card>
      <ListPagination
        meta={query.data?.meta}
        page={page}
        pageSize={pageSize}
        onPageChange={onPage}
        onPageSizeChange={() => undefined}
        isFetching={query.isFetching}
        hidePageSize
      />
    </>
  );
}

function ConsultationResults({
  query,
  t,
}: {
  query: QueryResult<import("@/lib/published-content-api").PublishedConsultation[]>;
  t: (key: string) => string;
}) {
  if (query.isLoading) return <ResultSkeleton />;
  if (query.isError)
    return (
      <QueryErrorState message={t("errors.consultation")} onRetry={() => void query.refetch()} />
    );
  const items = query.data ?? [];
  if (!items.length)
    return (
      <EmptyState
        icon={MessageCircleHeart}
        title={t("empty.consultationTitle")}
        description={t("empty.consultationDescription")}
      />
    );
  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      {items.map((item) => (
        <PublishedConsultationCard key={item.public_id} item={item} />
      ))}
    </div>
  );
}

function ResultSkeleton() {
  return (
    <div
      className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
      role="status"
      aria-live="polite"
    >
      {Array.from({ length: 6 }).map((_, index) => (
        <Skeleton key={index} className="h-80 rounded-2xl" />
      ))}
    </div>
  );
}
