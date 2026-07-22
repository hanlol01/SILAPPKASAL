import { createFileRoute, Link } from "@tanstack/react-router";
import { ArrowRight, BookOpen, CircleHelp, Landmark, MessageCircleHeart } from "lucide-react";
import { useTranslation } from "react-i18next";
import { ReporterInformationBreadcrumb } from "@/components/content/reporter-information-breadcrumb";
import { ReporterInformationCta } from "@/components/content/reporter-information-cta";
import { Card, CardContent } from "@/components/ui/card";

export const Route = createFileRoute("/portal/information-center/")({ component: ReporterInformationHome });
const destinations = [
  ["education", "/portal/information-center/education", BookOpen],
  ["policy", "/portal/information-center/policies", Landmark],
  ["faq", "/portal/information-center/faq", CircleHelp],
  ["consultation", "/portal/information-center/consultation", MessageCircleHeart],
] as const;
function ReporterInformationHome() {
  const { t } = useTranslation("informationCenter");
  return <div className="mx-auto w-full max-w-7xl space-y-7"><ReporterInformationBreadcrumb />
    <header className="rounded-3xl border bg-gradient-to-br from-primary/10 via-background to-accent/10 p-6 sm:p-10"><p className="text-sm font-medium text-primary">{t("eyebrow")}</p><h1 className="mt-2 text-3xl font-semibold sm:text-4xl">{t("title")}</h1><p className="mt-3 max-w-2xl leading-7 text-muted-foreground">{t("introduction")}</p></header>
    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label={t("sections.title")}>{destinations.map(([key, to, Icon]) => <Link key={key} to={to} className="group rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><Card className="h-full transition group-hover:border-primary/40 group-hover:shadow-md"><CardContent className="flex h-full flex-col p-5"><span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary"><Icon className="h-5 w-5" /></span><h2 className="mt-4 text-lg font-semibold">{t(`sections.${key}.title`)}</h2><p className="mt-2 flex-1 text-sm leading-6 text-muted-foreground">{t(`sections.${key}.description`)}</p><span className="mt-4 inline-flex min-h-11 items-center gap-2 font-medium text-primary">{t("sections.open")}<ArrowRight className="h-4 w-4" /></span></CardContent></Card></Link>)}</section>
    <ReporterInformationCta />
  </div>;
}
