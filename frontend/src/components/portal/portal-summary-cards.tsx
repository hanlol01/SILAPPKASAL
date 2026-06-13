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
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map((card) => (
        <Card key={card.key} className="overflow-hidden">
          <CardContent className="p-5">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-muted-foreground">{card.label}</p>
                <p className="mt-2 text-3xl font-semibold tracking-tight">
                  {data[card.key]}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {card.description}
                </p>
              </div>
              <div
                className={`flex h-10 w-10 items-center justify-center rounded-lg ${card.tone}`}
              >
                <card.icon className="h-5 w-5" />
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
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }).map((_, i) => (
        <Card key={i}>
          <CardContent className="space-y-3 p-5">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-8 w-16" />
            <Skeleton className="h-3 w-28" />
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
