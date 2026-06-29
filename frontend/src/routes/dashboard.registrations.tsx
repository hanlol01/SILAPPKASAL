import { createFileRoute, Link, Navigate, Outlet, useRouterState } from "@tanstack/react-router";
import { useQuery, keepPreviousData } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { Inbox, SearchX } from "lucide-react";

import {
  campusQueryKeys,
  getReporterRegistrations,
  getUniversities,
  registrationQueryKeys,
} from "@/lib/registration-api";
import { useAuth } from "@/hooks/use-auth";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { SelectInput } from "@/components/form-fields";
import { formatRegistrationStatus } from "@/lib/format-labels";
import { EmptyState } from "@/components/empty-state";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";

export const Route = createFileRoute("/dashboard/registrations")({
  component: RegistrationsPage,
});

function RegistrationsPage() {
  const { roleCode } = useAuth();
  const { t } = useTranslation(["dashboard"]);
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [universityId, setUniversityId] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(DEFAULT_PAGE_SIZE);
  const canAccess = roleCode === "admin" || roleCode === "super_admin";
  const filtersActive = status !== "" || search !== "" || universityId !== "";

  const resetFilters = () => {
    setStatus("");
    setSearch("");
    setUniversityId("");
    setPage(1);
  };

  useEffect(() => {
    setPage(1);
  }, [status, search, universityId, pageSize]);

  const query = useMemo(
    () => ({
      status: status || undefined,
      search: search || undefined,
      university_id: universityId || undefined,
      per_page: pageSize,
      page,
    }),
    [status, search, universityId, pageSize, page],
  );

  const registrationsQuery = useQuery({
    queryKey: registrationQueryKeys.list(query),
    queryFn: () => getReporterRegistrations(query),
    enabled: canAccess,
    placeholderData: keepPreviousData,
  });
  const universitiesQuery = useQuery({
    queryKey: campusQueryKeys.universities(),
    queryFn: getUniversities,
    enabled: canAccess && roleCode === "super_admin",
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;
  if (pathname !== "/dashboard/registrations") return <Outlet />;

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:registrations.title") }]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:registrations.title")}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:registrations.subtitle")}</p>
      </div>
      <Card>
        <CardContent className="space-y-3 p-4">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Input placeholder={t("dashboard:registrations.search")} value={search} onChange={(e) => setSearch(e.target.value)} />
            <SelectInput
              value={status}
              onValueChange={setStatus}
              placeholder={t("dashboard:common.allStatuses")}
              options={[
                { value: "", label: t("dashboard:common.allStatuses") },
                { value: "pending", label: formatRegistrationStatus(t, "pending") },
                { value: "approved", label: formatRegistrationStatus(t, "approved") },
                { value: "rejected", label: formatRegistrationStatus(t, "rejected") },
              ]}
            />
            {roleCode === "super_admin" && (
              <SelectInput
                value={universityId}
                onValueChange={setUniversityId}
                placeholder={t("dashboard:common.allUniversities")}
                options={[
                  { value: "", label: t("dashboard:common.allUniversities") },
                  ...(universitiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
                ]}
              />
            )}
          </div>
          <div className="flex justify-end">
            <FilterResetButton active={filtersActive} onReset={resetFilters} />
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardContent className="space-y-3 p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-left">
                <tr>
                  <th className="p-3">{t("dashboard:registrations.name")}</th>
                  <th className="p-3">{t("dashboard:registrations.nim")}</th>
                  <th className="p-3">{t("dashboard:registrations.university")}</th>
                  <th className="p-3">{t("dashboard:registrations.studyProgram")}</th>
                  <th className="p-3">{t("dashboard:common.status")}</th>
                  <th className="p-3 text-right">{t("dashboard:common.actions")}</th>
                </tr>
              </thead>
              <tbody>
                {(registrationsQuery.data?.data ?? []).map((item) => (
                  <tr key={item.id} className="border-t">
                    <td className="p-3">
                      <div className="font-medium">{item.name}</div>
                      <div className="text-xs text-muted-foreground">{item.email}</div>
                    </td>
                    <td className="p-3">{item.nim}</td>
                    <td className="p-3">{item.university?.name ?? "-"}</td>
                    <td className="p-3">{item.study_program?.name ?? "-"}</td>
                    <td className="p-3"><Badge variant="outline">{formatRegistrationStatus(t, item.status)}</Badge></td>
                    <td className="p-3 text-right">
                      <Button asChild size="sm" variant="outline">
                        <Link to="/dashboard/registrations/$id" params={{ id: String(item.id) }}>{t("dashboard:common.review")}</Link>
                      </Button>
                    </td>
                  </tr>
                ))}
                {registrationsQuery.isSuccess && registrationsQuery.data.data.length === 0 && (
                  <tr>
                    <td colSpan={6} className="p-0">
                      {filtersActive ? (
                        <EmptyState icon={SearchX} title={t("dashboard:registrations.filteredEmptyTitle")} description={t("dashboard:registrations.filteredEmptyDesc")} />
                      ) : (
                        <EmptyState icon={Inbox} title={t("dashboard:registrations.emptyTitle")} description={t("dashboard:registrations.emptyDesc")} />
                      )}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="px-4 pb-4">
            <ListPagination
              meta={registrationsQuery.data?.meta}
              page={page}
              pageSize={pageSize}
              onPageChange={setPage}
              onPageSizeChange={setPageSize}
              isFetching={registrationsQuery.isFetching}
            />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
