import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import {
  campusAdminQueryKeys,
  createCampusStudyProgram,
  getCampusFaculties,
  getCampusStudyPrograms,
  getCampusUniversities,
  toggleCampusStudyProgram,
  updateCampusStudyProgram,
  type CampusStudyProgram,
  type StudyProgramPayload,
} from "@/lib/campus-admin-api";
import { ApiError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { formatDegreeLevel } from "@/lib/format-labels";

export const Route = createFileRoute("/dashboard/master-data/study-programs")({
  component: StudyProgramsPage,
});

const degreeLevels = ["D3", "D4", "S1", "S2", "S3", "profesi"];

function StudyProgramsPage() {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [universityId, setUniversityId] = useState("");
  const [facultyId, setFacultyId] = useState("");
  const [editing, setEditing] = useState<CampusStudyProgram | null>(null);
  const [creating, setCreating] = useState(false);
  const query = useMemo(
    () => ({ search: search || undefined, university_id: universityId || undefined, faculty_id: facultyId || undefined, per_page: 50 }),
    [search, universityId, facultyId],
  );
  const programsQuery = useQuery({ queryKey: campusAdminQueryKeys.studyPrograms(query), queryFn: () => getCampusStudyPrograms(query) });
  const universitiesQuery = useQuery({ queryKey: campusAdminQueryKeys.universities({ per_page: 50 }), queryFn: () => getCampusUniversities({ per_page: 50 }) });
  const facultiesQuery = useQuery({
    queryKey: campusAdminQueryKeys.faculties({ university_id: universityId || undefined, per_page: 50 }),
    queryFn: () => getCampusFaculties({ university_id: universityId || undefined, per_page: 50 }),
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["campus-admin", "study-programs"] });
  const toggleMutation = useMutation({
    mutationFn: toggleCampusStudyProgram,
    onSuccess: () => {
      toast.success(t("dashboard:masterData.studyProgramStatusUpdated"));
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:masterData.studyProgramStatusError")),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-3">
        <Input className="max-w-sm" placeholder={t("dashboard:masterData.searchStudyPrograms")} value={search} onChange={(event) => setSearch(event.target.value)} />
        <select className="h-10 rounded-md border bg-background px-3 text-sm" value={universityId} onChange={(event) => { setUniversityId(event.target.value); setFacultyId(""); }}>
          <option value="">{t("dashboard:common.allUniversities")}</option>
          {(universitiesQuery.data?.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select>
        <select className="h-10 rounded-md border bg-background px-3 text-sm" value={facultyId} onChange={(event) => setFacultyId(event.target.value)} disabled={!universityId}>
          <option value="">{t("dashboard:masterData.allFaculties")}</option>
          {(facultiesQuery.data?.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select>
        <Button onClick={() => setCreating(true)}>{t("dashboard:masterData.createStudyProgram")}</Button>
      </div>
      <Card>
        <CardContent className="overflow-x-auto p-0">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="p-3">{t("dashboard:masterData.code")}</th>
                <th className="p-3">{t("dashboard:masterData.name")}</th>
                <th className="p-3">{t("dashboard:masterData.degree")}</th>
                <th className="p-3">{t("dashboard:masterData.faculty")}</th>
                <th className="p-3">{t("dashboard:masterData.university")}</th>
                <th className="p-3">{t("dashboard:masterData.status")}</th>
                <th className="p-3 text-right">{t("dashboard:masterData.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {(programsQuery.data?.data ?? []).map((item) => (
                <tr key={item.id} className="border-t">
                  <td className="p-3 font-mono text-xs">{item.code}</td>
                  <td className="p-3 font-medium">{item.name}</td>
                  <td className="p-3">{formatDegreeLevel(t, item.degree_level)}</td>
                  <td className="p-3">{item.faculty?.name ?? "-"}</td>
                  <td className="p-3">{item.university?.name ?? "-"}</td>
                  <td className="p-3"><Badge variant={item.is_active ? "default" : "outline"}>{item.is_active ? t("dashboard:masterData.active") : t("dashboard:masterData.inactive")}</Badge></td>
                  <td className="p-3 text-right">
                    <div className="flex justify-end gap-2">
                      <Button variant="outline" size="sm" onClick={() => setEditing(item)}>{t("dashboard:common.edit")}</Button>
                      <Button variant="outline" size="sm" onClick={() => toggleMutation.mutate(item.id)}>
                        {item.is_active ? t("dashboard:masterData.deactivate") : t("dashboard:masterData.activate")}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {programsQuery.isSuccess && programsQuery.data.data.length === 0 && (
                <tr><td colSpan={7} className="p-8 text-center text-muted-foreground">{t("dashboard:masterData.noStudyPrograms")}</td></tr>
              )}
              {programsQuery.isLoading && (
                <tr><td colSpan={7} className="p-8 text-center text-muted-foreground">{t("dashboard:masterData.loadingStudyPrograms")}</td></tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <StudyProgramDialog
        open={creating || Boolean(editing)}
        studyProgram={editing}
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

function StudyProgramDialog({
  open,
  studyProgram,
  universities,
  onOpenChange,
  onSaved,
}: {
  open: boolean;
  studyProgram: CampusStudyProgram | null;
  universities: { id: number; name: string; is_active?: boolean }[];
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [form, setForm] = useState<StudyProgramPayload>(() => toPayload(studyProgram));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const facultiesQuery = useQuery({
    queryKey: campusAdminQueryKeys.faculties({ university_id: form.university_id || undefined, per_page: 50 }),
    queryFn: () => getCampusFaculties({ university_id: form.university_id || undefined, per_page: 50 }),
    enabled: Boolean(form.university_id),
  });
  const mutation = useMutation({
    mutationFn: () => studyProgram ? updateCampusStudyProgram(studyProgram.id, form) : createCampusStudyProgram(form),
    onSuccess: () => {
      toast.success(studyProgram ? t("dashboard:masterData.studyProgramUpdated") : t("dashboard:masterData.studyProgramCreated"));
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

  useEffect(() => setForm(toPayload(studyProgram)), [studyProgram, open]);
  const update = <K extends keyof StudyProgramPayload>(key: K, value: StudyProgramPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: [] }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>{studyProgram ? t("dashboard:masterData.editStudyProgram") : t("dashboard:masterData.createStudyProgram")}</DialogTitle></DialogHeader>
        <form className="grid gap-3" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
          <Field label={t("dashboard:masterData.university")} error={errors.university_id?.[0]}>
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.university_id || ""} onChange={(event) => setForm((current) => ({ ...current, university_id: Number(event.target.value), faculty_id: null }))} required>
              <option value="">{t("dashboard:masterData.selectUniversity")}</option>
              {universities.filter((item) => item.is_active !== false).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </Field>
          <Field label={`${t("dashboard:masterData.faculty")} (${t("dashboard:masterData.optional")})`} error={errors.faculty_id?.[0]}>
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.faculty_id ?? ""} onChange={(event) => update("faculty_id", event.target.value ? Number(event.target.value) : null)} disabled={!form.university_id}>
              <option value="">{t("dashboard:masterData.noFaculty")}</option>
              {(facultiesQuery.data?.data ?? []).filter((item) => item.is_active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </Field>
          <Field label={t("dashboard:masterData.code")} error={errors.code?.[0]}><Input value={form.code} onChange={(event) => update("code", event.target.value)} required /></Field>
          <Field label={t("dashboard:masterData.name")} error={errors.name?.[0]}><Input value={form.name} onChange={(event) => update("name", event.target.value)} required /></Field>
          <Field label={t("dashboard:masterData.degreeLevel")} error={errors.degree_level?.[0]}>
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.degree_level} onChange={(event) => update("degree_level", event.target.value)} required>
              {degreeLevels.map((level) => <option key={level} value={level}>{formatDegreeLevel(t, level)}</option>)}
            </select>
          </Field>
          <Field label={t("dashboard:masterData.sortOrder")}><Input type="number" value={form.sort_order ?? 0} onChange={(event) => update("sort_order", Number(event.target.value))} /></Field>
          <Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? t("dashboard:common.saving") : t("dashboard:common.save")}</Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function toPayload(studyProgram: CampusStudyProgram | null): StudyProgramPayload {
  return {
    university_id: studyProgram?.university_id ?? 0,
    faculty_id: studyProgram?.faculty_id ?? null,
    code: studyProgram?.code ?? "",
    name: studyProgram?.name ?? "",
    degree_level: studyProgram?.degree_level ?? "S1",
    sort_order: studyProgram?.sort_order ?? 0,
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
