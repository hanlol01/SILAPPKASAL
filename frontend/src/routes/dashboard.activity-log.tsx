import { keepPreviousData, useMutation, useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import {
  AlertTriangle,
  CheckCircle2,
  Clock3,
  Download,
  Eye,
  FileClock,
  Inbox,
  LoaderCircle,
  ShieldAlert,
  UserRoundCheck,
  UsersRound,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { AccessDenied } from "@/components/access-denied";
import { EmptyState } from "@/components/empty-state";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
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
} from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useAuth } from "@/hooks/use-auth";
import {
  auditQueryKeys,
  downloadAuditCsv,
  getAuditDetail,
  getAuditHistory,
  getOversightItems,
  getOversightSummary,
} from "@/lib/audit-api";
import type {
  AuditActorKind,
  AuditHistoryFilters,
  AuditLogEntry,
  AuditQueue,
  AuditResult,
  AuditUrgency,
  OversightItem,
} from "@/lib/audit-types";
import { apiErrorMessage } from "@/lib/form-errors";
import { formatDateTime } from "@/lib/format";
import {
  formatCaseStatus,
  formatDecisionStatus,
  formatRecommendationStatus,
  formatReportStatus,
  formatRoleLabel,
} from "@/lib/format-labels";
import { DEFAULT_PAGE_SIZE, normalizePageSize } from "@/lib/list-controls";
import { cn } from "@/lib/utils";

const TABS = ["attention", "history"] as const;
const QUEUES: AuditQueue[] = [
  "waiting_admin",
  "waiting_satgas",
  "waiting_leader",
  "emergency_access",
  "critical_security",
];
const URGENCIES: AuditUrgency[] = ["normal", "attention", "overdue"];
const CATEGORIES = [
  "auth",
  "report",
  "case",
  "investigation",
  "recommendation",
  "decision",
  "recovery",
  "evidence",
  "privacy",
  "security",
  "system",
] as const;
const SEVERITIES = ["info", "warning", "critical"] as const;
const RESULTS: AuditResult[] = ["succeeded", "failed", "denied"];
const ACTOR_KINDS: AuditActorKind[] = ["system", "reporter", "staff"];

type ActivityTab = (typeof TABS)[number];
type ActivitySearch = {
  tab?: ActivityTab;
  queue?: AuditQueue;
  urgency?: AuditUrgency;
  category?: (typeof CATEGORIES)[number];
  severity?: (typeof SEVERITIES)[number];
  result?: AuditResult;
  actor_kind?: AuditActorKind;
  is_elevated_access?: boolean;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
  cutoff?: string;
};

export const Route = createFileRoute("/dashboard/activity-log")({
  validateSearch: (search: Record<string, unknown>): ActivitySearch => ({
    tab: TABS.find((value) => value === search.tab),
    queue: QUEUES.find((value) => value === search.queue),
    urgency: URGENCIES.find((value) => value === search.urgency),
    category: CATEGORIES.find((value) => value === search.category),
    severity: SEVERITIES.find((value) => value === search.severity),
    result: RESULTS.find((value) => value === search.result),
    actor_kind: ACTOR_KINDS.find((value) => value === search.actor_kind),
    is_elevated_access: search.is_elevated_access === true || search.is_elevated_access === "true"
      ? true
      : undefined,
    date_from: validDateSearch(search.date_from),
    date_to: validDateSearch(search.date_to),
    page: positiveInteger(search.page),
    per_page: normalizePageSize(positiveInteger(search.per_page)),
    cutoff: validCutoffSearch(search.cutoff),
  }),
  component: ActivityLogPage,
  head: () => ({ meta: [{ title: "Activity Log - SILAPPKASAL" }] }),
});

function ActivityLogPage() {
  const { t, i18n } = useTranslation(["dashboard", "common"]);
  const { roleCode, user } = useAuth();
  const search = Route.useSearch();
  const navigate = Route.useNavigate();
  const documentVisible = useDocumentVisibility();
  const [selectedAuditId, setSelectedAuditId] = useState<string | null>(null);
  const tab = search.tab ?? "attention";
  const page = search.page ?? 1;
  const pageSize = search.per_page ?? DEFAULT_PAGE_SIZE;
  const canViewOversight = roleCode === "super_admin"
    && Boolean(user?.permissions?.includes("system.audit_log.oversight"));
  const canExport = roleCode === "super_admin"
    && Boolean(user?.permissions?.includes("system.audit_log.export"));
  const defaultDates = useMemo(() => defaultDateRange(), []);
  const historyFilters: AuditHistoryFilters = {
    category: search.category,
    severity: search.severity,
    result: search.result,
    actor_kind: search.actor_kind,
    is_elevated_access: search.is_elevated_access,
    date_from: search.date_from ?? defaultDates.from,
    date_to: search.date_to ?? defaultDates.to,
    page,
    per_page: pageSize,
    cutoff: search.cutoff,
  };

  const summaryQuery = useQuery({
    queryKey: auditQueryKeys.summary({ urgency: search.urgency }),
    queryFn: () => getOversightSummary({ urgency: search.urgency }),
    enabled: canViewOversight && tab === "attention",
    refetchInterval: tab === "attention" && documentVisible ? 30_000 : false,
    refetchOnWindowFocus: false,
  });
  const oversightFilters = {
    queue: search.queue,
    urgency: search.urgency,
    cutoff: summaryQuery.data?.generated_at,
    page,
    per_page: pageSize,
  };
  const oversightQuery = useQuery({
    queryKey: auditQueryKeys.oversight(oversightFilters),
    queryFn: () => getOversightItems(oversightFilters),
    enabled: canViewOversight && tab === "attention" && summaryQuery.isSuccess,
    placeholderData: keepPreviousData,
    refetchOnWindowFocus: false,
  });
  const historyQuery = useQuery({
    queryKey: auditQueryKeys.history(historyFilters),
    queryFn: () => getAuditHistory(historyFilters),
    enabled: canViewOversight && tab === "history",
    placeholderData: keepPreviousData,
    refetchInterval: false,
  });
  useEffect(() => {
    const cutoff = historyQuery.data?.meta.cutoff;

    if (tab === "history" && cutoff && !search.cutoff) {
      void navigate({
        search: (current) => ({ ...current, cutoff }),
        replace: true,
      });
    }
  }, [historyQuery.data?.meta.cutoff, navigate, search.cutoff, tab]);
  const exportMutation = useMutation({
    mutationFn: () => downloadAuditCsv(historyFilters),
    onSuccess: () => toast.success(t("dashboard:activityLog.actions.exportSuccess")),
    onError: (error) => toast.error(apiErrorMessage(error, t("dashboard:activityLog.actions.exportError"))),
  });

  if (!canViewOversight) {
    return <AccessDenied />;
  }

  const updateSearch = (changes: Partial<ActivitySearch>) => {
    const resetsHistorySnapshot = [
      "category",
      "severity",
      "result",
      "actor_kind",
      "is_elevated_access",
      "date_from",
      "date_to",
    ].some((key) => Object.prototype.hasOwnProperty.call(changes, key));

    void navigate({
      search: (current) => ({
        ...current,
        ...changes,
        ...(resetsHistorySnapshot ? { cutoff: undefined } : {}),
      }),
      replace: true,
    });
  };
  const resetHistoryFilters = () => updateSearch({
    category: undefined,
    severity: undefined,
    result: undefined,
    actor_kind: undefined,
    is_elevated_access: undefined,
    date_from: undefined,
    date_to: undefined,
    page: 1,
  });
  const historyFiltersActive = Boolean(
    search.category
    || search.severity
    || search.result
    || search.actor_kind
    || search.is_elevated_access
    || search.date_from
    || search.date_to,
  );

  return (
    <div className="min-w-0 space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:activityLog.title") }]} />
      <div>
        <h1 className="text-2xl font-semibold">{t("dashboard:activityLog.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:activityLog.subtitle")}</p>
      </div>

      <Tabs
        value={tab}
        onValueChange={(value) => updateSearch({ tab: value as ActivityTab, page: 1 })}
        className="min-w-0"
      >
        <div className="w-full min-w-0 overflow-x-auto">
          <TabsList className="h-auto min-w-max flex-nowrap justify-start">
            <TabsTrigger value="attention" className="shrink-0">
              {t("dashboard:activityLog.tabs.attention")}
            </TabsTrigger>
            <TabsTrigger value="history" className="shrink-0">
              {t("dashboard:activityLog.tabs.history")}
            </TabsTrigger>
          </TabsList>
        </div>

        <TabsContent value="attention" className="space-y-5">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="max-w-3xl text-sm text-muted-foreground">
              {t("dashboard:activityLog.clock.caption")}
            </p>
            <Select
              value={search.urgency ?? "all"}
              onValueChange={(value) => updateSearch({
                urgency: value === "all" ? undefined : value as AuditUrgency,
                page: 1,
              })}
            >
              <SelectTrigger className="w-full sm:w-56">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("dashboard:activityLog.urgencies.all")}</SelectItem>
                {URGENCIES.map((urgency) => (
                  <SelectItem key={urgency} value={urgency}>
                    {t(`dashboard:activityLog.urgencies.${urgency}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {summaryQuery.isLoading && <QueueCardsSkeleton />}
          {summaryQuery.isError && (
            <QueryErrorState
              message={t("dashboard:activityLog.states.oversightError")}
              onRetry={() => summaryQuery.refetch()}
            />
          )}
          {summaryQuery.data && (
            <div className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-5">
              {QUEUES.map((queue) => (
                <QueueCard
                  key={queue}
                  queue={queue}
                  count={summaryQuery.data.queues[queue] ?? 0}
                  active={search.queue === queue}
                  onClick={() => updateSearch({ queue: search.queue === queue ? undefined : queue, page: 1 })}
                />
              ))}
            </div>
          )}

          <Card className="min-w-0">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">
                {search.queue
                  ? t(`dashboard:activityLog.queues.${search.queue}`)
                  : t("dashboard:activityLog.tabs.attention")}
              </CardTitle>
              <CardDescription>
                {search.queue
                  ? t(`dashboard:activityLog.queueDescriptions.${search.queue}`)
                  : t("dashboard:activityLog.clock.caption")}
              </CardDescription>
            </CardHeader>
            <CardContent className="min-w-0 space-y-3">
              {(summaryQuery.isLoading || oversightQuery.isLoading) && <ListSkeleton />}
              {oversightQuery.isError && (
                <QueryErrorState
                  message={t("dashboard:activityLog.states.oversightError")}
                  onRetry={() => oversightQuery.refetch()}
                />
              )}
              {oversightQuery.data?.data.length === 0 && (
                <EmptyState
                  icon={Inbox}
                  title={t("dashboard:activityLog.states.oversightEmptyTitle")}
                  description={t("dashboard:activityLog.states.oversightEmptyDescription")}
                />
              )}
              {oversightQuery.data && oversightQuery.data.data.length > 0 && (
                <OversightList items={oversightQuery.data.data} language={i18n.resolvedLanguage} />
              )}
              <ListPagination
                meta={oversightQuery.data?.meta}
                page={page}
                pageSize={pageSize}
                onPageChange={(nextPage) => updateSearch({ page: nextPage })}
                onPageSizeChange={(size) => updateSearch({ per_page: size, page: 1 })}
                isFetching={oversightQuery.isFetching}
              />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="history" className="space-y-4">
          <Card>
            <CardContent className="space-y-4 p-4">
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SelectFilter
                  value={search.category}
                  placeholder={t("dashboard:activityLog.filters.allCategories")}
                  options={CATEGORIES.map((value) => ({
                    value,
                    label: t(`dashboard:activityLog.categories.${value}`),
                  }))}
                  onValueChange={(value) => updateSearch({ category: value as ActivitySearch["category"], page: 1 })}
                />
                <SelectFilter
                  value={search.severity}
                  placeholder={t("dashboard:activityLog.filters.allSeverities")}
                  options={SEVERITIES.map((value) => ({
                    value,
                    label: t(`dashboard:activityLog.severities.${value}`),
                  }))}
                  onValueChange={(value) => updateSearch({ severity: value as ActivitySearch["severity"], page: 1 })}
                />
                <SelectFilter
                  value={search.result}
                  placeholder={t("dashboard:activityLog.filters.allResults")}
                  options={RESULTS.map((value) => ({
                    value,
                    label: t(`dashboard:activityLog.results.${value}`),
                  }))}
                  onValueChange={(value) => updateSearch({ result: value as AuditResult | undefined, page: 1 })}
                />
                <SelectFilter
                  value={search.actor_kind}
                  placeholder={t("dashboard:activityLog.filters.allActors")}
                  options={ACTOR_KINDS.map((value) => ({
                    value,
                    label: t(`dashboard:activityLog.actorKinds.${value}`),
                  }))}
                  onValueChange={(value) => updateSearch({ actor_kind: value as AuditActorKind | undefined, page: 1 })}
                />
                <label className="space-y-1 text-sm">
                  <span className="text-muted-foreground">{t("dashboard:activityLog.filters.dateFrom")}</span>
                  <Input
                    type="date"
                    value={historyFilters.date_from}
                    max={historyFilters.date_to}
                    onChange={(event) => updateSearch({ date_from: event.target.value || undefined, page: 1 })}
                  />
                </label>
                <label className="space-y-1 text-sm">
                  <span className="text-muted-foreground">{t("dashboard:activityLog.filters.dateTo")}</span>
                  <Input
                    type="date"
                    value={historyFilters.date_to}
                    min={historyFilters.date_from}
                    onChange={(event) => updateSearch({ date_to: event.target.value || undefined, page: 1 })}
                  />
                </label>
                <label className="flex min-h-10 items-center gap-2 self-end rounded-md border px-3 text-sm">
                  <Checkbox
                    checked={Boolean(search.is_elevated_access)}
                    onCheckedChange={(checked) => updateSearch({
                      is_elevated_access: checked === true ? true : undefined,
                      page: 1,
                    })}
                  />
                  {t("dashboard:activityLog.filters.elevatedOnly")}
                </label>
                <div className="flex flex-wrap items-end justify-end gap-2">
                  <FilterResetButton active={historyFiltersActive} onReset={resetHistoryFilters} />
                  {canExport && (
                    <Button
                      variant="outline"
                      onClick={() => exportMutation.mutate()}
                      disabled={exportMutation.isPending}
                    >
                      {exportMutation.isPending
                        ? <LoaderCircle className="h-4 w-4 motion-safe:animate-spin" />
                        : <Download className="h-4 w-4" />}
                      {exportMutation.isPending
                        ? t("dashboard:activityLog.actions.exporting")
                        : t("dashboard:activityLog.actions.exportCsv")}
                    </Button>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className="min-w-0">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">{t("dashboard:activityLog.history.title")}</CardTitle>
              <CardDescription>{t("dashboard:activityLog.history.description")}</CardDescription>
            </CardHeader>
            <CardContent className="min-w-0 space-y-3">
              {historyQuery.isLoading && <ListSkeleton />}
              {historyQuery.isError && (
                <QueryErrorState
                  message={t("dashboard:activityLog.states.historyError")}
                  onRetry={() => historyQuery.refetch()}
                />
              )}
              {historyQuery.data?.data.length === 0 && (
                <EmptyState
                  icon={FileClock}
                  title={t("dashboard:activityLog.states.historyEmptyTitle")}
                  description={t("dashboard:activityLog.states.historyEmptyDescription")}
                />
              )}
              {historyQuery.data && historyQuery.data.data.length > 0 && (
                <AuditHistoryList
                  entries={historyQuery.data.data}
                  language={i18n.resolvedLanguage}
                  onSelect={setSelectedAuditId}
                />
              )}
              <ListPagination
                meta={historyQuery.data?.meta}
                page={page}
                pageSize={pageSize}
                onPageChange={(nextPage) => updateSearch({ page: nextPage })}
                onPageSizeChange={(size) => updateSearch({ per_page: size, page: 1 })}
                isFetching={historyQuery.isFetching}
              />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <AuditDetailSheet
        publicId={selectedAuditId}
        open={selectedAuditId !== null}
        onOpenChange={(open) => {
          if (!open) setSelectedAuditId(null);
        }}
      />
    </div>
  );
}

function QueueCard({ queue, count, active, onClick }: {
  queue: AuditQueue;
  count: number;
  active: boolean;
  onClick: () => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  const Icon = queueIcon(queue);

  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "min-w-0 rounded-lg border bg-card p-4 text-left shadow-sm transition-colors hover:border-primary/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        active && "border-primary bg-primary/5",
      )}
    >
      <div className="flex min-w-0 items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="break-words text-sm font-medium">{t(`dashboard:activityLog.queues.${queue}`)}</p>
          <p className="mt-2 text-2xl font-semibold tabular-nums">{count}</p>
        </div>
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-muted">
          <Icon className="h-4 w-4" aria-hidden="true" />
        </span>
      </div>
    </button>
  );
}

function OversightList({ items, language }: { items: OversightItem[]; language?: string }) {
  const { t } = useTranslation(["dashboard"]);

  return (
    <>
      <div className="hidden overflow-x-auto rounded-lg border md:block">
        <table className="w-full min-w-[800px] text-sm">
          <thead className="bg-muted/60 text-left">
            <tr>
              <th className="p-3">{t("dashboard:activityLog.table.reference")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.work")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.queue")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.urgency")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.elapsed")}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={`${item.queue}:${item.work_type}:${item.reference}`} className="border-t">
                <td className="max-w-56 break-words p-3 font-mono text-xs">{item.reference}</td>
                <td className="p-3">
                  <div className="font-medium">{t(`dashboard:activityLog.workTypes.${item.work_type}`)}</div>
                  <div className="text-xs text-muted-foreground">{oversightStatusLabel(t, item)}</div>
                </td>
                <td className="p-3">{t(`dashboard:activityLog.queues.${item.queue}`)}</td>
                <td className="p-3"><UrgencyBadge urgency={item.urgency} /></td>
                <td className="p-3">
                  <div>{t("dashboard:activityLog.clock.elapsed", { days: item.elapsed_business_days.toFixed(1) })}</div>
                  <div className="text-xs text-muted-foreground">
                    {t("dashboard:activityLog.clock.progress", { percent: Math.round(item.progress_percent) })}
                    {" · "}{formatDateTime(item.started_at, language)}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="space-y-2 md:hidden">
        {items.map((item) => (
          <div key={`${item.queue}:${item.work_type}:${item.reference}`} className="min-w-0 rounded-lg border p-3">
            <div className="flex min-w-0 items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="break-all font-mono text-xs text-muted-foreground">{item.reference}</p>
                <p className="mt-1 break-words text-sm font-medium">
                  {t(`dashboard:activityLog.workTypes.${item.work_type}`)}
                </p>
              </div>
              <UrgencyBadge urgency={item.urgency} />
            </div>
            <div className="mt-3 grid gap-1 text-xs text-muted-foreground">
              <span>{t(`dashboard:activityLog.queues.${item.queue}`)}</span>
              <span>{oversightStatusLabel(t, item)}</span>
              <span>{t("dashboard:activityLog.clock.elapsed", { days: item.elapsed_business_days.toFixed(1) })}</span>
              <span>{t("dashboard:activityLog.clock.progress", { percent: Math.round(item.progress_percent) })}</span>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}

function AuditHistoryList({ entries, language, onSelect }: {
  entries: AuditLogEntry[];
  language?: string;
  onSelect: (publicId: string) => void;
}) {
  const { t } = useTranslation(["dashboard"]);

  return (
    <>
      <div className="hidden overflow-x-auto rounded-lg border md:block">
        <table className="w-full min-w-[860px] text-sm">
          <thead className="bg-muted/60 text-left">
            <tr>
              <th className="p-3">{t("dashboard:activityLog.table.time")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.action")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.actor")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.subject")}</th>
              <th className="p-3">{t("dashboard:activityLog.table.result")}</th>
              <th className="p-3 text-right"><span className="sr-only">{t("dashboard:common.actions")}</span></th>
            </tr>
          </thead>
          <tbody>
            {entries.map((entry) => (
              <tr key={entry.public_id} className="border-t">
                <td className="whitespace-nowrap p-3 text-muted-foreground">{formatDateTime(entry.created_at, language)}</td>
                <td className="p-3">
                  <div className="font-medium">{auditActionLabel(t, entry.action)}</div>
                  <div className="text-xs text-muted-foreground">{categoryLabel(t, entry.category)}</div>
                </td>
                <td className="max-w-48 break-words p-3">{localizedActorLabel(t, entry.actor)}</td>
                <td className="max-w-48 break-words p-3">{entry.subject.reference ?? subjectKindLabel(t, entry.subject.kind)}</td>
                <td className="p-3"><ResultBadge result={entry.result} /></td>
                <td className="p-3 text-right">
                  <Button variant="ghost" size="icon" onClick={() => onSelect(entry.public_id)} title={t("dashboard:activityLog.actions.viewDetail")}>
                    <Eye className="h-4 w-4" />
                    <span className="sr-only">{t("dashboard:activityLog.actions.viewDetail")}</span>
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="space-y-2 md:hidden">
        {entries.map((entry) => (
          <button
            key={entry.public_id}
            type="button"
            onClick={() => onSelect(entry.public_id)}
            className="w-full min-w-0 rounded-lg border p-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <div className="flex min-w-0 items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="break-words text-sm font-medium">{auditActionLabel(t, entry.action)}</p>
                <p className="mt-1 text-xs text-muted-foreground">{formatDateTime(entry.created_at, language)}</p>
              </div>
              <ResultBadge result={entry.result} />
            </div>
            <div className="mt-3 grid gap-1 text-xs text-muted-foreground">
              <span className="break-words">{localizedActorLabel(t, entry.actor)}</span>
              <span className="break-all">{entry.subject.reference ?? subjectKindLabel(t, entry.subject.kind)}</span>
            </div>
          </button>
        ))}
      </div>
    </>
  );
}

function AuditDetailSheet({ publicId, open, onOpenChange }: {
  publicId: string | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t, i18n } = useTranslation(["dashboard", "common"]);
  const query = useQuery({
    queryKey: auditQueryKeys.detail(publicId),
    queryFn: () => getAuditDetail(publicId!),
    enabled: open && publicId !== null,
    retry: false,
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full min-w-0 overflow-y-auto sm:max-w-xl">
        <SheetHeader className="pr-8">
          <SheetTitle>{t("dashboard:activityLog.detail.title")}</SheetTitle>
          <SheetDescription>{t("dashboard:activityLog.detail.description")}</SheetDescription>
        </SheetHeader>
        {query.isLoading && <ListSkeleton />}
        {query.isError && (
          <QueryErrorState
            message={t("dashboard:activityLog.states.detailError")}
            onRetry={() => query.refetch()}
          />
        )}
        {query.data && (
          <div className="mt-6 min-w-0 space-y-6">
            <dl className="grid min-w-0 gap-4 text-sm sm:grid-cols-2">
              <DetailField label={t("dashboard:activityLog.detail.publicId")} value={query.data.public_id} mono />
              <DetailField label={t("dashboard:activityLog.detail.requestId")} value={query.data.request_id ?? "-"} mono />
              <DetailField label={t("dashboard:activityLog.table.time")} value={formatDateTime(query.data.created_at, i18n.resolvedLanguage)} />
              <DetailField label={t("dashboard:activityLog.table.action")} value={auditActionLabel(t, query.data.action)} />
              <DetailField label={t("dashboard:activityLog.detail.category")} value={categoryLabel(t, query.data.category)} />
              <DetailField label={t("dashboard:activityLog.detail.severity")} value={severityLabel(t, query.data.severity)} />
              <DetailField label={t("dashboard:activityLog.detail.actor")} value={localizedActorLabel(t, query.data.actor)} />
              <DetailField label={t("dashboard:activityLog.detail.actorRole")} value={formatRoleLabel(t, query.data.actor.role_code)} />
              <DetailField label={t("dashboard:activityLog.detail.subject")} value={query.data.subject.reference ?? subjectKindLabel(t, query.data.subject.kind)} />
              <DetailField label={t("dashboard:activityLog.detail.emergencyAccess")} value={query.data.is_elevated_access ? t("common:yes") : t("common:no")} />
            </dl>
            <SafeMap title={t("dashboard:activityLog.detail.metadata")} values={query.data.metadata} />
            <div className="space-y-3">
              <h3 className="text-sm font-semibold">{t("dashboard:activityLog.detail.changes")}</h3>
              <SafeMap title={t("dashboard:activityLog.detail.before")} values={query.data.changes.before} />
              <SafeMap title={t("dashboard:activityLog.detail.after")} values={query.data.changes.after} />
            </div>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

function SafeMap({ title, values }: { title: string; values: AuditLogEntry["metadata"] }) {
  const { t } = useTranslation(["dashboard"]);
  const entries = Object.entries(values);

  return (
    <section className="min-w-0 space-y-2">
      <h3 className="text-sm font-semibold">{title}</h3>
      {entries.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("dashboard:activityLog.detail.none")}</p>
      ) : (
        <dl className="min-w-0 divide-y rounded-lg border text-sm">
          {entries.map(([key, value]) => (
            <div key={key} className="grid min-w-0 gap-1 p-3 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
              <dt className="break-words text-muted-foreground">{safeFieldLabel(t, key)}</dt>
              <dd className="min-w-0 break-words [overflow-wrap:anywhere] sm:text-right">{safeScalar(value, t("common:yes"), t("common:no"))}</dd>
            </div>
          ))}
        </dl>
      )}
    </section>
  );
}

function DetailField({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs uppercase text-muted-foreground">{label}</dt>
      <dd className={cn("mt-1 min-w-0 break-words [overflow-wrap:anywhere]", mono && "font-mono text-xs")}>{value}</dd>
    </div>
  );
}

function SelectFilter({ value, placeholder, options, onValueChange }: {
  value?: string;
  placeholder: string;
  options: Array<{ value: string; label: string }>;
  onValueChange: (value: string | undefined) => void;
}) {
  return (
    <Select value={value ?? "all"} onValueChange={(next) => onValueChange(next === "all" ? undefined : next)}>
      <SelectTrigger><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem value="all">{placeholder}</SelectItem>
        {options.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
      </SelectContent>
    </Select>
  );
}

function UrgencyBadge({ urgency }: { urgency: AuditUrgency }) {
  const { t } = useTranslation(["dashboard"]);
  const Icon = urgency === "overdue" ? AlertTriangle : urgency === "attention" ? Clock3 : CheckCircle2;
  return (
    <Badge variant="outline" className={cn(
      "max-w-full shrink-0 gap-1 whitespace-normal text-left",
      urgency === "overdue" && "border-destructive/40 bg-destructive/10 text-destructive",
      urgency === "attention" && "border-warning/40 bg-warning/10 text-warning-foreground dark:text-warning",
      urgency === "normal" && "border-success/40 bg-success/10 text-success",
    )}>
      <Icon className="h-3 w-3 shrink-0" />
      {t(`dashboard:activityLog.urgencies.${urgency}`)}
    </Badge>
  );
}

function ResultBadge({ result }: { result: AuditResult }) {
  const { t } = useTranslation(["dashboard"]);
  return (
    <Badge variant="outline" className={cn(
      result === "succeeded" && "border-success/40 bg-success/10 text-success",
      result === "failed" && "border-destructive/40 bg-destructive/10 text-destructive",
      result === "denied" && "border-warning/40 bg-warning/10 text-warning-foreground dark:text-warning",
    )}>
      {t(`dashboard:activityLog.results.${result}`)}
    </Badge>
  );
}

function QueueCardsSkeleton() {
  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      {QUEUES.map((queue) => <Skeleton key={queue} className="h-28 rounded-lg" />)}
    </div>
  );
}

function ListSkeleton() {
  return (
    <div className="space-y-2 py-3">
      {Array.from({ length: 5 }).map((_, index) => <Skeleton key={index} className="h-14 w-full rounded-md" />)}
    </div>
  );
}

function useDocumentVisibility() {
  const [visible, setVisible] = useState(() => typeof document === "undefined" || document.visibilityState === "visible");

  useEffect(() => {
    const onVisibilityChange = () => setVisible(document.visibilityState === "visible");
    document.addEventListener("visibilitychange", onVisibilityChange);
    return () => document.removeEventListener("visibilitychange", onVisibilityChange);
  }, []);

  return visible;
}

function queueIcon(queue: AuditQueue) {
  if (queue === "waiting_admin") return UserRoundCheck;
  if (queue === "waiting_satgas") return UsersRound;
  if (queue === "waiting_leader") return FileClock;
  if (queue === "emergency_access") return ShieldAlert;
  return AlertTriangle;
}

function oversightStatusLabel(t: ReturnType<typeof useTranslation>["t"], item: OversightItem) {
  if (item.work_type === "report_verification") return formatReportStatus(t, item.status);
  if (item.work_type === "case_assignment" || item.work_type === "satgas_case") return formatCaseStatus(t, item.status);
  if (item.work_type === "recommendation_review") return formatRecommendationStatus(t, item.status);
  if (item.work_type === "decision_handoff") return formatDecisionStatus(t, item.status);
  if (item.work_type === "emergency_access") return t("dashboard:breakGlass.status.pending");
  if (item.work_type === "critical_security") return t(`dashboard:activityLog.results.${item.status}`, { defaultValue: item.status });
  return t("dashboard:common.notAvailable");
}

function auditActionLabel(t: ReturnType<typeof useTranslation>["t"], action: string) {
  const key = action.replace(/[.]/g, "_");
  return t(`dashboard:activityLog.actionLabels.${key}`, {
    defaultValue: t("dashboard:activityLog.actionLabels.unknown"),
  });
}

function categoryLabel(t: ReturnType<typeof useTranslation>["t"], category: string) {
  return t(`dashboard:activityLog.categories.${category}`, { defaultValue: category });
}

function severityLabel(t: ReturnType<typeof useTranslation>["t"], severity: string) {
  return t(`dashboard:activityLog.severities.${severity}`, { defaultValue: severity });
}

function subjectKindLabel(t: ReturnType<typeof useTranslation>["t"], kind: string | null) {
  if (!kind) return t("dashboard:common.notAvailable");
  return t(`dashboard:activityLog.subjectKinds.${kind}`, { defaultValue: t("dashboard:common.notAvailable") });
}

function localizedActorLabel(
  t: ReturnType<typeof useTranslation>["t"],
  actor: AuditLogEntry["actor"],
) {
  if (actor.kind === "staff") {
    return actor.display_name_safe ?? formatRoleLabel(t, actor.role_code);
  }

  return t(`dashboard:activityLog.actorKinds.${actor.kind}`);
}

function safeFieldLabel(t: ReturnType<typeof useTranslation>["t"], value: string) {
  return t(`dashboard:activityLog.fieldLabels.${value}`, {
    defaultValue: t("dashboard:common.notAvailable"),
  });
}

function safeScalar(value: string | number | boolean | null, yes: string, no: string) {
  if (value === null) return "-";
  if (typeof value === "boolean") return value ? yes : no;
  return String(value);
}

function validDateSearch(value: unknown) {
  return typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : undefined;
}

function validCutoffSearch(value: unknown) {
  return typeof value === "string" && value.length <= 40 && !Number.isNaN(Date.parse(value))
    ? value
    : undefined;
}

function positiveInteger(value: unknown) {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function defaultDateRange() {
  const to = new Date();
  const from = new Date(to);
  from.setDate(from.getDate() - 29);
  return { from: localDateValue(from), to: localDateValue(to) };
}

function localDateValue(value: Date) {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, "0");
  const day = String(value.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
