import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient, keepPreviousData } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { Inbox, SearchX } from "lucide-react";

import {
  campusAdminQueryKeys,
  createCampusFaculty,
  getCampusFaculties,
  getCampusUniversities,
  toggleCampusFaculty,
  updateCampusFaculty,
  type CampusFaculty,
  type FacultyPayload,
} from "@/lib/campus-admin-api";
import { ApiError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SelectInput } from "@/components/form-fields";
import { EmptyState } from "@/components/empty-state";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";
import { ActiveStateBadge } from "@/components/status-badge";

export const Route = createFileRoute("/dashboard/master-data/faculties")({
  component: FacultiesPage,
});

function FacultiesPage() {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [universityId, setUniversityId] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(DEFAULT_PAGE_SIZE);
  const [editing, setEditing] = useState<CampusFaculty | null>(null);
  const [creating, setCreating] = useState(false);
  const filtersActive = search !== "" || universityId !== "";

  const resetFilters = () => {
    setSearch("");
    setUniversityId("");
    setPage(1);
  };

  useEffect(() => {
    setPage(1);
  }, [search, universityId, pageSize]);

  const query = useMemo(
    () => ({ search: search || undefined, university_id: universityId || undefined, per_page: pageSize, page }),
    [search, universityId, pageSize, page],
  );
  const facultiesQuery = useQuery({
    queryKey: campusAdminQueryKeys.faculties(query),
    queryFn: () => getCampusFaculties(query),
    placeholderData: keepPreviousData,
  });
  // Universities listing here is a picker source; using a generous per_page is intentional.
  const universitiesQuery = useQuery({
    queryKey: campusAdminQueryKeys.universities({ per_page: 50 }),
    queryFn: () => getCampusUniversities({ per_page: 50 }),
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["campus-admin", "faculties"] });
  const toggleMutation = useMutation({
    mutationFn: toggleCampusFaculty,
    onSuccess: () => {
      toast.success(t("dashboard:masterData.facultyStatusUpdated"));
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:masterData.facultyStatusError")),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <Input className="max-w-sm" placeholder={t("dashboard:masterData.searchFaculties")} value={search} onChange={(event) => setSearch(event.target.value)} />
        <SelectInput
          value={universityId}
          onValueChange={setUniversityId}
          placeholder={t("dashboard:common.allUniversities")}
          options={[
            { value: "", label: t("dashboard:common.allUniversities") },
            ...(universitiesQuery.data?.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
          ]}
        />
        <FilterResetButton active={filtersActive} onReset={resetFilters} />
        <div className="ml-auto">
          <Button onClick={() => setCreating(true)}>{t("dashboard:masterData.createFaculty")}</Button>
        </div>
      </div>
      <Card>
        <CardContent className="space-y-3 p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-left">
                <tr>
                  <th className="p-3">{t("dashboard:masterData.code")}</th>
                  <th className="p-3">{t("dashboard:masterData.name")}</th>
                  <th className="p-3">{t("dashboard:masterData.university")}</th>
                  <th className="p-3">{t("dashboard:masterData.programs")}</th>
                  <th className="p-3">{t("dashboard:masterData.status")}</th>
                  <th className="p-3 text-right">{t("dashboard:masterData.actions")}</th>
                </tr>
              </thead>
              <tbody>
                {(facultiesQuery.data?.data ?? []).map((item) => (
                  <tr key={item.id} className="border-t">
                    <td className="p-3 font-mono text-xs">{item.code}</td>
                    <td className="p-3 font-medium">{item.name}</td>
                    <td className="p-3">{item.university?.name ?? "-"}</td>
                    <td className="p-3">{item.study_programs_count ?? 0}</td>
                    <td className="p-3">
                      <ActiveStateBadge
                        active={item.is_active}
                        activeLabel={t("dashboard:masterData.active")}
                        inactiveLabel={t("dashboard:masterData.inactive")}
                      />
                    </td>
                    <td className="p-3 text-right">
                      <div className="flex justify-end gap-2">
                        <Button size="sm" variant="outline" onClick={() => setEditing(item)}>{t("dashboard:common.edit")}</Button>
                        <Button size="sm" variant="outline" onClick={() => toggleMutation.mutate(item.id)}>
                          {item.is_active ? t("dashboard:masterData.deactivate") : t("dashboard:masterData.activate")}
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
                {facultiesQuery.isSuccess && facultiesQuery.data.data.length === 0 && (
                  <tr>
                    <td colSpan={6} className="p-0">
                      {filtersActive ? (
                        <EmptyState icon={SearchX} title={t("dashboard:masterData.filteredEmptyTitle")} description={t("dashboard:masterData.filteredEmptyDesc")} />
                      ) : (
                        <EmptyState icon={Inbox} title={t("dashboard:masterData.emptyTitle")} description={t("dashboard:masterData.emptyDesc")} />
                      )}
                    </td>
                  </tr>
                )}
                {facultiesQuery.isLoading && (
                  <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">{t("dashboard:masterData.loadingFaculties")}</td></tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="px-4 pb-4">
            <ListPagination
              meta={facultiesQuery.data?.meta}
              page={page}
              pageSize={pageSize}
              onPageChange={setPage}
              onPageSizeChange={setPageSize}
              isFetching={facultiesQuery.isFetching}
            />
          </div>
        </CardContent>
      </Card>
      <FacultyDialog
        open={creating || Boolean(editing)}
        faculty={editing}
        universities={universitiesQuery.data?.data ?? []}
        onOpenChange={(open) => {
          if (!open) {
            setCreating(false);
            setEditing(null);
          }
        }}
        onSaved={() => {
          setCreating(false);
          setEditing(null);
          invalidate();
        }}
      />
    </div>
  );
}

function FacultyDialog({
  open,
  faculty,
  universities,
  onOpenChange,
  onSaved,
}: {
  open: boolean;
  faculty: CampusFaculty | null;
  universities: { id: number; name: string; has_faculties?: boolean; is_active?: boolean }[];
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [form, setForm] = useState<FacultyPayload>(() => toPayload(faculty));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const mutation = useMutation({
    mutationFn: () => faculty ? updateCampusFaculty(faculty.id, form) : createCampusFaculty(form),
    onSuccess: () => {
      toast.success(faculty ? t("dashboard:masterData.facultyUpdated") : t("dashboard:masterData.facultyCreated"));
      setErrors({});
      onSaved();
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? {});
        toast.error(error.message);
      }
    },
  });

  useEffect(() => setForm(toPayload(faculty)), [faculty, open]);
  const selectableUniversities = universities.filter((item) => item.is_active !== false && item.has_faculties !== false);
  const update = <K extends keyof FacultyPayload>(key: K, value: FacultyPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: [] }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>{faculty ? t("dashboard:masterData.editFaculty") : t("dashboard:masterData.createFaculty")}</DialogTitle></DialogHeader>
        <form className="grid gap-3" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
          <Field label={t("dashboard:masterData.university")} error={errors.university_id?.[0]}>
            <SelectInput
              value={form.university_id ? String(form.university_id) : ""}
              onValueChange={(value) => update("university_id", Number(value))}
              placeholder={t("dashboard:masterData.selectUniversity")}
              options={[
                { value: "", label: t("dashboard:masterData.selectUniversity") },
                ...selectableUniversities.map((item) => ({ value: String(item.id), label: item.name })),
              ]}
            />
          </Field>
          <Field label={t("dashboard:masterData.code")} error={errors.code?.[0]}><Input value={form.code} onChange={(event) => update("code", event.target.value)} required /></Field>
          <Field label={t("dashboard:masterData.name")} error={errors.name?.[0]}><Input value={form.name} onChange={(event) => update("name", event.target.value)} required /></Field>
          <Field label={t("dashboard:masterData.sortOrder")}><Input type="number" value={form.sort_order ?? 0} onChange={(event) => update("sort_order", Number(event.target.value))} /></Field>
          <Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? t("dashboard:common.saving") : t("dashboard:common.save")}</Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function toPayload(faculty: CampusFaculty | null): FacultyPayload {
  return {
    university_id: faculty?.university_id ?? 0,
    code: faculty?.code ?? "",
    name: faculty?.name ?? "",
    sort_order: faculty?.sort_order ?? 0,
  };
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div className="grid gap-2">
      <Label>{label}</Label>
      {children}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}
