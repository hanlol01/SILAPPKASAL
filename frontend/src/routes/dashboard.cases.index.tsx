import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery, keepPreviousData } from "@tanstack/react-query";
import { Eye, Inbox, Search, SearchX, SlidersHorizontal } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { QueryErrorState } from "@/components/query-state";
import { Badge } from "@/components/ui/badge";
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
import { formatCaseStatus } from "@/lib/format-labels";
import { getCases, operationsQueryKeys } from "@/lib/operations-api";
import { EmptyState } from "@/components/empty-state";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";

export const Route = createFileRoute("/dashboard/cases/")({
  component: CasesPage,
  head: () => ({ meta: [{ title: "Cases - SILAPPKASAL Admin" }] }),
});

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
];

function CasesPage() {
  const { t, i18n } = useTranslation(["dashboard"]);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(DEFAULT_PAGE_SIZE);
  const filtersActive = q !== "" || status !== "all";

  const resetFilters = () => {
    setQ("");
    setStatus("all");
    setPage(1);
  };

  useEffect(() => {
    setPage(1);
  }, [status, pageSize]);

  const query = useMemo(
    () => ({
      status: status === "all" ? undefined : status,
      per_page: pageSize,
      page,
    }),
    [status, pageSize, page],
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
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:cases.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:cases.subtitle")}</p>
        </div>
        <Button disabled variant="outline" title={t("dashboard:cases.assignmentUnavailableTitle")}>
          {t("dashboard:cases.assignmentUnavailable")}
        </Button>
      </div>

      <Card>
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder={t("dashboard:cases.search")}
                value={q}
                onChange={(e) => setQ(e.target.value)}
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
            <FilterResetButton active={filtersActive} onReset={resetFilters} />
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
                      <Badge variant="outline" className="shrink-0">{formatCaseStatus(t, item.status_code)}</Badge>
                    </div>
                    <div className="mt-3 grid gap-2 text-xs">
                      <MobileField label={t("dashboard:common.risk")}>{item.risk_level ?? item.risk_level_code ?? "-"}</MobileField>
                      <MobileField label={t("dashboard:common.priority")}>{item.priority ?? "-"}</MobileField>
                      <MobileField label={t("dashboard:common.forwarded")}>{formatDateTime(item.forwarded_at, i18n.language)}</MobileField>
                    </div>
                    <Button asChild size="sm" variant="outline" className="mt-3 w-full">
                      <Link to="/dashboard/cases/$id" params={{ id: String(item.id) }}>
                        <Eye className="mr-1 h-3.5 w-3.5" /> {t("dashboard:common.detail")}
                      </Link>
                    </Button>
                  </div>
                ))}
                {filtered.length === 0 && (
                  status !== "all" || q ? (
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
                        <td className="px-3 py-2"><Badge variant="outline">{formatCaseStatus(t, item.status_code)}</Badge></td>
                        <td className="px-3 py-2">{item.risk_level ?? item.risk_level_code ?? "-"}</td>
                        <td className="px-3 py-2">{item.priority ?? "-"}</td>
                        <td className="px-3 py-2 text-muted-foreground">{formatDateTime(item.forwarded_at, i18n.language)}</td>
                        <td className="px-3 py-2 text-right">
                          <Button asChild size="sm" variant="ghost">
                            <Link to="/dashboard/cases/$id" params={{ id: String(item.id) }}>
                              <Eye className="mr-1 h-3.5 w-3.5" /> {t("dashboard:common.detail")}
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                    {filtered.length === 0 && (
                      <tr>
                        <td colSpan={7} className="p-0">
                          {status !== "all" || q ? (
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
            onPageChange={setPage}
            onPageSizeChange={setPageSize}
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
