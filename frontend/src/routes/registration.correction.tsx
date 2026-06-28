import { createFileRoute, Navigate, useNavigate } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";

import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  campusQueryKeys,
  correctReporterRegistration,
  getFaculties,
  getStudyPrograms,
  getUniversities,
} from "@/lib/registration-api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Form } from "@/components/ui/form";
import { PasswordField, SelectFormField, TextInputField } from "@/components/form-fields";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/registration/correction")({
  component: RegistrationCorrectionPage,
});

function RegistrationCorrectionPage() {
  const { t } = useTranslation(["auth", "portal", "common"]);
  const { registration, setRegistration } = useAuth();
  const navigate = useNavigate();
  const form = useForm<CorrectionValues>({
    resolver: zodResolver(createCorrectionSchema(validationMessages(t))),
    defaultValues: toFormValues(registration),
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

  useEffect(() => {
    form.reset(toFormValues(registration));
  }, [form, registration]);

  useEffect(() => {
    if (selectedUniversity && !hasFaculties && facultyId) {
      form.setValue("faculty_id", "");
    }
  }, [facultyId, form, hasFaculties, selectedUniversity]);

  const mutation = useMutation({
    mutationFn: (values: CorrectionValues) =>
      correctReporterRegistration({
        email: values.email,
        password: values.password,
        name: values.name,
        nim: values.nim,
        phone_number: values.phone_number,
        university_id: Number(values.university_id),
        faculty_id: effectiveFacultyId,
        study_program_id: Number(values.study_program_id),
        new_password: values.new_password || undefined,
        new_password_confirmation: values.new_password_confirmation || undefined,
      }),
    onSuccess: (data) => {
      setRegistration(data);
      toast.success(t("correctionSubmitted"));
      navigate({ to: "/registration/pending", replace: true });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("common:unexpectedError")));
    },
  });

  if (!registration) return <Navigate to="/login" replace />;
  if (registration.status !== "rejected") return <Navigate to="/registration/pending" replace />;

  const canSubmit = Boolean(
    values.password &&
    values.name &&
    values.nim &&
    values.phone_number &&
    values.university_id &&
    values.study_program_id,
  );

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
          <Form {...form}>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
              <TextInputField control={form.control} name="email" label={t("auth:emailAddress")} readOnly />
              <PasswordField control={form.control} name="password" label={t("auth:currentPassword")} />
              <TextInputField control={form.control} name="name" label={t("auth:fullName")} />
              <TextInputField control={form.control} name="nim" label={t("auth:nim")} />
              <TextInputField control={form.control} name="phone_number" label={t("portal:phoneNumber")} />
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
              <PasswordField control={form.control} name="new_password" label={t("auth:optionalNewPassword")} />
              <PasswordField control={form.control} name="new_password_confirmation" label={t("auth:confirmNewPassword")} />
              <div className="md:col-span-2">
                <Button type="submit" className="w-full" disabled={mutation.isPending || !canSubmit}>
                  {mutation.isPending ? t("auth:submittingCorrection") : t("auth:submitCorrection")}
                </Button>
              </div>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}

function createCorrectionSchema(messages: ValidationMessages) {
  return z
    .object({
      email: z.string().min(1, messages.required).email(messages.email),
      password: z.string().min(1, messages.required),
      name: z.string().min(1, messages.required),
      nim: z.string().min(1, messages.required),
      phone_number: z.string().min(1, messages.required),
      university_id: z.string().min(1, messages.required),
      faculty_id: z.string().optional(),
      study_program_id: z.string().min(1, messages.required),
      new_password: z.string().optional(),
      new_password_confirmation: z.string().optional(),
    })
    .superRefine((values, context) => {
      if (!values.new_password && !values.new_password_confirmation) return;

      if (values.new_password !== values.new_password_confirmation) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["new_password_confirmation"],
          message: messages.passwordConfirmationMismatch,
        });
      }
    });
}

type CorrectionValues = z.infer<ReturnType<typeof createCorrectionSchema>>;

function toFormValues(registration: ReturnType<typeof useAuth>["registration"]): CorrectionValues {
  return {
    email: registration?.email ?? "",
    password: "",
    name: registration?.name ?? "",
    nim: registration?.nim ?? "",
    phone_number: registration?.phone_number ?? "",
    university_id: registration?.university_id ? String(registration.university_id) : "",
    faculty_id: registration?.faculty_id ? String(registration.faculty_id) : "",
    study_program_id: registration?.study_program_id ? String(registration.study_program_id) : "",
    new_password: "",
    new_password_confirmation: "",
  };
}

type ValidationMessages = {
  required: string;
  email: string;
  passwordConfirmationMismatch: string;
};

function validationMessages(t: ReturnType<typeof useTranslation>["t"]): ValidationMessages {
  return {
    required: t("common:validation.required"),
    email: t("common:validation.email"),
    passwordConfirmationMismatch: t("common:validation.passwordConfirmationMismatch"),
  };
}
