import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link, Outlet, useMatchRoute } from "@tanstack/react-router";
import { Clock3, Eye, Inbox, Loader2, Search, SearchX } from "lucide-react";
import { useTranslation } from "react-i18next";
import { AccessDenied } from "@/components/access-denied";
import { EmptyState } from "@/components/empty-state";
import { ListPagination } from "@/components/list-pagination";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { formatDateTime } from "@/lib/format";
import { DEFAULT_PAGE_SIZE, normalizePageSize, type PageSize } from "@/lib/list-controls";
import { getReportWithdrawalReviews, operationsQueryKeys } from "@/lib/operations-api";
import type { ReportWithdrawalStatus } from "@/lib/operations-types";

const FILTERS = ["pending_review", "approved", "rejected", "cancelled", "all"] as const;
type WithdrawalFilter = (typeof FILTERS)[number];
type WithdrawalSearch = { status?: WithdrawalFilter; q?: string; page?: number; per_page?: PageSize };

export const Route = createFileRoute("/dashboard/report-withdrawals")({
  validateSearch: (search: Record<string, unknown>): WithdrawalSearch => ({
    status: FILTERS.find((value) => value === search.status),
    q: typeof search.q === "string" && search.q.trim() ? search.q.slice(0, 64) : undefined,
    page: positiveInteger(search.page),
    per_page: normalizePageSize(positiveInteger(search.per_page)),
  }),
  component: ReportWithdrawalsPage,
  head: () => ({ meta: [{ title: "Complaint Withdrawal Review - SILAPPKASAL" }] }),
});

function positiveInteger(value: unknown) {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function ReportWithdrawalsPage() {
  const { roleCode, user } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const matchRoute = useMatchRoute();
  const search = Route.useSearch();
  const navigate = Route.useNavigate();
  const isIndexRoute = Boolean(
    matchRoute({ to: "/dashboard/report-withdrawals", fuzzy: false }),
  );
  const status = search.status ?? "all";
  const page = search.page ?? 1;
  const pageSize = search.per_page ?? DEFAULT_PAGE_SIZE;
  const canAccess =
    (roleCode === "admin" && user?.permissions?.includes("reports.withdraw.review.own_campus")) ||
    (roleCode === "super_admin" && user?.permissions?.includes("reports.read.all"));
  const query = { status, search: search.q, page, per_page: pageSize };
  const withdrawalsQuery = useQuery({
    queryKey: operationsQueryKeys.withdrawalReviews(query),
    queryFn: () => getReportWithdrawalReviews(query),
    enabled: Boolean(canAccess && isIndexRoute),
  });

  if (!canAccess) return <AccessDenied />;
  if (!isIndexRoute) return <Outlet />;

  const filtered = status !== "all" || Boolean(search.q);

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:withdrawals.title") }]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:withdrawals.title")}</h1>
        <p className="text-sm text-muted-foreground">
          {t(
            roleCode === "super_admin"
              ? "dashboard:withdrawals.monitoringSubtitle"
              : "dashboard:withdrawals.subtitle",
          )}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("dashboard:withdrawals.queue")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_240px]">
            <label className="relative">
              <span className="sr-only">{t("dashboard:withdrawals.search")}</span>
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                className="pl-9"
                value={search.q ?? ""}
                placeholder={t("dashboard:withdrawals.search")}
                onChange={(event) =>
                  void navigate({
                    search: (current) => ({ ...current, q: event.target.value || undefined, page: undefined }),
                    replace: true,
                  })
                }
              />
            </label>
            <Select
              value={status}
              onValueChange={(value: WithdrawalFilter) =>
                void navigate({ search: (current) => ({ ...current, status: value, page: undefined }), replace: true })
              }
            >
              <SelectTrigger aria-label={t("dashboard:common.status")}><SelectValue /></SelectTrigger>
              <SelectContent>
                {FILTERS.map((value) => (
                  <SelectItem key={value} value={value}>{t(`dashboard:withdrawals.filters.${value}`)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {withdrawalsQuery.isFetching && !withdrawalsQuery.isPending && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground" role="status">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              {t("dashboard:common.loading")}
            </div>
          )}
          {withdrawalsQuery.isPending ? (
            <div className="flex min-h-40 items-center justify-center gap-2 text-sm text-muted-foreground" role="status">
              <Loader2 className="h-5 w-5 animate-spin" aria-hidden="true" />
              {t("dashboard:common.loading")}
            </div>
          ) : withdrawalsQuery.isError ? (
            <QueryErrorState message={t("dashboard:withdrawals.loadError")} onRetry={() => withdrawalsQuery.refetch()} />
          ) : withdrawalsQuery.data?.data.length ? (
            <div className="grid gap-3">
              {withdrawalsQuery.data.data.map((item) => (
                <article key={item.withdrawal_reference} className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_auto] md:items-center">
                  <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="break-all font-mono text-sm font-semibold">{item.registration_number}</span>
                      <WithdrawalBadge status={item.status} />
                    </div>
                    <div className="grid gap-1 text-sm text-muted-foreground sm:grid-cols-2 lg:grid-cols-4">
                      {item.reporter_display_name && <span>{item.reporter_display_name}</span>}
                      <span>{item.campus?.name ?? t("dashboard:common.metadataUnavailable")}</span>
                      <span>{formatDateTime(item.submitted_at, i18n.language)}</span>
                      {item.status === "pending_review" && item.elapsed_waiting_seconds !== null && (
                        <span className="inline-flex items-center gap-1"><Clock3 className="h-3.5 w-3.5" />{formatElapsed(item.elapsed_waiting_seconds, t)}</span>
                      )}
                    </div>
                  </div>
                  <Button asChild variant="outline" size="sm">
                    <Link to="/dashboard/report-withdrawals/$publicId" params={{ publicId: item.withdrawal_reference }}>
                      <Eye className="h-4 w-4" /> {t("dashboard:common.detail")}
                    </Link>
                  </Button>
                </article>
              ))}
            </div>
          ) : (
            <EmptyState
              icon={filtered ? SearchX : Inbox}
              title={filtered ? t("dashboard:withdrawals.filteredEmpty") : t("dashboard:withdrawals.empty")}
              description={t(
                filtered
                  ? "dashboard:withdrawals.filteredEmptyDescription"
                  : "dashboard:withdrawals.emptyDescription",
              )}
            />
          )}

          <ListPagination
            meta={withdrawalsQuery.data?.meta}
            page={page}
            pageSize={pageSize}
            isFetching={withdrawalsQuery.isFetching}
            onPageChange={(next) => void navigate({ search: (current) => ({ ...current, page: next }), replace: true })}
            onPageSizeChange={(next) => void navigate({ search: (current) => ({ ...current, per_page: normalizePageSize(next), page: undefined }), replace: true })}
          />
        </CardContent>
      </Card>
    </div>
  );
}

export function WithdrawalBadge({ status }: { status: ReportWithdrawalStatus }) {
  const { t } = useTranslation(["dashboard"]);
  return <Badge variant={status === "approved" ? "default" : status === "rejected" ? "destructive" : "outline"}>{t(`dashboard:withdrawals.status.${status}`)}</Badge>;
}

function formatElapsed(seconds: number, t: ReturnType<typeof useTranslation>["t"]) {
  const hours = Math.floor(seconds / 3600);
  const days = Math.floor(hours / 24);
  return days > 0
    ? t("dashboard:withdrawals.waitingDays", { count: days })
    : t("dashboard:withdrawals.waitingHours", { count: Math.max(1, hours) });
}
