import { useQuery } from "@tanstack/react-query";
import { ChevronDown, History, RefreshCw } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { ProgressTimeline, ProgressTimelineSkeleton } from "@/components/progress-timeline";
import { Button } from "@/components/ui/button";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { formatDateTime } from "@/lib/format";
import { formatEvidenceCustodyEvent } from "@/lib/format-labels";
import { getEvidenceCustody, operationsQueryKeys } from "@/lib/operations-api";

export function EvidenceCustodyDisclosure({
  evidenceId,
  language,
}: {
  evidenceId: string | number;
  language: string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const custodyQuery = useQuery({
    queryKey: operationsQueryKeys.evidenceCustody(evidenceId),
    queryFn: () => getEvidenceCustody(evidenceId),
    enabled: open,
  });

  const events = (custodyQuery.data ?? []).map((event, index) => ({
    id: `${evidenceId}-${event.event_type}-${event.event_at ?? index}`,
    title: formatEvidenceCustodyEvent(t, event.event_type),
    timestamp: formatDateTime(event.event_at, language),
    description: t("dashboard:sections.custodyActor", {
      actor: event.actor?.name ?? t("dashboard:sections.custodyUnknownActor"),
    }),
    icon: History,
  }));

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="min-w-0 border-t pt-3">
      <CollapsibleTrigger asChild>
        <Button variant="ghost" size="sm" className="group h-auto w-full min-w-0 justify-between gap-2 whitespace-normal px-2 py-2">
          <span className="flex min-w-0 items-center gap-2 text-left">
            <History className="h-4 w-4 shrink-0" aria-hidden="true" />
            <span className="min-w-0 break-words [overflow-wrap:anywhere] whitespace-normal">{t("dashboard:sections.custodyTitle")}</span>
          </span>
          <ChevronDown className="h-4 w-4 shrink-0 transition-transform group-data-[state=open]:rotate-180" aria-hidden="true" />
        </Button>
      </CollapsibleTrigger>
      <CollapsibleContent className="min-w-0 px-2 pb-1 pt-3">
        {custodyQuery.isLoading ? (
          <ProgressTimelineSkeleton rows={2} />
        ) : custodyQuery.isError ? (
          <div className="flex min-w-0 flex-col items-start gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="min-w-0 break-words text-sm text-destructive [overflow-wrap:anywhere] whitespace-pre-wrap">{t("dashboard:sections.custodyError")}</p>
            <Button variant="outline" size="sm" onClick={() => custodyQuery.refetch()}>
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
              {t("dashboard:sections.custodyRetry")}
            </Button>
          </div>
        ) : events.length === 0 ? (
          <p className="min-w-0 break-words rounded-md border border-dashed p-3 text-sm text-muted-foreground [overflow-wrap:anywhere] whitespace-pre-wrap">
            {t("dashboard:sections.custodyEmpty")}
          </p>
        ) : (
          <ProgressTimeline
            events={events}
            className="min-w-0 [&_li]:min-w-0 [&_li>div>div]:break-words [&_li>div>div]:[overflow-wrap:anywhere] [&_li>div>div]:whitespace-pre-wrap"
          />
        )}
      </CollapsibleContent>
    </Collapsible>
  );
}
