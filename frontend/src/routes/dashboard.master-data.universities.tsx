import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient, keepPreviousData } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import {
  campusAdminQueryKeys,
  createCampusUniversity,
  getCampusUniversities,
  toggleCampusUniversity,
  updateCampusUniversity,
  type CampusUniversity,
  type UniversityPayload,
} from "@/lib/campus-admin-api";
import { ApiError } from "@/lib/api-client";
import { Inbox, SearchX } from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SelectInput } from "@/components/form-fields";
import { formatCampusType } from "@/lib/format-labels";
import { EmptyState } from "@/components/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";

export const Route = createFileRoute("/dashboard/master-data/universities")({
  component: UniversitiesPage,
});

const universityTypes = ["universitas", "institut", "sekolah_tinggi", "politeknik", "akademi"];

function UniversitiesPage() {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(DEFAULT_PAGE_SIZE);
  const [editing, setEditing] = useState<CampusUniversity | null>(null);
  const [creating, setCreating] = useState(false);
  const filtersActive = search !== "";

  const resetFilters = () => {
    setSearch("");
    setPage(1);
  };

  useEffect(() => {
    setPage(1);
  }, [search, pageSize]);

  const query = useMemo(
    () => ({ search: search || undefined, per_page: pageSize, page }),
    [search, pageSize, page],
  );
  const universitiesQuery = useQuery({
    queryKey: campusAdminQueryKeys.universities(query),
    queryFn: () => getCampusUniversities(query),
    placeholderData: keepPreviousData,
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["campus-admin", "universities"] });
  const toggleMutation = useMutation({
    mutationFn: toggleCampusUniversity,
    onSuccess: () => {
      toast.success(t("dashboard:masterData.universityStatusUpdated"));
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:masterData.universityStatusError")),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <Input className="max-w-sm" placeholder={t("dashboard:masterData.searchUniversities")} value={search} onChange={(event) => setSearch(event.target.value)} />
        <FilterResetButton active={filtersActive} onReset={resetFilters} />
        <div className="ml-auto">
          <Button onClick={() => setCreating(true)}>{t("dashboard:masterData.createUniversity")}</Button>
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
                  <th className="p-3">{t("dashboard:masterData.type")}</th>
                  <th className="p-3">{t("dashboard:masterData.facultiesColumn")}</th>
                  <th className="p-3">{t("dashboard:masterData.status")}</th>
                  <th className="p-3 text-right">{t("dashboard:masterData.actions")}</th>
                </tr>
              </thead>
              <tbody>
                {(universitiesQuery.data?.data ?? []).map((item) => (
                  <tr key={item.id} className="border-t">
                    <td className="p-3 font-mono text-xs">{item.code}</td>
                    <td className="p-3">
                      <div className="font-medium">{item.name}</div>
                      <div className="text-xs text-muted-foreground">{item.abbreviation ?? "-"}</div>
                    </td>
                    <td className="p-3">{formatCampusType(t, item.type)}</td>
                    <td className="p-3">{item.has_faculties ? t("dashboard:masterData.yes") : t("dashboard:masterData.no")}</td>
                    <td className="p-3"><StatusBadge active={item.is_active} /></td>
                    <td className="p-3 text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" onClick={() => setEditing(item)}>{t("dashboard:common.edit")}</Button>
                        {item.is_active ? (
                          <AlertDialog>
                            <AlertDialogTrigger asChild>
                              <Button variant="outline" size="sm">{t("dashboard:masterData.deactivate")}</Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                              <AlertDialogHeader>
                                <AlertDialogTitle>{t("dashboard:masterData.universityDeactivateConfirmTitle", { name: item.name })}</AlertDialogTitle>
                                <AlertDialogDescription>
                                  {t("dashboard:masterData.universityDeactivateConfirmDescription", { name: item.name })}
                                </AlertDialogDescription>
                              </AlertDialogHeader>
                              <AlertDialogFooter>
                                <AlertDialogCancel>{t("dashboard:common.cancel")}</AlertDialogCancel>
                                <AlertDialogAction
                                  variant="destructive"
                                  disabled={toggleMutation.isPending}
                                  onClick={() => toggleMutation.mutate(item.id)}
                                >
                                  {t("dashboard:masterData.deactivate")}
                                </AlertDialogAction>
                              </AlertDialogFooter>
                            </AlertDialogContent>
                          </AlertDialog>
                        ) : (
                          <Button variant="outline" size="sm" onClick={() => toggleMutation.mutate(item.id)} disabled={toggleMutation.isPending}>
                            {t("dashboard:masterData.activate")}
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
                {universitiesQuery.isSuccess && universitiesQuery.data.data.length === 0 && (
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
                {universitiesQuery.isLoading && (
                  <>
                    {Array.from({ length: 3 }).map((_, i) => (
                      <tr key={i} className="border-t">
                        <td className="p-3"><Skeleton className="h-4 w-16" /></td>
                        <td className="p-3"><Skeleton className="h-4 w-40" /></td>
                        <td className="p-3"><Skeleton className="h-4 w-20" /></td>
                        <td className="p-3"><Skeleton className="h-4 w-10" /></td>
                        <td className="p-3"><Skeleton className="h-4 w-14" /></td>
                        <td className="p-3"><Skeleton className="h-4 w-24" /></td>
                      </tr>
                    ))}
                  </>
                )}
              </tbody>
            </table>
          </div>
          <div className="px-4 pb-4">
            <ListPagination
              meta={universitiesQuery.data?.meta}
              page={page}
              pageSize={pageSize}
              onPageChange={setPage}
              onPageSizeChange={setPageSize}
              isFetching={universitiesQuery.isFetching}
            />
          </div>
        </CardContent>
      </Card>
      <UniversityDialog
        open={creating || Boolean(editing)}
        university={editing}
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

function UniversityDialog({
  open,
  university,
  onOpenChange,
  onSaved,
}: {
  open: boolean;
  university: CampusUniversity | null;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [form, setForm] = useState<UniversityPayload>(() => toPayload(university));
  const mutation = useMutation({
    mutationFn: () => university ? updateCampusUniversity(university.id, form) : createCampusUniversity(form),
    onSuccess: () => {
      toast.success(university ? t("dashboard:masterData.universityUpdated") : t("dashboard:masterData.universityCreated"));
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

  useEffect(() => setForm(toPayload(university)), [university, open]);

  const update = <K extends keyof UniversityPayload>(key: K, value: UniversityPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: [] }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>{university ? t("dashboard:masterData.editUniversity") : t("dashboard:masterData.createUniversity")}</DialogTitle></DialogHeader>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
          <Field label={t("dashboard:masterData.code")} error={errors.code?.[0]}><Input value={form.code} onChange={(event) => update("code", event.target.value)} required /></Field>
          <Field label={t("dashboard:masterData.name")} error={errors.name?.[0]}><Input value={form.name} onChange={(event) => update("name", event.target.value)} required /></Field>
          <Field label={t("dashboard:masterData.abbreviation")}><Input value={form.abbreviation ?? ""} onChange={(event) => update("abbreviation", event.target.value || null)} /></Field>
          <Field label={t("dashboard:masterData.type")} error={errors.type?.[0]}>
            <SelectInput
              value={form.type}
              onValueChange={(value) => update("type", value)}
              placeholder={t("dashboard:masterData.type")}
              options={universityTypes.map((type) => ({ value: type, label: formatCampusType(t, type) }))}
            />
          </Field>
          <Field label={t("dashboard:masterData.website")} error={errors.website?.[0]}><Input value={form.website ?? ""} onChange={(event) => update("website", event.target.value || null)} /></Field>
          <Field label={t("dashboard:masterData.email")} error={errors.email?.[0]}><Input value={form.email ?? ""} onChange={(event) => update("email", event.target.value || null)} /></Field>
          <Field label={t("dashboard:masterData.hotline")} error={errors.hotline?.[0]}><Input value={form.hotline ?? ""} onChange={(event) => update("hotline", event.target.value || null)} /></Field>
          <Field label={t("dashboard:masterData.sortOrder")}><Input type="number" value={form.sort_order ?? 0} onChange={(event) => update("sort_order", Number(event.target.value))} /></Field>
          <Field label={t("dashboard:masterData.hasFaculties")}>
            <SelectInput
              value={String(form.has_faculties)}
              onValueChange={(value) => update("has_faculties", value === "true")}
              placeholder={t("dashboard:masterData.hasFaculties")}
              options={[
                { value: "true", label: t("dashboard:masterData.yes") },
                { value: "false", label: t("dashboard:masterData.no") },
              ]}
            />
          </Field>
          <Field label={t("dashboard:masterData.address")}><Input value={form.address ?? ""} onChange={(event) => update("address", event.target.value || null)} /></Field>
          <div className="md:col-span-2"><Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? t("dashboard:common.saving") : t("dashboard:common.save")}</Button></div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function toPayload(university: CampusUniversity | null): UniversityPayload {
  return {
    code: university?.code ?? "",
    name: university?.name ?? "",
    abbreviation: university?.abbreviation ?? null,
    address: university?.address ?? null,
    website: university?.website ?? null,
    email: university?.email ?? null,
    hotline: university?.hotline ?? null,
    type: university?.type ?? "universitas",
    has_faculties: university?.has_faculties ?? true,
    sort_order: university?.sort_order ?? 0,
  };
}

function StatusBadge({ active }: { active: boolean }) {
  const { t } = useTranslation(["dashboard"]);
  return <Badge variant={active ? "default" : "outline"}>{active ? t("dashboard:masterData.active") : t("dashboard:masterData.inactive")}</Badge>;
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
