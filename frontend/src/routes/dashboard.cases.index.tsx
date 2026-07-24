import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery, keepPreviousData } from "@tanstack/react-query";
import { Eye, Inbox, Loader2, Search, SearchX, SlidersHorizontal } from "lucide-react";
import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { QueryErrorState } from "@/components/query-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { formatDateTime } from "@/lib/format";
import { formatCaseStatus, formatPriorityLevel, formatRiskLevel } from "@/lib/format-labels";
import { getCases, operationsQueryKeys } from "@/lib/operations-api";
import { EmptyState } from "@/components/empty-state";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { DEFAULT_PAGE_SIZE, normalizePageSize, type PageSize } from "@/lib/list-controls";
import { StatusBadge } from "@/components/status-badge";
import { OperationalScopeFilter } from "@/components/operational-scope-filter";
import { useAuth } from "@/hooks/use-auth";

const CASE_STATUSES = [
  "forwarded",
  "assessment",
  "investigation",
  "mediation",
  "recommendation",
  "decision",
  "decided",
  "recovery",
  "monitoring",
  "closed",
  "escalated",
] as const;

const CASE_QUICK_FILTERS = ["active", "pending_decision", "with_evidence"] as const;

type CaseStatusFilter = (typeof CASE_STATUSES)[number];
type CaseQuickFilter = (typeof CASE_QUICK_FILTERS)[number];

type CasesSearch = {
  q?: string;
  status?: CaseStatusFilter;
  quick_filter?: CaseQuickFilter;
  satgas_id?: number;
  assignment_status?: "unassigned";
  page?: number;
  per_page?: PageSize;
};

export const Route = createFileRoute("/dashboard/cases/")({
  validateSearch: (search: Record<string, unknown>): CasesSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    status: CASE_STATUSES.find((status) => status === search.status),
    quick_filter: CASE_QUICK_FILTERS.find((filter) => filter === search.quick_filter),
    satgas_id: positiveInteger(search.satgas_id),
    assignment_status: search.assignment_status === "unassigned" ? "unassigned" : undefined,
    page: positiveInteger(search.page),
    per_page: normalizePageSize(positiveInteger(search.per_page)),
  }),
  component: CasesPage,
  head: () => ({ meta: [{ title: "Cases - SILAPPKASAL Admin" }] }),
});

function positiveInteger(value: unknown): number | undefined {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function CasesPage() {
  const { roleCode } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const navigate = Route.useNavigate();
  const search = Route.useSearch();
  const q = search.q ?? "";
  const satgasId =
    search.assignment_status === "unassigned"
      ? "unassigned"
      : search.satgas_id
        ? String(search.satgas_id)
        : "all";
  const page = search.page ?? 1;
  const pageSize = search.per_page ?? DEFAULT_PAGE_SIZE;
  const status = search.status ?? "all";
  const quickFilter = search.quick_filter ?? "all";
  const scopeFilterActive = roleCode === "admin" && satgasId !== "all";
  const filtersActive =
    q !== "" ||
    status !== "all" ||
    quickFilter !== "all" ||
    scopeFilterActive;

  const updateFilters = (patch: Partial<CasesSearch>) => {
    void navigate({
      search: (current) => ({
        ...current,
        ...patch,
        page: undefined,
      }),
      replace: true,
    });
  };

  const resetFilters = () => void navigate({ search: {}, replace: true });

  const setStatus = (nextStatus: string) => {
    void navigate({
      search: (current) => ({
        ...current,
        status: nextStatus === "all" ? undefined : nextStatus as CaseStatusFilter,
      }),
      replace: true,
    });
  };

  const setQuickFilter = (nextFilter: string) => {
    void navigate({
      search: (current) => ({
        ...current,
        quick_filter: nextFilter === "all" ? undefined : nextFilter as CaseQuickFilter,
      }),
      replace: true,
    });
  };

  const query = useMemo(
    () => ({
      status: status === "all" ? undefined : status,
      quick_filter: quickFilter === "all" ? undefined : quickFilter,
      satgas_id:
        roleCode === "admin" && satgasId !== "all" && satgasId !== "unassigned"
          ? Number(satgasId)
          : undefined,
      assignment_status:
        roleCode === "admin" && satgasId === "unassigned" ? "unassigned" : undefined,
      per_page: pageSize,
      page,
    }),
    [roleCode, status, quickFilter, satgasId, pageSize, page],
  );
  const casesQuery = useQuery({
    queryKey: operationsQueryKeys.cases(query),
    queryFn: () => getCases(query),
    placeholderData: keepPreviousData,
  });
  const filtered =
    casesQuery.data?.data.filter((item) => {
      const haystack = `${item.case_number} ${item.registration_number} ${item.status_code} ${item.current_stage ?? ""}`.toLowerCase();
      return !q || haystack.includes(q.toLowerCase());
    }) ?? [];

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:cases.title") }]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:cases.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:cases.subtitle")}</p>
      </div>

      <Card>
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder={t("dashboard:cases.search")}
                value={q}
                onChange={(e) => updateFilters({ q: e.target.value || undefined })}
                className="pl-9"
              />
            </div>
            <SlidersHorizontal className="h-4 w-4 text-muted-foreground" />
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger className="w-[180px]"><SelectValue placeholder={t("dashboard:common.status")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("dashboard:common.allStatuses")}</SelectItem>
                {CASE_STATUSES.map((item) => (
                  <SelectItem key={item} value={item}>{formatCaseStatus(t, item)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={quickFilter} onValueChange={setQuickFilter}>
              <SelectTrigger className="w-[210px]">
                <SelectValue placeholder={t("dashboard:cases.quickFilters.label")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("dashboard:cases.quickFilters.all")}</SelectItem>
                {CASE_QUICK_FILTERS.map((item) => (
                  <SelectItem key={item} value={item}>
                    {t(`dashboard:cases.quickFilters.${item}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <OperationalScopeFilter
              roleCode={roleCode === "admin" ? roleCode : null}
              satgasId={satgasId}
              universityId="all"
              includeUnassigned
              onSatgasChange={(value) =>
                updateFilters({
                  satgas_id:
                    value !== "all" && value !== "unassigned" ? Number(value) : undefined,
                  assignment_status: value === "unassigned" ? "unassigned" : undefined,
                })
              }
              onUniversityChange={() => undefined}
            />
            <FilterResetButton active={filtersActive} onReset={resetFilters} />
            {casesQuery.isFetching && (
              <Loader2
                className="h-4 w-4 animate-spin text-muted-foreground"
                aria-label={t("dashboard:common.loading")}
              />
            )}
          </div>

          {casesQuery.isLoading && (
            <div className="py-12 text-center text-sm text-muted-foreground">{t("dashboard:cases.loading")}</div>
          )}
          {casesQuery.isError && (
            <QueryErrorState message={t("dashboard:cases.error")} onRetry={() => casesQuery.refetch()} />
          )}
          {casesQuery.isSuccess && (
            <>
              <div className="grid gap-3 md:hidden">
                {filtered.map((item) => (
                  <div key={item.id} className="rounded-lg border bg-background p-3 text-sm">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="truncate font-mono text-xs font-medium">{item.case_number}</div>
                        <div className="mt-1 font-mono text-xs text-muted-foreground">{item.registration_number}</div>
                      </div>
                      <StatusBadge status={item.status_code} className="shrink-0" />
                    </div>
                    <div className="mt-3 grid gap-2 text-xs">
                      <MobileField label={t("dashboard:common.risk")}>{formatRiskValue(t, item.risk_level ?? item.risk_level_code)}</MobileField>
                      <MobileField label={t("dashboard:common.priority")}>{formatPriorityValue(t, item.priority)}</MobileField>
                      <MobileField label={t("dashboard:common.forwarded")}>{formatDateTime(item.forwarded_at, i18n.language)}</MobileField>
                    </div>
                    <Button asChild size="sm" variant="outline" className="mt-3 w-full">
                      <Link to="/dashboard/cases/$id" params={{ id: String(item.id) }}>
                        <Eye />
                        {t("dashboard:common.detail")}
                      </Link>
                    </Button>
                  </div>
                ))}
                {filtered.length === 0 && (
                  filtersActive ? (
                    <EmptyState icon={SearchX} title={t("dashboard:cases.filteredEmptyTitle")} description={t("dashboard:cases.filteredEmptyDesc")} />
                  ) : (
                    <EmptyState icon={Inbox} title={t("dashboard:cases.emptyTitle")} description={t("dashboard:cases.emptyDesc")} />
                  )
                )}
              </div>
              <div className="hidden overflow-x-auto rounded-lg border md:block">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="px-3 py-2 text-left">{t("dashboard:cases.case")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:reports.registration")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.status")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.risk")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.priority")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.forwarded")}</th>
                      <th className="px-3 py-2"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {filtered.map((item) => (
                      <tr key={item.id} className="border-t hover:bg-muted/40">
                        <td className="px-3 py-2 font-mono text-xs">{item.case_number}</td>
                        <td className="px-3 py-2 font-mono text-xs">{item.registration_number}</td>
                        <td className="px-3 py-2"><StatusBadge status={item.status_code} /></td>
                        <td className="px-3 py-2">{formatRiskValue(t, item.risk_level ?? item.risk_level_code)}</td>
                        <td className="px-3 py-2">{formatPriorityValue(t, item.priority)}</td>
                        <td className="px-3 py-2 text-muted-foreground">{formatDateTime(item.forwarded_at, i18n.language)}</td>
                        <td className="px-3 py-2 text-right">
                          <Button asChild size="sm" variant="ghost">
                            <Link to="/dashboard/cases/$id" params={{ id: String(item.id) }}>
                              <Eye />
                              {t("dashboard:common.detail")}
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                    {filtered.length === 0 && (
                      <tr>
                        <td colSpan={7} className="p-0">
                          {filtersActive ? (
                            <EmptyState icon={SearchX} title={t("dashboard:cases.filteredEmptyTitle")} description={t("dashboard:cases.filteredEmptyDesc")} />
                          ) : (
                            <EmptyState icon={Inbox} title={t("dashboard:cases.emptyTitle")} description={t("dashboard:cases.emptyDesc")} />
                          )}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </>
          )}
          <ListPagination
            meta={casesQuery.data?.meta}
            page={page}
            pageSize={pageSize}
            onPageChange={(nextPage) =>
              void navigate({
                search: (current) => ({ ...current, page: nextPage }),
                replace: true,
              })
            }
            onPageSizeChange={(nextPageSize) =>
              updateFilters({ per_page: normalizePageSize(nextPageSize) })
            }
            isFetching={casesQuery.isFetching}
          />
        </CardContent>
      </Card>
    </div>
  );
}

function MobileField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-[11px] uppercase text-muted-foreground">{label}</div>
      <div className="mt-0.5 text-sm">{children}</div>
    </div>
  );
}

function formatRiskValue(t: ReturnType<typeof useTranslation>["t"], value: unknown) {
  if (!value) return "-";
  return formatRiskLevel(t, value);
}

function formatPriorityValue(t: ReturnType<typeof useTranslation>["t"], value: unknown) {
  if (!value) return "-";
  return formatPriorityLevel(t, value);
}
