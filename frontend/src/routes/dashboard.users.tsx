import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";
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
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Password reset failed"),
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Reporter Management</h1>
          <p className="text-sm text-muted-foreground">Manage active reporter accounts and campus filters.</p>
        </div>
        <Button onClick={() => setShowCreate((value) => !value)}>{showCreate ? "Close" : "Create Reporter"}</Button>
      </div>

      {temporaryPassword && (
        <Card className="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
          <CardContent className="space-y-3 p-4">
            <div className="font-medium">Temporary password generated</div>
            <p className="text-sm text-muted-foreground">Copy this securely now. It is not stored client-side after closing this message.</p>
            <div className="rounded-md border bg-background p-3 font-mono">{temporaryPassword}</div>
            <Button variant="outline" size="sm" onClick={() => setTemporaryPassword(null)}>Dismiss</Button>
          </CardContent>
        </Card>
      )}

      {showCreate && <CreateReporterCard onCreated={(password) => { setTemporaryPassword(password); setShowCreate(false); invalidate(); }} />}

      <Card>
        <CardContent className="grid gap-3 p-4 md:grid-cols-5">
          <Input placeholder="Search reporters" value={search} onChange={(e) => setSearch(e.target.value)} />
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={isActive} onChange={(e) => setIsActive(e.target.value)}>
            <option value="">Any active status</option>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </select>
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={universityId} onChange={(e) => { setUniversityId(e.target.value); setFacultyId(""); setStudyProgramId(""); }}>
            <option value="">All universities</option>
            {(universitiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={facultyId} onChange={(e) => { setFacultyId(e.target.value); setStudyProgramId(""); }} disabled={!universityId}>
            <option value="">All faculties</option>
            {(facultiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={studyProgramId} onChange={(e) => setStudyProgramId(e.target.value)} disabled={!universityId}>
            <option value="">All study programs</option>
            {(studyProgramsQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
        </CardContent>
      </Card>

      <div className="overflow-hidden rounded-md border bg-background">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left">
            <tr>
              <th className="p-3">Reporter</th>
              <th className="p-3">Campus</th>
              <th className="p-3">Study Program</th>
              <th className="p-3">Status</th>
              <th className="p-3 text-right">Actions</th>
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
                <td className="p-3"><Badge variant={user.is_active ? "default" : "outline"}>{user.is_active ? "Active" : "Inactive"}</Badge></td>
                <td className="p-3 text-right">
                  <div className="flex flex-wrap justify-end gap-2">
                    <Button size="sm" variant="outline" onClick={() => user.is_active ? deactivateMutation.mutate(user.id) : activateMutation.mutate(user.id)}>
                      {user.is_active ? "Deactivate" : "Activate"}
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => resetMutation.mutate(user.id)}>Reset Password</Button>
                  </div>
                </td>
              </tr>
            ))}
            {usersQuery.isSuccess && usersQuery.data.data.length === 0 && (
              <tr><td colSpan={5} className="p-8 text-center text-muted-foreground">No reporters found.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function CreateReporterCard({ onCreated }: { onCreated: (temporaryPassword: string) => void }) {
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
      <CardHeader><CardTitle>Create Reporter</CardTitle></CardHeader>
      <CardContent>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
          <Input placeholder="Name" value={form.name} onChange={(e) => update("name", e.target.value)} required />
          <Input placeholder="Email" type="email" value={form.email} onChange={(e) => update("email", e.target.value)} required />
          <Input placeholder="NIM" value={form.nim} onChange={(e) => update("nim", e.target.value)} required />
          <Input placeholder="Phone number" value={form.phone_number} onChange={(e) => update("phone_number", e.target.value)} required />
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.university_id} onChange={(e) => { update("university_id", e.target.value); update("faculty_id", ""); update("study_program_id", ""); }} required>
            <option value="">Select university</option>
            {(universitiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          {hasFaculties && (
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.faculty_id} onChange={(e) => { update("faculty_id", e.target.value); update("study_program_id", ""); }}>
              <option value="">Select faculty</option>
              {(facultiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          )}
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.study_program_id} onChange={(e) => update("study_program_id", e.target.value)} required>
            <option value="">Select study program</option>
            {(studyProgramsQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <PasswordInput placeholder="Temporary password" value={form.password} onChange={(e) => update("password", e.target.value)} required />
          {Object.keys(errors).length > 0 && <p className="text-sm text-destructive md:col-span-2">Please review highlighted form values.</p>}
          <Button type="submit" disabled={mutation.isPending} className="md:col-span-2">Create Reporter</Button>
        </form>
      </CardContent>
    </Card>
  );
}
