import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { ArrowLeft, CircleHelp, Search } from "lucide-react";
import { FormEvent, useState } from "react";
import { useTranslation } from "react-i18next";

import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { DashboardInformationBreadcrumb } from "@/components/content/dashboard-information-breadcrumb";
import { DashboardInformationManagementCta } from "@/components/content/dashboard-information-management-cta";
import { ReporterInformationBreadcrumb } from "@/components/content/reporter-information-breadcrumb";
import { ReporterInformationCta } from "@/components/content/reporter-information-cta";
import { EmptyState } from "@/components/empty-state";
import { QueryErrorState } from "@/components/query-state";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import { getPublishedFaqs, publishedContentKeys } from "@/lib/published-content-api";

export function InformationFaqPage({ area }: { area: "portal" | "dashboard" }) {
  const { t } = useTranslation("informationCenter");
  const { user } = useAuth();
  const [input, setInput] = useState("");
  const [search, setSearch] = useState("");
  const filters = { section: "faq", search: search || undefined, per_page: 50 };
  const query = useQuery({ queryKey: publishedContentKeys.faqs(user?.id, filters), queryFn: ({ signal }) => getPublishedFaqs(filters, signal), placeholderData: keepPreviousData, staleTime: 2 * 60 * 1000 });
  const submit = (event: FormEvent) => { event.preventDefault(); setSearch(input.trim()); };
  const home = area === "portal" ? "/portal/information-center" : "/dashboard/information-center";
  const title = t("sections.faq.title");

  return <div className="mx-auto w-full max-w-5xl space-y-7">
    {area === "portal" ? <ReporterInformationBreadcrumb current={title} /> : <DashboardInformationBreadcrumb current={title} />}
    <Button asChild variant="ghost" className="min-h-11"><Link to={home}><ArrowLeft className="h-4 w-4" />Kembali ke Information Center</Link></Button>
    <header className="rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-accent/10 p-6 sm:p-8"><p className="text-sm font-medium text-primary">{t("eyebrow")}</p><h1 className="mt-2 text-3xl font-semibold">{title}</h1><p className="mt-3 text-muted-foreground">{t("views.faq.description")}</p></header>
    <form onSubmit={submit} role="search" className="flex gap-2 rounded-2xl border bg-card p-4"><div className="relative flex-1"><Label htmlFor={`${area}-faq-search`} className="sr-only">{t("filters.searchLabel")}</Label><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input id={`${area}-faq-search`} className="h-11 pl-9" value={input} maxLength={100} placeholder={t("filters.faqSearchPlaceholder")} onChange={(event) => setInput(event.target.value)} /></div><Button type="submit" className="min-h-11">{t("filters.searchAction")}</Button></form>
    {query.isLoading ? <Skeleton className="h-80 rounded-2xl" /> : query.isError ? <QueryErrorState message={t("errors.faq")} onRetry={() => void query.refetch()} /> : !(query.data?.data.length) ? <EmptyState icon={CircleHelp} title={t("empty.faqTitle")} description={t("empty.faqDescription")} /> : <Card><CardContent className="p-4 sm:p-6"><Accordion type="single" collapsible>{query.data.data.map((faq) => <AccordionItem key={faq.public_id} value={faq.public_id}><AccordionTrigger className="min-h-11 text-left text-base">{faq.question}</AccordionTrigger><AccordionContent><ContentDocumentPreview document={faq.answer} /></AccordionContent></AccordionItem>)}</Accordion></CardContent></Card>}
    {area === "portal" ? <ReporterInformationCta /> : <DashboardInformationManagementCta />}
  </div>;
}
