import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery, keepPreviousData } from "@tanstack/react-query";
import { Eye, Inbox, Loader2, Lock, Search, SearchX, SlidersHorizontal } from "lucide-react";
import { useMemo } from "react";
import { useTranslation } from "react-i18next";
import { AccessDenied } from "@/components/access-denied";
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
import { useAuth } from "@/hooks/use-auth";
import { formatDateTime } from "@/lib/format";
import { formatPriorityLevel, formatReportStatus, formatReportType } from "@/lib/format-labels";
import { getReports, operationsQueryKeys } from "@/lib/operations-api";
import type { ReportPriorityProjection, ReportReporter } from "@/lib/operations-types";
import { EmptyState } from "@/components/empty-state";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import {
  DEFAULT_PAGE_SIZE,
  normalizePageSize,
  type PageSize,
} from "@/lib/list-controls";
import { ReportStatusBadge } from "@/components/status-badge";
import { OperationalScopeFilter } from "@/components/operational-scope-filter";

export const Route = createFileRoute("/dashboard/reports/")({
  validateSearch: (search: Record<string, unknown>): ReportsSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    status: REPORT_STATUSES.find((item) => item === search.status) as ReportStatusFilter | undefined,
    report_type: REPORT_TYPES.find((item) => item === search.report_type) as ReportTypeFilter | undefined,
    satgas_id: positiveInteger(search.satgas_id),
    assignment_status: search.assignment_status === "unassigned" ? "unassigned" : undefined,
    university_id: positiveInteger(search.university_id),
    page: positiveInteger(search.page),
    per_page: normalizePageSize(positiveInteger(search.per_page)),
  }),
  component: ReportsPage,
  head: () => ({ meta: [{ title: "Complaints - SILAPPKASAL Admin" }] }),
});

const REPORT_STATUSES = ["submitted", "under_review", "need_info", "rejected", "forwarded"] as const;
const REPORT_TYPES = ["open", "confidential", "anonymous"] as const;
type ReportStatusFilter = (typeof REPORT_STATUSES)[number];
type ReportTypeFilter = (typeof REPORT_TYPES)[number];

type ReportsSearch = {
  q?: string;
  status?: ReportStatusFilter;
  report_type?: ReportTypeFilter;
  satgas_id?: number;
  assignment_status?: "unassigned";
  university_id?: number;
  page?: number;
  per_page?: PageSize;
};

function positiveInteger(value: unknown): number | undefined {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
}

function ReportsPage() {
  const { roleCode } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const navigate = Route.useNavigate();
  const search = Route.useSearch();
  const q = search.q ?? "";
  const status = search.status ?? "all";
  const reportType = search.report_type ?? "all";
  const satgasId =
    search.assignment_status === "unassigned"
      ? "unassigned"
      : search.satgas_id
        ? String(search.satgas_id)
        : "all";
  const universityId = search.university_id ? String(search.university_id) : "all";
  const page = search.page ?? 1;
  const pageSize = search.per_page ?? DEFAULT_PAGE_SIZE;
  const scopeFilterActive =
    (roleCode === "admin" && satgasId !== "all") ||
    (roleCode === "super_admin" && universityId !== "all");
  const filtersActive =
    q !== "" ||
    status !== "all" ||
    reportType !== "all" ||
    scopeFilterActive;

  const updateFilters = (patch: Partial<ReportsSearch>) => {
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

  const query = useMemo(
    () => ({
      status: status === "all" ? undefined : status,
      report_type: reportType === "all" ? undefined : reportType,
      satgas_id:
        roleCode === "admin" && satgasId !== "all" && satgasId !== "unassigned"
          ? Number(satgasId)
          : undefined,
      assignment_status:
        roleCode === "admin" && satgasId === "unassigned" ? "unassigned" : undefined,
      university_id:
        roleCode === "super_admin" && universityId !== "all" ? Number(universityId) : undefined,
      per_page: pageSize,
      page,
    }),
    [roleCode, status, reportType, satgasId, universityId, pageSize, page],
  );
  const reportsQuery = useQuery({
    queryKey: operationsQueryKeys.reports(query),
    queryFn: () => getReports(query),
    enabled: roleCode === "super_admin" || roleCode === "admin",
    placeholderData: keepPreviousData,
  });

  if (roleCode !== "super_admin" && roleCode !== "admin") {
    return <AccessDenied />;
  }

  // Client-side text search complements the server-side filter selects;
  // pagination remains server-driven.
  const filtered =
    reportsQuery.data?.data.filter((report) => {
      const haystack = `${report.registration_number} ${report.status} ${report.report_type} ${report.category?.name ?? ""}`.toLowerCase();
      return !q || haystack.includes(q.toLowerCase());
    }) ?? [];

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:reports.title") }]} />
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:reports.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:reports.subtitle")}</p>
        </div>
      </div>

      <Card>
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-wrap items-center gap-2">
           <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder={t("dashboard:reports.search")}
                value={q}
                onChange={(e) => updateFilters({ q: e.target.value || undefined })}
                className="pl-9"
              />
            </div>
            <SlidersHorizontal className="h-4 w-4 text-muted-foreground" />
            <Select
              value={status}
              onValueChange={(value) =>
                updateFilters({ status: value === "all" ? undefined : (value as ReportStatusFilter) })
              }
            >
              <SelectTrigger className="w-[170px]"><SelectValue placeholder={t("dashboard:common.status")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("dashboard:common.allStatuses")}</SelectItem>
                {REPORT_STATUSES.map((item) => (
                  <SelectItem key={item} value={item}>{formatReportStatus(t, item)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select
              value={reportType}
              onValueChange={(value) =>
                updateFilters({ report_type: value === "all" ? undefined : (value as ReportTypeFilter) })
              }
            >
              <SelectTrigger className="w-[170px]"><SelectValue placeholder={t("dashboard:common.type")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("dashboard:common.allTypes")}</SelectItem>
                {REPORT_TYPES.map((item) => (
                  <SelectItem key={item} value={item}>{formatReportType(t, item)}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <OperationalScopeFilter
              roleCode={roleCode}
              satgasId={satgasId}
              universityId={universityId}
              includeUnassigned
              onSatgasChange={(value) =>
                updateFilters({
                  satgas_id:
                    value !== "all" && value !== "unassigned" ? Number(value) : undefined,
                  assignment_status: value === "unassigned" ? "unassigned" : undefined,
                  university_id: undefined,
                })
              }
              onUniversityChange={(value) =>
                updateFilters({
                  satgas_id: undefined,
                  assignment_status: undefined,
                  university_id: value !== "all" ? Number(value) : undefined,
                })
              }
            />
            <FilterResetButton active={filtersActive} onReset={resetFilters} />
            {reportsQuery.isFetching && (
              <Loader2
                className="h-4 w-4 animate-spin text-muted-foreground"
                aria-label={t("dashboard:common.loading")}
              />
            )}
          </div>

          {reportsQuery.isLoading && (
            <div className="py-12 text-center text-sm text-muted-foreground">{t("dashboard:reports.loading")}</div>
          )}
          {reportsQuery.isError && (
            <QueryErrorState message={t("dashboard:reports.error")} onRetry={() => reportsQuery.refetch()} />
          )}
          {reportsQuery.isSuccess && (
            <>
              <div className="grid gap-3 md:hidden">
                {filtered.map((report) => (
                  <div key={report.id} className="rounded-lg border bg-background p-3 text-sm">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="truncate font-mono text-xs font-medium">{report.registration_number}</div>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                          <ReportStatusBadge status={report.status} />
                          {(report.is_anonymous || report.report_type === "anonymous") && (
                            <ReportTypeChip>
                              <Lock className="h-3 w-3" />
                              {t("dashboard:reports.anonymous")}
                            </ReportTypeChip>
                          )}
                        </div>
                      </div>
                      <Button asChild size="sm" variant="outline" className="shrink-0">
                        <Link to="/dashboard/reports/$id" params={{ id: String(report.id) }}>
                          <Eye className="h-4 w-4" />
                          {t("dashboard:common.detail")}
                        </Link>
                      </Button>
                    </div>
                    <div className="mt-3 grid gap-2 text-xs">
                      <MobileField label={t("dashboard:common.type")}>{formatReportType(t, report.report_type)}</MobileField>
                      <MobileField label={t("dashboard:reports.reporter")}>{reporterDisplay(report.reporter, t)}</MobileField>
                      <MobileField label={t("dashboard:common.category")}>{report.category?.name ?? t("dashboard:common.metadataUnavailable")}</MobileField>
                      <MobileField label={t("dashboard:common.priority")}>
                        {reportPriorityLabel(t, report.priority)}
                      </MobileField>
                      <MobileField label={t("dashboard:common.submitted")}>{formatDateTime(report.submitted_at, i18n.language)}</MobileField>
                    </div>
                  </div>
                ))}
                 {filtered.length === 0 && (
                   filtersActive ? (
                    <EmptyState icon={SearchX} title={t("dashboard:reports.filteredEmptyTitle")} description={t("dashboard:reports.filteredEmptyDesc")} />
                  ) : (
                    <EmptyState icon={Inbox} title={t("dashboard:reports.emptyTitle")} description={t("dashboard:reports.emptyDesc")} />
                  )
                )}
              </div>
              <div className="hidden overflow-x-auto rounded-lg border md:block">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="px-3 py-2 text-left">{t("dashboard:reports.registration")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.type")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:reports.reporter")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.category")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.priority")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.status")}</th>
                      <th className="px-3 py-2 text-left">{t("dashboard:common.submitted")}</th>
                      <th className="px-3 py-2"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {filtered.map((report) => (
                      <tr key={report.id} className="border-t hover:bg-muted/40">
                        <td className="px-3 py-2 font-mono text-xs">{report.registration_number}</td>
                        <td className="px-3 py-2">
                          <div className="flex flex-wrap items-center gap-2">
                            <span>{formatReportType(t, report.report_type)}</span>
                            {(report.is_anonymous || report.report_type === "anonymous") && (
                              <ReportTypeChip>
                                <Lock className="h-3 w-3" />
                                {t("dashboard:reports.anonymous")}
                              </ReportTypeChip>
                            )}
                          </div>
                        </td>
                        <td className="px-3 py-2">{reporterDisplay(report.reporter, t)}</td>
                        <td className="px-3 py-2">{report.category?.name ?? t("dashboard:common.metadataUnavailable")}</td>
                        <td className="px-3 py-2">{reportPriorityLabel(t, report.priority)}</td>
                        <td className="px-3 py-2"><ReportStatusBadge status={report.status} /></td>
                        <td className="px-3 py-2 text-muted-foreground">{formatDateTime(report.submitted_at, i18n.language)}</td>
                        <td className="px-3 py-2 text-right">
                          <Button asChild size="sm" variant="ghost">
                            <Link to="/dashboard/reports/$id" params={{ id: String(report.id) }}>
                              <Eye className="h-4 w-4" />
                              {t("dashboard:common.detail")}
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                    {filtered.length === 0 && (
                      <tr>
                        <td colSpan={8} className="p-0">
                           {filtersActive ? (
                            <EmptyState icon={SearchX} title={t("dashboard:reports.filteredEmptyTitle")} description={t("dashboard:reports.filteredEmptyDesc")} />
                          ) : (
                            <EmptyState icon={Inbox} title={t("dashboard:reports.emptyTitle")} description={t("dashboard:reports.emptyDesc")} />
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
            meta={reportsQuery.data?.meta}
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
            isFetching={reportsQuery.isFetching}
          />
        </CardContent>
      </Card>
    </div>
  );
}

function ReportTypeChip({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-full border border-muted-foreground/30 bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
      {children}
    </span>
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

function reporterDisplay(reporter: ReportReporter | null | undefined, t: ReturnType<typeof useTranslation>["t"]) {
  if (!reporter) {
    return <span className="text-muted-foreground">{t("dashboard:common.metadataUnavailable")}</span>;
  }

  if ("masked" in reporter && reporter.masked === true) {
    return <span className="text-muted-foreground">{t("dashboard:reports.identityHidden")}</span>;
  }

  return "name" in reporter ? reporter.name : t("dashboard:common.metadataUnavailable");
}

function reportPriorityLabel(
  t: ReturnType<typeof useTranslation>["t"],
  priority: ReportPriorityProjection,
) {
  if (priority.availability === "assessed" && priority.level) {
    return formatPriorityLevel(t, priority.level);
  }

  return t(`dashboard:reports.priorityAvailability.${priority.availability}`);
}
