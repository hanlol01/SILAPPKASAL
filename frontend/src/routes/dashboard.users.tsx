import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import {
  activateUser,
  adminUsersQueryKeys,
  createReporter,
  deactivateUser,
  getUsers,
  resetUserPassword,
} from "@/lib/admin-users-api";
import { campusQueryKeys, getFaculties, getStudyPrograms, getUniversities } from "@/lib/registration-api";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { PasswordInput } from "@/components/ui/password-input";
import { Badge } from "@/components/ui/badge";

export const Route = createFileRoute("/dashboard/users")({
  component: DashboardUsersPage,
});

function DashboardUsersPage() {
  const { t } = useTranslation(["auth", "dashboard"]);
  const { roleCode } = useAuth();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [isActive, setIsActive] = useState("");
  const [universityId, setUniversityId] = useState("");
  const [facultyId, setFacultyId] = useState("");
  const [studyProgramId, setStudyProgramId] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [temporaryPassword, setTemporaryPassword] = useState<string | null>(null);
  const canAccess = roleCode === "admin" || roleCode === "super_admin";
  const query = useMemo(
    () => ({
      role: "reporter",
      search: search || undefined,
      is_active: isActive || undefined,
      university_id: universityId || undefined,
      faculty_id: facultyId || undefined,
      study_program_id: studyProgramId || undefined,
      per_page: 50,
    }),
    [search, isActive, universityId, facultyId, studyProgramId],
  );

  const usersQuery = useQuery({
    queryKey: adminUsersQueryKeys.list(query),
    queryFn: () => getUsers(query),
    enabled: canAccess,
  });
  const universitiesQuery = useQuery({ queryKey: campusQueryKeys.universities(), queryFn: getUniversities, enabled: canAccess });
  const facultiesQuery = useQuery({
    queryKey: campusQueryKeys.faculties(Number(universityId) || null),
    queryFn: () => getFaculties(Number(universityId)),
    enabled: canAccess && Boolean(universityId),
  });
  const studyProgramsQuery = useQuery({
    queryKey: campusQueryKeys.studyPrograms(Number(universityId) || null, Number(facultyId) || null),
    queryFn: () => getStudyPrograms(Number(universityId), facultyId ? Number(facultyId) : null),
    enabled: canAccess && Boolean(universityId),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["admin-users"] });
  const activateMutation = useMutation({ mutationFn: activateUser, onSuccess: invalidate });
  const deactivateMutation = useMutation({ mutationFn: deactivateUser, onSuccess: invalidate });
  const resetMutation = useMutation({
    mutationFn: resetUserPassword,
    onSuccess: (data) => {
      setTemporaryPassword(data.temporary_password);
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:users.passwordResetError")),
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:users.reporterManagement")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:users.reporterManagementSubtitle")}</p>
        </div>
        <Button onClick={() => setShowCreate((value) => !value)}>{showCreate ? t("dashboard:users.close") : t("dashboard:users.createReporter")}</Button>
      </div>

      {temporaryPassword && (
        <Card className="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
          <CardContent className="space-y-3 p-4">
            <div className="font-medium">{t("dashboard:users.temporaryPasswordGenerated")}</div>
            <p className="text-sm text-muted-foreground">{t("dashboard:users.copySecurely")}</p>
            <div className="rounded-md border bg-background p-3 font-mono">{temporaryPassword}</div>
            <Button variant="outline" size="sm" onClick={() => setTemporaryPassword(null)}>{t("dashboard:users.dismiss")}</Button>
          </CardContent>
        </Card>
      )}

      {showCreate && <CreateReporterCard onCreated={(password) => { setTemporaryPassword(password); setShowCreate(false); invalidate(); }} />}

      <Card>
        <CardContent className="grid gap-3 p-4 md:grid-cols-5">
          <Input placeholder={t("dashboard:users.searchReporters")} value={search} onChange={(e) => setSearch(e.target.value)} />
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={isActive} onChange={(e) => setIsActive(e.target.value)}>
            <option value="">{t("dashboard:users.anyActiveStatus")}</option>
            <option value="true">{t("dashboard:users.active")}</option>
            <option value="false">{t("dashboard:users.inactive")}</option>
          </select>
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={universityId} onChange={(e) => { setUniversityId(e.target.value); setFacultyId(""); setStudyProgramId(""); }}>
            <option value="">{t("dashboard:common.allUniversities")}</option>
            {(universitiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={facultyId} onChange={(e) => { setFacultyId(e.target.value); setStudyProgramId(""); }} disabled={!universityId}>
            <option value="">{t("dashboard:users.allFaculties")}</option>
            {(facultiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={studyProgramId} onChange={(e) => setStudyProgramId(e.target.value)} disabled={!universityId}>
            <option value="">{t("dashboard:users.allStudyPrograms")}</option>
            {(studyProgramsQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
        </CardContent>
      </Card>

      <div className="overflow-hidden rounded-md border bg-background">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left">
            <tr>
              <th className="p-3">{t("dashboard:users.reporter")}</th>
              <th className="p-3">{t("dashboard:users.campus")}</th>
              <th className="p-3">{t("dashboard:users.studyProgram")}</th>
              <th className="p-3">{t("dashboard:common.status")}</th>
              <th className="p-3 text-right">{t("dashboard:common.actions")}</th>
            </tr>
          </thead>
          <tbody>
            {(usersQuery.data?.data ?? []).map((user) => (
              <tr key={user.id} className="border-t">
                <td className="p-3">
                  <div className="font-medium">{user.name}</div>
                  <div className="text-xs text-muted-foreground">{user.email}</div>
                </td>
                <td className="p-3">{user.university?.name ?? "-"}</td>
                <td className="p-3">{user.study_program?.name ?? "-"}</td>
                <td className="p-3"><Badge variant={user.is_active ? "default" : "outline"}>{user.is_active ? t("dashboard:users.active") : t("dashboard:users.inactive")}</Badge></td>
                <td className="p-3 text-right">
                  <div className="flex flex-wrap justify-end gap-2">
                    <Button size="sm" variant="outline" onClick={() => user.is_active ? deactivateMutation.mutate(user.id) : activateMutation.mutate(user.id)}>
                      {user.is_active ? t("dashboard:users.deactivate") : t("dashboard:users.activate")}
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => resetMutation.mutate(user.id)}>{t("dashboard:users.resetPassword")}</Button>
                  </div>
                </td>
              </tr>
            ))}
            {usersQuery.isSuccess && usersQuery.data.data.length === 0 && (
              <tr><td colSpan={5} className="p-8 text-center text-muted-foreground">{t("dashboard:users.noReporters")}</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function CreateReporterCard({ onCreated }: { onCreated: (temporaryPassword: string) => void }) {
  const { t } = useTranslation(["auth", "dashboard", "portal"]);
  const [form, setForm] = useState({
    name: "",
    email: "",
    nim: "",
    phone_number: "",
    university_id: "",
    faculty_id: "",
    study_program_id: "",
    password: "",
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const universitiesQuery = useQuery({ queryKey: campusQueryKeys.universities(), queryFn: getUniversities });
  const selectedUniversity = universitiesQuery.data?.find((item) => String(item.id) === form.university_id);
  const hasFaculties = selectedUniversity?.has_faculties === true;
  const effectiveFacultyId = hasFaculties && form.faculty_id ? Number(form.faculty_id) : null;
  const facultiesQuery = useQuery({
    queryKey: campusQueryKeys.faculties(Number(form.university_id) || null),
    queryFn: () => getFaculties(Number(form.university_id)),
    enabled: Boolean(form.university_id && hasFaculties),
  });
  const studyProgramsQuery = useQuery({
    queryKey: campusQueryKeys.studyPrograms(Number(form.university_id) || null, effectiveFacultyId),
    queryFn: () => getStudyPrograms(Number(form.university_id), effectiveFacultyId),
    enabled: Boolean(form.university_id),
  });
  const mutation = useMutation({
    mutationFn: () =>
      createReporter({
        name: form.name,
        email: form.email,
        nim: form.nim,
        phone_number: form.phone_number,
        university_id: Number(form.university_id),
        faculty_id: effectiveFacultyId,
        study_program_id: Number(form.study_program_id),
        password: form.password,
      }),
    onSuccess: (data) => onCreated(data.temporary_password),
    onError: (error) => {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? {});
        toast.error(error.message);
      }
    },
  });
  const update = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  return (
    <Card>
      <CardHeader><CardTitle>{t("dashboard:users.createReporter")}</CardTitle></CardHeader>
      <CardContent>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
          <Input placeholder={t("dashboard:users.name")} value={form.name} onChange={(e) => update("name", e.target.value)} required />
          <Input placeholder={t("dashboard:users.email")} type="email" value={form.email} onChange={(e) => update("email", e.target.value)} required />
          <Input placeholder="NIM" value={form.nim} onChange={(e) => update("nim", e.target.value)} required />
          <Input placeholder={t("portal:phoneNumber")} value={form.phone_number} onChange={(e) => update("phone_number", e.target.value)} required />
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.university_id} onChange={(e) => { update("university_id", e.target.value); update("faculty_id", ""); update("study_program_id", ""); }} required>
            <option value="">{t("auth:selectUniversity")}</option>
            {(universitiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          {hasFaculties && (
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.faculty_id} onChange={(e) => { update("faculty_id", e.target.value); update("study_program_id", ""); }}>
              <option value="">{t("auth:selectFaculty")}</option>
              {(facultiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          )}
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.study_program_id} onChange={(e) => update("study_program_id", e.target.value)} required>
            <option value="">{t("auth:selectStudyProgram")}</option>
            {(studyProgramsQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <PasswordInput placeholder={t("dashboard:users.temporaryPasswordPlaceholder")} value={form.password} onChange={(e) => update("password", e.target.value)} required />
          {Object.keys(errors).length > 0 && <p className="text-sm text-destructive md:col-span-2">{t("dashboard:users.reviewFormValues")}</p>}
          <Button type="submit" disabled={mutation.isPending} className="md:col-span-2">{t("dashboard:users.createReporter")}</Button>
        </form>
      </CardContent>
    </Card>
  );
}
