import { createFileRoute, Navigate, useNavigate } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { ApiError } from "@/lib/api-client";
import {
  campusQueryKeys,
  correctReporterRegistration,
  getFaculties,
  getStudyPrograms,
  getUniversities,
} from "@/lib/registration-api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/registration/correction")({
  component: RegistrationCorrectionPage,
});

function RegistrationCorrectionPage() {
  const { t } = useTranslation(["auth", "portal"]);
  const { registration, setRegistration } = useAuth();
  const navigate = useNavigate();
  const [currentPassword, setCurrentPassword] = useState("");
  const [form, setForm] = useState(() => ({
    name: registration?.name ?? "",
    nim: registration?.nim ?? "",
    phone_number: registration?.phone_number ?? "",
    university_id: registration?.university_id ? String(registration.university_id) : "",
    faculty_id: registration?.faculty_id ? String(registration.faculty_id) : "",
    study_program_id: registration?.study_program_id ? String(registration.study_program_id) : "",
    new_password: "",
    new_password_confirmation: "",
  }));
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const universitiesQuery = useQuery({ queryKey: campusQueryKeys.universities(), queryFn: getUniversities });
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
    if (!hasFaculties && form.faculty_id) {
      setForm((current) => ({ ...current, faculty_id: "" }));
    }
  }, [hasFaculties, form.faculty_id]);

  const mutation = useMutation({
    mutationFn: () =>
      correctReporterRegistration({
        email: registration?.email ?? "",
        password: currentPassword,
        name: form.name,
        nim: form.nim,
        phone_number: form.phone_number,
        university_id: Number(form.university_id),
        faculty_id: form.faculty_id ? Number(form.faculty_id) : null,
        study_program_id: Number(form.study_program_id),
        new_password: form.new_password || undefined,
        new_password_confirmation: form.new_password_confirmation || undefined,
      }),
    onSuccess: (data) => {
      setRegistration(data);
      toast.success(t("correctionSubmitted"));
      navigate({ to: "/registration/pending", replace: true });
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? {});
        toast.error(error.message);
      }
    },
  });

  if (!registration) return <Navigate to="/login" replace />;
  if (registration.status !== "rejected") return <Navigate to="/registration/pending" replace />;

  const update = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  return (
    <div className="min-h-screen bg-muted/30 px-4 py-8">
      <Card className="mx-auto max-w-2xl">
        <CardHeader>
          <CardTitle>{t("correctionTitle")}</CardTitle>
          <div className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
            {registration.rejection_reason}
          </div>
        </CardHeader>
        <CardContent>
          <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(event) => {
              event.preventDefault();
              mutation.mutate();
            }}
          >
            <Field label={t("auth:emailAddress")}>
              <Input value={registration.email} readOnly />
            </Field>
            <Field label={t("auth:currentPassword")} error={errors.password?.[0]}>
              <Input type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} required />
            </Field>
            <Field label={t("auth:fullName")} error={errors.name?.[0]}>
              <Input value={form.name} onChange={(e) => update("name", e.target.value)} required />
            </Field>
            <Field label={t("auth:nim")} error={errors.nim?.[0]}>
              <Input value={form.nim} onChange={(e) => update("nim", e.target.value)} required />
            </Field>
            <Field label={t("portal:phoneNumber")} error={errors.phone_number?.[0]}>
              <Input value={form.phone_number} onChange={(e) => update("phone_number", e.target.value)} required />
            </Field>
            <Field label={t("auth:university")} error={errors.university_id?.[0]}>
              <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.university_id} onChange={(e) => update("university_id", e.target.value)} required>
                <option value="">{t("auth:selectUniversity")}</option>
                {(universitiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </Field>
            {hasFaculties && (
              <Field label={t("auth:faculty")} error={errors.faculty_id?.[0]}>
                <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.faculty_id} onChange={(e) => update("faculty_id", e.target.value)}>
                  <option value="">{t("auth:selectFaculty")}</option>
                  {(facultiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </Field>
            )}
            <Field label={t("auth:studyProgram")} error={errors.study_program_id?.[0]}>
              <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.study_program_id} onChange={(e) => update("study_program_id", e.target.value)} required>
                <option value="">{t("auth:selectStudyProgram")}</option>
                {(studyProgramsQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </Field>
            <Field label={t("auth:optionalNewPassword")} error={errors.new_password?.[0]}>
              <Input type="password" value={form.new_password} onChange={(e) => update("new_password", e.target.value)} />
            </Field>
            <Field label={t("auth:confirmNewPassword")}>
              <Input type="password" value={form.new_password_confirmation} onChange={(e) => update("new_password_confirmation", e.target.value)} />
            </Field>
            <div className="md:col-span-2">
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? t("auth:submittingCorrection") : t("auth:submitCorrection")}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
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
