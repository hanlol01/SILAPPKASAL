import { FileText, Clock, CheckCircle2, Bell } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import type { PortalSummary } from "@/lib/portal-types";
import { useTranslation } from "react-i18next";

interface PortalSummaryCardsProps {
  data: PortalSummary;
}

const getCards = (t: import("i18next").TFunction) => [
  {
    key: "total_reports" as const,
    label: t("totalReports"),
    icon: FileText,
    tone: "bg-primary/10 text-primary",
    description: t("totalReportsDesc"),
  },
  {
    key: "active_reports" as const,
    label: t("activeReports"),
    icon: Clock,
    tone: "bg-warning/10 text-warning",
    description: t("activeDesc"),
  },
  {
    key: "completed_reports" as const,
    label: t("completedReports"),
    icon: CheckCircle2,
    tone: "bg-success/10 text-success",
    description: t("completedDesc"),
  },
  {
    key: "unread_notifications" as const,
    label: t("notifications"),
    icon: Bell,
    tone: "bg-info/10 text-info",
    description: t("unreadUpdates"),
  },
];

export function PortalSummaryCards({ data }: PortalSummaryCardsProps) {
  const { t } = useTranslation(["portal"]);
  const cards = getCards(t);

  return (
    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
      {cards.map((card) => (
        <Card key={card.key} className="overflow-hidden">
          <CardContent className="h-full p-4 sm:p-5">
            <div className="flex h-full min-w-0 flex-col">
              <div className="flex min-w-0 items-start justify-between gap-2">
                <p className="min-w-0 text-xs leading-4 text-muted-foreground sm:text-sm">
                  {card.label}
                </p>
                <div
                  className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg sm:h-10 sm:w-10 ${card.tone}`}
                >
                  <card.icon className="h-4 w-4 sm:h-5 sm:w-5" aria-hidden="true" />
                </div>
              </div>
              <div className="mt-3">
                <p className="text-2xl font-semibold tabular-nums tracking-tight sm:text-3xl">
                  {data[card.key]}
                </p>
                <p className="mt-1 text-xs leading-4 text-muted-foreground">{card.description}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

export function PortalSummaryCardsSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
      {Array.from({ length: 4 }).map((_, i) => (
        <Card key={i}>
          <CardContent className="space-y-3 p-4 sm:p-5">
            <div className="flex items-start justify-between gap-2">
              <Skeleton className="h-4 w-3/5" />
              <Skeleton className="h-8 w-8 shrink-0 rounded-lg sm:h-10 sm:w-10" />
            </div>
            <Skeleton className="h-7 w-12 sm:h-8 sm:w-16" />
            <Skeleton className="h-3 w-4/5" />
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
