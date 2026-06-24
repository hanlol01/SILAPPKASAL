import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { ApiError } from "@/lib/api-client";
import {
  campusQueryKeys,
  getFaculties,
  getStudyPrograms,
  getUniversities,
  submitReporterRegistration,
} from "@/lib/registration-api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export const Route = createFileRoute("/register")({
  component: RegisterPage,
  head: () => ({
    meta: [
      { title: "Daftar Pelapor - SILAPPKASAL" },
      { name: "description", content: "Registrasi akun pelapor SILAPPKASAL." },
    ],
  }),
});

function RegisterPage() {
  const { t } = useTranslation(["auth", "portal"]);
  const [form, setForm] = useState({
    name: "",
    nim: "",
    email: "",
    phone_number: "",
    university_id: "",
    faculty_id: "",
    study_program_id: "",
    password: "",
    password_confirmation: "",
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [successNumber, setSuccessNumber] = useState<string | null>(null);

  const universitiesQuery = useQuery({
    queryKey: campusQueryKeys.universities(),
    queryFn: getUniversities,
  });

  const selectedUniversity = universitiesQuery.data?.find((item) => String(item.id) === form.university_id);
  const hasFaculties = selectedUniversity?.has_faculties === true;

  const facultiesQuery = useQuery({
    queryKey: campusQueryKeys.faculties(Number(form.university_id) || null),
    queryFn: () => getFaculties(Number(form.university_id)),
    enabled: Boolean(form.university_id && hasFaculties),
  });

  const studyProgramsQuery = useQuery({
    queryKey: campusQueryKeys.studyPrograms(Number(form.university_id) || null, Number(form.faculty_id) || null),
    queryFn: () => getStudyPrograms(Number(form.university_id), form.faculty_id ? Number(form.faculty_id) : null),
    enabled: Boolean(form.university_id),
  });

  useEffect(() => {
    setForm((current) => ({ ...current, faculty_id: "", study_program_id: "" }));
  }, [form.university_id]);

  useEffect(() => {
    setForm((current) => ({ ...current, study_program_id: "" }));
  }, [form.faculty_id]);

  const mutation = useMutation({
    mutationFn: () =>
      submitReporterRegistration({
        name: form.name,
        nim: form.nim,
        email: form.email,
        phone_number: form.phone_number,
        university_id: Number(form.university_id),
        faculty_id: form.faculty_id ? Number(form.faculty_id) : null,
        study_program_id: Number(form.study_program_id),
        password: form.password,
        password_confirmation: form.password_confirmation,
      }),
    onSuccess: (data) => {
      setErrors({});
      setSuccessNumber(data.registration_number);
      toast.success(t("auth:registrationSubmitted"));
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? {});
        toast.error(error.status === 429 ? t("auth:rateLimited") : error.message);
      }
    },
  });

  const update = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  if (successNumber) {
    return (
      <PublicShell>
        <Card className="mx-auto max-w-lg">
          <CardHeader>
            <CardTitle>{t("auth:registrationSuccessTitle")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-muted-foreground">{t("auth:registrationSuccessBody")}</p>
            <div className="rounded-md border bg-muted/40 p-3 font-mono text-sm">{successNumber}</div>
            <Button asChild className="w-full">
              <Link to="/login">{t("auth:backToLogin")}</Link>
            </Button>
          </CardContent>
        </Card>
      </PublicShell>
    );
  }

  return (
    <PublicShell>
      <Card className="mx-auto max-w-2xl">
        <CardHeader>
          <CardTitle>{t("auth:registerTitle")}</CardTitle>
          <p className="text-sm text-muted-foreground">{t("auth:registerSubtitle")}</p>
        </CardHeader>
        <CardContent>
          <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(event) => {
              event.preventDefault();
              mutation.mutate();
            }}
          >
            <Field label={t("auth:fullName")} error={errors.name?.[0]}>
              <Input value={form.name} onChange={(e) => update("name", e.target.value)} required />
            </Field>
            <Field label={t("auth:nim")} error={errors.nim?.[0]}>
              <Input value={form.nim} onChange={(e) => update("nim", e.target.value)} required />
            </Field>
            <Field label={t("auth:emailAddress")} error={errors.email?.[0]}>
              <Input type="email" value={form.email} onChange={(e) => update("email", e.target.value)} required />
            </Field>
            <Field label={t("portal:phoneNumber")} error={errors.phone_number?.[0]}>
              <Input value={form.phone_number} onChange={(e) => update("phone_number", e.target.value)} required />
            </Field>
            <Field label={t("auth:university")} error={errors.university_id?.[0]}>
              <select
                className="h-10 rounded-md border bg-background px-3 text-sm"
                value={form.university_id}
                onChange={(e) => update("university_id", e.target.value)}
                required
              >
                <option value="">{t("auth:selectUniversity")}</option>
                {(universitiesQuery.data ?? []).map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>
            </Field>
            {hasFaculties && (
              <Field label={t("auth:faculty")} error={errors.faculty_id?.[0]}>
                <select
                  className="h-10 rounded-md border bg-background px-3 text-sm"
                  value={form.faculty_id}
                  onChange={(e) => update("faculty_id", e.target.value)}
                >
                  <option value="">{t("auth:selectFaculty")}</option>
                  {(facultiesQuery.data ?? []).map((item) => (
                    <option key={item.id} value={item.id}>{item.name}</option>
                  ))}
                </select>
              </Field>
            )}
            <Field label={t("auth:studyProgram")} error={errors.study_program_id?.[0]}>
              <select
                className="h-10 rounded-md border bg-background px-3 text-sm"
                value={form.study_program_id}
                onChange={(e) => update("study_program_id", e.target.value)}
                required
              >
                <option value="">{t("auth:selectStudyProgram")}</option>
                {(studyProgramsQuery.data ?? []).map((item) => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>
            </Field>
            <Field label={t("auth:password")} error={errors.password?.[0]}>
              <Input type="password" value={form.password} onChange={(e) => update("password", e.target.value)} required />
            </Field>
            <Field label={t("auth:passwordConfirmation")} error={errors.password_confirmation?.[0]}>
              <Input
                type="password"
                value={form.password_confirmation}
                onChange={(e) => update("password_confirmation", e.target.value)}
                required
              />
            </Field>
            <div className="md:col-span-2">
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? t("auth:submittingRegistration") : t("auth:submitRegistration")}
              </Button>
              <p className="mt-4 text-center text-sm text-muted-foreground">
                <Link to="/login" className="text-primary hover:underline">{t("auth:backToLogin")}</Link>
              </p>
            </div>
          </form>
        </CardContent>
      </Card>
    </PublicShell>
  );
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

function PublicShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-muted/30 px-4 py-8">
      <div className="mx-auto mb-8 flex max-w-2xl items-center justify-between">
        <Link to="/login" className="flex items-center gap-2 font-semibold">
          <img src="/Logo.ico" alt="Logo" className="h-8 w-8" />
          SILAPPKASAL
        </Link>
      </div>
      {children}
    </div>
  );
}
