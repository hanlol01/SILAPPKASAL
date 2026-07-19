import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";

import { ApiError } from "@/lib/api-client";
import { phoneInputAttributes, requiredPhoneNumberSchema } from "@/lib/phone-validation";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  campusQueryKeys,
  getFaculties,
  getStudyPrograms,
  getUniversities,
  submitReporterRegistration,
} from "@/lib/registration-api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Form } from "@/components/ui/form";
import { PasswordField, SelectFormField, TextInputField } from "@/components/form-fields";

export const Route = createFileRoute("/register")({
  component: RegisterPage,
  head: () => ({
    meta: [
      { title: "Daftar Pelapor - SILAPPKASAL" },
      { name: "description", content: "Registrasi akun pelapor SILAPPKASAL." },
    ],
  }),
});

function createRegistrationSchema(messages: ValidationMessages) {
  return z
    .object({
      name: z.string().min(1, messages.required),
      nim: z.string().min(1, messages.required),
      email: z.string().min(1, messages.required).email(messages.email),
      phone_number: requiredPhoneNumberSchema({ required: messages.required, invalid: messages.phone }),
      university_id: z.string().min(1, messages.required),
      faculty_id: z.string().optional(),
      study_program_id: z.string().min(1, messages.required),
      password: z.string().min(1, messages.required),
      password_confirmation: z.string().min(1, messages.required),
    })
    .refine((values) => values.password === values.password_confirmation, {
      path: ["password_confirmation"],
      message: messages.passwordConfirmationMismatch,
    });
}

type RegistrationValues = z.infer<ReturnType<typeof createRegistrationSchema>>;

function RegisterPage() {
  const { t } = useTranslation(["auth", "portal", "common"]);
  const form = useForm<RegistrationValues>({
    resolver: zodResolver(createRegistrationSchema(validationMessages(t))),
    defaultValues: {
      name: "",
      nim: "",
      email: "",
      phone_number: "",
      university_id: "",
      faculty_id: "",
      study_program_id: "",
      password: "",
      password_confirmation: "",
    },
  });
  const [successNumber, setSuccessNumber] = useState<string | null>(null);
  const universityId = form.watch("university_id");
  const facultyId = form.watch("faculty_id");
  const values = form.watch();

  const universitiesQuery = useQuery({
    queryKey: campusQueryKeys.universities(),
    queryFn: getUniversities,
  });

  const selectedUniversity = universitiesQuery.data?.find((item) => String(item.id) === universityId);
  const hasFaculties = selectedUniversity?.has_faculties === true;

  const facultiesQuery = useQuery({
    queryKey: campusQueryKeys.faculties(Number(universityId) || null),
    queryFn: () => getFaculties(Number(universityId)),
    enabled: Boolean(universityId && hasFaculties),
  });

  const selectedFaculty = (facultiesQuery.data ?? []).find((item) => String(item.id) === facultyId);
  const effectiveFacultyId = hasFaculties && selectedFaculty ? selectedFaculty.id : null;

  const studyProgramsQuery = useQuery({
    queryKey: campusQueryKeys.studyPrograms(Number(universityId) || null, effectiveFacultyId),
    queryFn: () => getStudyPrograms(Number(universityId), effectiveFacultyId),
    enabled: Boolean(universityId),
  });

  const mutation = useMutation({
    mutationFn: (values: RegistrationValues) =>
      submitReporterRegistration({
        name: values.name,
        nim: values.nim,
        email: values.email,
        phone_number: values.phone_number,
        university_id: Number(values.university_id),
        faculty_id: effectiveFacultyId,
        study_program_id: Number(values.study_program_id),
        password: values.password,
        password_confirmation: values.password_confirmation,
      }),
    onSuccess: (data) => {
      setSuccessNumber(data.registration_number);
      toast.success(t("auth:registrationSubmitted"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(error instanceof ApiError && error.status === 429 ? t("auth:rateLimited") : apiErrorMessage(error, t("common:unexpectedError")));
    },
  });

  const canSubmit = Boolean(
    values.name &&
    values.nim &&
    values.email &&
    values.phone_number &&
    values.university_id &&
    values.study_program_id &&
    values.password &&
    values.password_confirmation,
  );

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
          <Form {...form}>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
              <TextInputField control={form.control} name="name" label={t("auth:fullName")} />
              <TextInputField control={form.control} name="nim" label={t("auth:nim")} />
              <TextInputField control={form.control} name="email" label={t("auth:emailAddress")} type="email" />
              <TextInputField control={form.control} name="phone_number" label={t("portal:phoneNumber")} {...phoneInputAttributes} required />
              <SelectFormField
                control={form.control}
                name="university_id"
                label={t("auth:university")}
                placeholder={universitiesQuery.isLoading ? t("auth:loadingUniversities") : t("auth:selectUniversity")}
                disabled={universitiesQuery.isLoading}
                onValueChange={() => {
                  form.setValue("faculty_id", "");
                  form.setValue("study_program_id", "");
                }}
                options={
                  universitiesQuery.isSuccess && universitiesQuery.data.length === 0
                    ? [{ value: "", label: t("auth:noUniversitiesAvailable"), disabled: true }]
                    : (universitiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))
                }
              />
              {hasFaculties && (
                <SelectFormField
                  control={form.control}
                  name="faculty_id"
                  label={`${t("auth:faculty")} (${t("auth:optional")})`}
                  placeholder={facultiesQuery.isLoading ? t("auth:loadingFaculties") : t("auth:selectFaculty")}
                  disabled={facultiesQuery.isLoading}
                  onValueChange={() => form.setValue("study_program_id", "")}
                  options={[
                    { value: "", label: facultiesQuery.isLoading ? t("auth:loadingFaculties") : t("auth:selectFaculty") },
                    ...(facultiesQuery.isSuccess && facultiesQuery.data.length === 0
                      ? [{ value: "", label: t("auth:noFacultiesAvailable"), disabled: true }]
                      : (facultiesQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))),
                  ]}
                />
              )}
              <SelectFormField
                control={form.control}
                name="study_program_id"
                label={t("auth:studyProgram")}
                placeholder={studyProgramsQuery.isLoading ? t("auth:loadingStudyPrograms") : t("auth:selectStudyProgram")}
                disabled={!universityId || studyProgramsQuery.isLoading}
                options={
                  studyProgramsQuery.isSuccess && studyProgramsQuery.data.length === 0
                    ? [{ value: "", label: t("auth:noStudyProgramsAvailable"), disabled: true }]
                    : (studyProgramsQuery.data ?? []).map((item) => ({ value: String(item.id), label: item.name }))
                }
              />
              <PasswordField control={form.control} name="password" label={t("auth:password")} />
              <PasswordField control={form.control} name="password_confirmation" label={t("auth:passwordConfirmation")} />
              <div className="md:col-span-2">
                <Button type="submit" className="w-full" disabled={mutation.isPending || !canSubmit}>
                  {mutation.isPending ? t("auth:submittingRegistration") : t("auth:submitRegistration")}
                </Button>
                <p className="mt-4 text-center text-sm text-muted-foreground">
                  <Link to="/login" className="text-primary hover:underline">{t("auth:backToLogin")}</Link>
                </p>
              </div>
            </form>
          </Form>
        </CardContent>
      </Card>
    </PublicShell>
  );
}

type ValidationMessages = {
  required: string;
  email: string;
  passwordConfirmationMismatch: string;
  phone: string;
};

function validationMessages(t: ReturnType<typeof useTranslation>["t"]): ValidationMessages {
  return {
    required: t("common:validation.required"),
    email: t("common:validation.email"),
    passwordConfirmationMismatch: t("common:validation.passwordConfirmationMismatch"),
    phone: t("common:validation.phoneNumber"),
  };
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
