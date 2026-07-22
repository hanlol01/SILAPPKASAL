import { Link } from "@tanstack/react-router";
import { ArrowRight, ShieldCheck } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

export function ReporterInformationCta() {
  const { t } = useTranslation("informationCenter");
  return (
    <Card className="border-primary/20 bg-primary/5">
      <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><ShieldCheck className="h-5 w-5" aria-hidden="true" /></span>
          <div><p className="font-semibold">{t("reportCta.title")}</p><p className="mt-1 text-sm text-muted-foreground">{t("reportCta.description")}</p></div>
        </div>
        <Button asChild className="min-h-11 shrink-0"><Link to="/portal/reports/new">{t("reportCta.action")} <ArrowRight className="h-4 w-4" /></Link></Button>
      </CardContent>
    </Card>
  );
}
