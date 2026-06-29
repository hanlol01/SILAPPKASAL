import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient, keepPreviousData } from "@tanstack/react-query";
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
import { Inbox, SearchX } from "lucide-react";

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
import type { ApiUser } from "@/lib/api-types";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
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
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Form } from "@/components/ui/form";
import { PasswordField, SelectFormField, SelectInput, TextInputField } from "@/components/form-fields";
import { EmptyState } from "@/components/empty-state";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { FilterResetButton } from "@/components/filter-reset-button";
import { ListPagination } from "@/components/list-pagination";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";

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
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState<number>(DEFAULT_PAGE_SIZE);
  const [showCreate, setShowCreate] = useState(false);
  const [temporaryPassword, setTemporaryPassword] = useState<string | null>(null);
  const [resetTarget, setResetTarget] = useState<ApiUser | null>(null);
  const [resetConfirmationEmail, setResetConfirmationEmail] = useState("");
  const canAccess = roleCode === "admin" || roleCode === "super_admin";
  const filtersActive =
    search !== "" ||
    isActive !== "" ||
    universityId !== "" ||
    facultyId !== "" ||
    studyProgramId !== "";

  const resetFilters = () => {
    setSearch("");
    setIsActive("");
    setUniversityId("");
    setFacultyId("");
    setStudyProgramId("");
    setPage(1);
  };

  useEffect(() => {
    setPage(1);
  }, [search, isActive, universityId, facultyId, studyProgramId, pageSize]);

  const query = useMemo(
    () => ({
      role: "reporter",
      search: search || undefined,
      is_active: isActive || undefined,
      university_id: universityId || undefined,
      faculty_id: facultyId || undefined,
      study_program_id: studyProgramId || undefined,
      per_page: pageSize,
      page,
    }),
    [search, isActive, universityId, facultyId, studyProgramId, pageSize, page],
  );

  const usersQuery = useQuery({
    queryKey: adminUsersQueryKeys.list(query),
    queryFn: () => getUsers(query),
    enabled: canAccess,
    placeholderData: keepPreviousData,
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
      setResetTarget(null);
      setResetConfirmationEmail("");
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:users.passwordResetError")),
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("dashboard:users.reporterManagement") }]} />
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("dashboard:users.reporterManagement")}</h1>
          <p className="text-sm text-muted-foreground">{t("dashboard:users.reporterManagementSubtitle")}</p>
        </div>
        <Button onClick={() => setShowCreate((value) => !value)}>{showCreate ? t("dashboard:users.close") : t("dashboard:users.createReporter")}</Button>
      </div>

      {temporaryPassword && (
        <Card className="border-warning/40 bg-warning/10">
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
        <CardContent className="space-y-3 p-4">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Input placeholder={t("dashboard:users.searchReporters")} value={search} onChange={(e) => setSearch(e.target.value)} />
            <SelectInput
              value={isActive}
              onValueChange={setIsActive}
              placeholder={t("dashboard:users.anyActiveStatus")}
              options={[
                { value: "", label: t("dashboard:users.anyActiveStatus") },
                { value: "true", label: t("dashboard:users.active") },
                { value: "false", label: t("dashboard:users.inactive") },
              ]}
            />
            <SelectInput
              value={universityId}
              onValueChange={(value) => {
                setUniversityId(value);
                setFacultyId("");
                setStudyProgramId("");
              }}
              placeholder={t("dashboard:common.allUniversities")}
              options={[
                { value: "", label: t("dashboard:common.allUniversities") },
                ...(universitiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
              ]}
            />
            <SelectInput
              value={facultyId}
              onValueChange={(value) => {
                setFacultyId(value);
                setStudyProgramId("");
              }}
              placeholder={t("dashboard:users.allFaculties")}
              disabled={!universityId}
              options={[
                { value: "", label: t("dashboard:users.allFaculties") },
                ...(facultiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
              ]}
            />
            <SelectInput
              value={studyProgramId}
              onValueChange={setStudyProgramId}
              placeholder={t("dashboard:users.allStudyPrograms")}
              disabled={!universityId}
              options={[
                { value: "", label: t("dashboard:users.allStudyPrograms") },
                ...(studyProgramsQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
              ]}
            />
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
                        {user.is_active ? (
                          <AlertDialog>
                            <AlertDialogTrigger asChild>
                              <Button size="sm" variant="outline">{t("dashboard:users.deactivate")}</Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                              <AlertDialogHeader>
                                <AlertDialogTitle>{t("dashboard:users.deactivateConfirmTitle", { name: user.name })}</AlertDialogTitle>
                                <AlertDialogDescription>
                                  {t("dashboard:users.deactivateConfirmDescription", { name: user.name })}
                                </AlertDialogDescription>
                              </AlertDialogHeader>
                              <AlertDialogFooter>
                                <AlertDialogCancel>{t("dashboard:common.cancel")}</AlertDialogCancel>
                                <AlertDialogAction
                                  variant="destructive"
                                  disabled={deactivateMutation.isPending}
                                  onClick={() => deactivateMutation.mutate(user.id)}
                                >
                                  {t("dashboard:users.deactivate")}
                                </AlertDialogAction>
                              </AlertDialogFooter>
                            </AlertDialogContent>
                          </AlertDialog>
                        ) : (
                          <Button size="sm" variant="outline" onClick={() => activateMutation.mutate(user.id)} disabled={activateMutation.isPending}>
                            {t("dashboard:users.activate")}
                          </Button>
                        )}
                        <AlertDialog
                          open={resetTarget?.id === user.id}
                          onOpenChange={(open) => {
                            setResetTarget(open ? user : null);
                            setResetConfirmationEmail("");
                          }}
                        >
                          <AlertDialogTrigger asChild>
                            <Button size="sm" variant="outline">{t("dashboard:users.resetPassword")}</Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>{t("dashboard:users.resetPasswordConfirmTitle", { name: user.name })}</AlertDialogTitle>
                              <AlertDialogDescription>
                                {t("dashboard:users.resetPasswordConfirmDescription", { email: user.email })}
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <div className="grid gap-2">
                              <label className="text-sm font-medium" htmlFor={`reset-email-${user.id}`}>
                                {t("dashboard:users.resetPasswordConfirmLabel")}
                              </label>
                              <Input
                                id={`reset-email-${user.id}`}
                                value={resetConfirmationEmail}
                                onChange={(event) => setResetConfirmationEmail(event.target.value)}
                                placeholder={user.email}
                              />
                            </div>
                            <AlertDialogFooter>
                              <AlertDialogCancel>{t("dashboard:common.cancel")}</AlertDialogCancel>
                              <AlertDialogAction
                                variant="destructive"
                                disabled={resetMutation.isPending || resetConfirmationEmail !== user.email}
                                onClick={(event) => {
                                  event.preventDefault();
                                  resetMutation.mutate(user.id);
                                }}
                              >
                                {t("dashboard:users.resetPassword")}
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      </div>
                    </td>
                  </tr>
                ))}
                {usersQuery.isSuccess && usersQuery.data.data.length === 0 && (
                  <tr>
                    <td colSpan={5} className="p-0">
                      {filtersActive ? (
                        <EmptyState icon={SearchX} title={t("dashboard:users.filteredEmptyTitle")} description={t("dashboard:users.filteredEmptyDesc")} />
                      ) : (
                        <EmptyState icon={Inbox} title={t("dashboard:users.emptyTitle")} description={t("dashboard:users.emptyDesc")} />
                      )}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="px-4 pb-4">
            <ListPagination
              meta={usersQuery.data?.meta}
              page={page}
              pageSize={pageSize}
              onPageChange={setPage}
              onPageSizeChange={setPageSize}
              isFetching={usersQuery.isFetching}
            />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function CreateReporterCard({ onCreated }: { onCreated: (temporaryPassword: string) => void }) {
  const { t } = useTranslation(["auth", "dashboard", "portal", "common"]);
  const form = useForm<CreateReporterValues>({
    resolver: zodResolver(createCreateReporterSchema(validationMessages(t))),
    defaultValues: {
      name: "",
      email: "",
      nim: "",
      phone_number: "",
      university_id: "",
      faculty_id: "",
      study_program_id: "",
      password: "",
    },
  });
  const universityId = form.watch("university_id");
  const facultyId = form.watch("faculty_id");
  const values = form.watch();
  const universitiesQuery = useQuery({ queryKey: campusQueryKeys.universities(), queryFn: getUniversities });
  const selectedUniversity = universitiesQuery.data?.find((item) => String(item.id) === universityId);
  const hasFaculties = selectedUniversity?.has_faculties === true;
  const effectiveFacultyId = hasFaculties && facultyId ? Number(facultyId) : null;
  const facultiesQuery = useQuery({
    queryKey: campusQueryKeys.faculties(Number(universityId) || null),
    queryFn: () => getFaculties(Number(universityId)),
    enabled: Boolean(universityId && hasFaculties),
  });
  const studyProgramsQuery = useQuery({
    queryKey: campusQueryKeys.studyPrograms(Number(universityId) || null, effectiveFacultyId),
    queryFn: () => getStudyPrograms(Number(universityId), effectiveFacultyId),
    enabled: Boolean(universityId),
  });
  const mutation = useMutation({
    mutationFn: (values: CreateReporterValues) =>
      createReporter({
        name: values.name,
        email: values.email,
        nim: values.nim,
        phone_number: values.phone_number,
        university_id: Number(values.university_id),
        faculty_id: effectiveFacultyId,
        study_program_id: Number(values.study_program_id),
        password: values.password,
      }),
    onSuccess: (data) => onCreated(data.temporary_password),
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("common:unexpectedError")));
    },
  });
  const canSubmit = Boolean(
    values.name &&
    values.email &&
    values.nim &&
    values.phone_number &&
    values.university_id &&
    values.study_program_id &&
    values.password,
  );

  return (
    <Card>
      <CardHeader><CardTitle>{t("dashboard:users.createReporter")}</CardTitle></CardHeader>
      <CardContent>
        <Form {...form}>
          <form className="grid gap-3 md:grid-cols-2" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <TextInputField control={form.control} name="name" label={t("dashboard:users.name")} />
            <TextInputField control={form.control} name="email" label={t("dashboard:users.email")} type="email" />
            <TextInputField control={form.control} name="nim" label="NIM" />
            <TextInputField control={form.control} name="phone_number" label={t("portal:phoneNumber")} />
            <SelectFormField
              control={form.control}
              name="university_id"
              label={t("auth:university")}
              placeholder={t("auth:selectUniversity")}
              onValueChange={() => {
                form.setValue("faculty_id", "");
                form.setValue("study_program_id", "");
              }}
              options={(universitiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))}
            />
            {hasFaculties && (
              <SelectFormField
                control={form.control}
                name="faculty_id"
                label={`${t("auth:faculty")} (${t("auth:optional")})`}
                placeholder={t("auth:selectFaculty")}
                onValueChange={() => form.setValue("study_program_id", "")}
                options={[
                  { value: "", label: t("auth:selectFaculty") },
                  ...(facultiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name })),
                ]}
              />
            )}
            <SelectFormField
              control={form.control}
              name="study_program_id"
              label={t("auth:studyProgram")}
              placeholder={t("auth:selectStudyProgram")}
              disabled={!universityId || studyProgramsQuery.isLoading}
              options={(studyProgramsQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))}
            />
            <PasswordField
              control={form.control}
              name="password"
              label={t("dashboard:users.temporaryPasswordPlaceholder")}
            />
            <Button type="submit" disabled={mutation.isPending || !canSubmit} className="md:col-span-2">
              {mutation.isPending ? t("dashboard:common.saving") : t("dashboard:users.createReporter")}
            </Button>
          </form>
        </Form>
      </CardContent>
    </Card>
  );
}

function createCreateReporterSchema(messages: ValidationMessages) {
  return z.object({
    name: z.string().min(1, messages.required),
    email: z.string().min(1, messages.required).email(messages.email),
    nim: z.string().min(1, messages.required),
    phone_number: z.string().min(1, messages.required),
    university_id: z.string().min(1, messages.required),
    faculty_id: z.string().optional(),
    study_program_id: z.string().min(1, messages.required),
    password: z.string().min(1, messages.required),
  });
}

type CreateReporterValues = z.infer<ReturnType<typeof createCreateReporterSchema>>;

type ValidationMessages = {
  required: string;
  email: string;
};

function validationMessages(t: ReturnType<typeof useTranslation>["t"]): ValidationMessages {
  return {
    required: t("common:validation.required"),
    email: t("common:validation.email"),
  };
}
