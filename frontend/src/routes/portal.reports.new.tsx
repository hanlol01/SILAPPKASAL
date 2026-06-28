import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery } from "@tanstack/react-query";
import { createFileRoute, Link, Navigate } from "@tanstack/react-router";
import { Check, Loader2 } from "lucide-react";
import { useState, type ReactNode } from "react";
import { type Control, type FieldPath, useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";

import { ApiError } from "@/lib/api-client";
import { hasPortalAccess } from "@/lib/auth-roles";
import { formatLocationType } from "@/lib/format-labels";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { getMasterData, masterDataQueryKeys } from "@/lib/master-data-api";
import { submitReport } from "@/lib/portal-api";
import type { ReportSubmissionPayload } from "@/lib/portal-types";
import { useAuth } from "@/hooks/use-auth";
import { SelectFormField, TextInputField, type SelectOption } from "@/components/form-fields";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DatePicker } from "@/components/ui/date-picker";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Textarea } from "@/components/ui/textarea";
import { TimePicker } from "@/components/ui/time-picker";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/portal/reports/new")({
  component: NewPortalReportPage,
});

type WizardStep = 1 | 2 | 3;

const REPORT_TYPE_LABEL_KEYS: Record<string, "Open" | "Confidential" | "Anonymous"> = {
  open: "Open",
  confidential: "Confidential",
  anonymous: "Anonymous",
};

const REPORT_TYPE_VALUES = new Set(Object.keys(REPORT_TYPE_LABEL_KEYS));

const DEFAULT_VALUES = {
  report_type: "confidential",
  category_code: "",
  chronology: "",
  incident_date: "",
  incident_time: "",
  incident_location: "",
  location_type: "",
  respondent_name: "",
  respondent_campus_status: "",
  respondent_relation: "",
  respondent_details: "",
  witness_info: "",
  reporter_phone: "",
};

type WizardValues = z.infer<ReturnType<typeof createWizardSchema>>;

function NewPortalReportPage() {
  const { t } = useTranslation(["portal", "common"]);
  const { roleCode } = useAuth();
  const [step, setStep] = useState<WizardStep>(1);
  const [success, setSuccess] = useState<{ registration_number: string; tracking_code?: string | null } | null>(null);

  const wizardSchema = createWizardSchema({
    required: t("common:validation.required"),
    dateFuture: t("portal:reportWizard.incidentDateFuture"),
    timeFuture: t("portal:reportWizard.incidentTimeFuture"),
    timeInvalid: t("portal:reportWizard.invalidTimeFormat"),
    chronologyMin: t("portal:reportWizard.chronologyMin"),
    chronologyMax: t("portal:reportWizard.chronologyMax"),
  });
  const form = useForm<WizardValues>({
    resolver: zodResolver(wizardSchema),
    mode: "onBlur",
    defaultValues: DEFAULT_VALUES,
  });

  const reportType = form.watch("report_type");
  const chronology = form.watch("chronology") ?? "";
  const incidentDate = form.watch("incident_date") ?? "";
  const maxIncidentTime = incidentDate === formatDateValue(new Date()) ? formatTimeValue(new Date()) : undefined;

  const reportTypesQuery = useQuery({ queryKey: masterDataQueryKeys.list("report-types"), queryFn: () => getMasterData("report-types") });
  const categoriesQuery = useQuery({ queryKey: masterDataQueryKeys.list("report-categories"), queryFn: () => getMasterData("report-categories") });
  const locationTypesQuery = useQuery({ queryKey: masterDataQueryKeys.list("location-types"), queryFn: () => getMasterData("location-types") });
  const campusStatusesQuery = useQuery({ queryKey: masterDataQueryKeys.list("campus-statuses"), queryFn: () => getMasterData("campus-statuses") });
  const relationsQuery = useQuery({ queryKey: masterDataQueryKeys.list("relations"), queryFn: () => getMasterData("relations") });

  const stepFields: Record<WizardStep, FieldPath<WizardValues>[]> = {
    1: ["report_type", "category_code"],
    2: ["chronology", "incident_date", "incident_time", "incident_location", "location_type"],
    3: ["respondent_name", "respondent_campus_status", "respondent_relation", "respondent_details", "witness_info", "reporter_phone"],
  };

  const mutation = useMutation({
    mutationFn: (values: WizardValues) => submitReport(toReportPayload(values)),
    onSuccess: (data) => {
      setSuccess(data);
      form.reset(DEFAULT_VALUES);
      toast.success(t("portal:reportSubmittedSuccess"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      if (hasLaravelMessage(error, "incident_time", "validation.date_format")) {
        form.setError("incident_time", {
          type: "server",
          message: t("portal:reportWizard.invalidTimeFormat"),
        });
      }
      const errorStep = stepForLaravelErrors(error);
      if (errorStep) setStep(errorStep);
      toast.error(apiErrorMessage(error, t("common:unexpectedError")));
    },
  });

  if (!hasPortalAccess(roleCode)) return <Navigate to="/login" replace />;

  const reportTypeOptions: SelectOption[] = reportTypesQuery.isSuccess
    ? reportTypesQuery.data
        .map((item) => normalizeReportTypeValue(item.name) ?? normalizeReportTypeValue(item.code))
        .filter((value): value is string => Boolean(value))
        .map((value) => ({ value, label: t(`portal:${REPORT_TYPE_LABEL_KEYS[value]}`) }))
    : [];
  const categoryOptions = toMasterDataOptions(categoriesQuery.data);
  const locationTypeOptions = withOptionalOption(toLocationTypeOptions(locationTypesQuery.data, t), t("portal:optional"));
  const campusStatusOptions = withOptionalOption(toMasterDataOptions(campusStatusesQuery.data), t("portal:optional"));
  const relationOptions = withOptionalOption(toMasterDataOptions(relationsQuery.data), t("portal:optional"));
  const timeQuickPicks = [
    { label: t("portal:reportWizard.timeMorning"), value: "08:00" },
    { label: t("portal:reportWizard.timeAfternoon"), value: "13:00" },
    { label: t("portal:reportWizard.timeEvening"), value: "17:00" },
    { label: t("portal:reportWizard.timeNight"), value: "20:00" },
  ];

  async function goNext() {
    const isStepValid = await form.trigger(stepFields[step], { shouldFocus: true });
    if (isStepValid && step < 3) setStep((step + 1) as WizardStep);
  }

  function goBack() {
    if (step > 1) setStep((step - 1) as WizardStep);
  }

  if (success) {
    return (
      <div className="mx-auto max-w-xl">
        <Card>
          <CardHeader>
            <CardTitle>{t("portal:reportSubmittedTitle")}</CardTitle>
            <p className="text-sm text-muted-foreground">{t("portal:reportSubmittedBody")}</p>
          </CardHeader>
          <CardContent className="space-y-4">
            <InfoRow label={t("portal:registrationNumber")} value={success.registration_number} />
            {success.tracking_code && (
              <div className="rounded-md border border-primary/30 bg-primary/10 p-3">
                <div className="text-xs text-muted-foreground">{t("portal:trackingCode")}</div>
                <div className="font-mono text-lg font-semibold">{success.tracking_code}</div>
                <p className="mt-2 text-xs text-muted-foreground">{t("portal:saveTrackingCode")}</p>
              </div>
            )}
            <Button asChild className="w-full">
              <Link to="/portal/reports">{t("portal:myReports")}</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("portal:newReport")}</h1>
        <p className="text-sm text-muted-foreground">{t("portal:newReportSubtitle")}</p>
      </div>
      <div className="rounded-md border bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        {t("portal:reportContentWarning")}
      </div>
      <WizardProgress
        currentStep={step}
        steps={[
          t("portal:reportWizard.stepIdentification"),
          t("portal:reportWizard.stepIncident"),
          t("portal:reportWizard.stepRespondent"),
        ]}
      />
      <Card>
        <CardHeader>
          <CardTitle>{t("portal:stepLabel", { step })}</CardTitle>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
              {step === 1 && (
                <div className="grid gap-4 md:grid-cols-2">
                  <SelectFormField
                    control={form.control}
                    name="report_type"
                    label={t("portal:reportType")}
                    placeholder={t("portal:reportType")}
                    disabled={!reportTypesQuery.isSuccess || mutation.isPending}
                    options={reportTypeOptions}
                  />
                  <SelectFormField
                    control={form.control}
                    name="category_code"
                    label={t("portal:category")}
                    placeholder={t("portal:selectCategory")}
                    disabled={categoriesQuery.isLoading || mutation.isPending}
                    options={categoryOptions}
                  />
                </div>
              )}

              {step === 2 && (
                <div className="grid gap-4">
                  <FormField
                    control={form.control}
                    name="chronology"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("portal:chronology")}</FormLabel>
                        <FormControl>
                          <Textarea {...field} value={field.value ?? ""} minLength={50} maxLength={10000} disabled={mutation.isPending} />
                        </FormControl>
                        <FormDescription>{chronology.length}/10000</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                      control={form.control}
                      name="incident_date"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("portal:incidentDate")}</FormLabel>
                          <FormControl>
                            <DatePicker
                              value={field.value ?? ""}
                              onChange={field.onChange}
                              onBlur={field.onBlur}
                              name={field.name}
                              placeholder={t("portal:reportWizard.selectIncidentDate")}
                              disableFuture
                              disabled={mutation.isPending}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="incident_time"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("portal:incidentTime")}</FormLabel>
                          <FormControl>
                            <TimePicker
                              value={field.value}
                              onChange={field.onChange}
                              onBlur={field.onBlur}
                              name={field.name}
                              quickPicks={timeQuickPicks}
                              unknownLabel={t("portal:reportWizard.timeUnknown")}
                              hourLabel={t("portal:reportWizard.timeHour")}
                              minuteLabel={t("portal:reportWizard.timeMinute")}
                              placeholder="Contoh : 00.00"
                              maxTime={maxIncidentTime}
                              disabled={mutation.isPending}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>
                  <TextInputField
                    control={form.control}
                    name="incident_location"
                    label={t("portal:incidentLocation")}
                    disabled={mutation.isPending}
                  />
                  <SelectFormField
                    control={form.control}
                    name="location_type"
                    label={t("portal:locationType")}
                    placeholder={t("portal:optional")}
                    disabled={locationTypesQuery.isLoading || mutation.isPending}
                    options={locationTypeOptions}
                  />
                </div>
              )}

              {step === 3 && (
                <div className="grid gap-4">
                  <TextInputField
                    control={form.control}
                    name="respondent_name"
                    label={t("portal:respondentName")}
                    disabled={mutation.isPending}
                  />
                  <div className="grid gap-4 md:grid-cols-2">
                    <SelectFormField
                      control={form.control}
                      name="respondent_campus_status"
                      label={t("portal:respondentCampusStatus")}
                      placeholder={t("portal:optional")}
                      disabled={campusStatusesQuery.isLoading || mutation.isPending}
                      options={campusStatusOptions}
                    />
                    <SelectFormField
                      control={form.control}
                      name="respondent_relation"
                      label={t("portal:respondentRelation")}
                      placeholder={t("portal:optional")}
                      disabled={relationsQuery.isLoading || mutation.isPending}
                      options={relationOptions}
                    />
                  </div>
                  <TextareaField
                    control={form.control}
                    name="respondent_details"
                    label={t("portal:respondentDetails")}
                    disabled={mutation.isPending}
                  />
                  <TextareaField
                    control={form.control}
                    name="witness_info"
                    label={t("portal:witnessInfo")}
                    disabled={mutation.isPending}
                  />
                  {reportType === "confidential" && (
                    <TextInputField
                      control={form.control}
                      name="reporter_phone"
                      label={t("portal:reporterPhone")}
                      disabled={mutation.isPending}
                    />
                  )}
                </div>
              )}

              <div className="flex justify-between">
                <Button type="button" variant="outline" disabled={step === 1 || mutation.isPending} onClick={goBack}>
                  {t("common:back")}
                </Button>
                {step < 3 ? (
                  <Button type="button" disabled={mutation.isPending} onClick={goNext}>
                    {t("common:next")}
                  </Button>
                ) : (
                  <Button type="submit" disabled={mutation.isPending}>
                    {mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                    {mutation.isPending ? t("portal:submittingReport") : t("portal:submitReport")}
                  </Button>
                )}
              </div>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}

function createWizardSchema(messages: {
  required: string;
  dateFuture: string;
  timeFuture: string;
  timeInvalid: string;
  chronologyMin: string;
  chronologyMax: string;
}) {
  const today = formatDateValue(new Date());

  const stepOneSchema = z.object({
    report_type: z.string().min(1, messages.required),
    category_code: z.string().min(1, messages.required),
  });

  const stepTwoSchema = z.object({
    chronology: z.string().min(50, messages.chronologyMin).max(10000, messages.chronologyMax),
    incident_date: z
      .string()
      .min(1, messages.required)
      .regex(/^\d{4}-\d{2}-\d{2}$/, messages.required)
      .refine((value) => value <= today, messages.dateFuture),
    incident_time: z
      .union([
        z.literal(""),
        z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, messages.timeInvalid),
        z.null(),
      ])
      .optional(),
    incident_location: z.string().min(1, messages.required),
    location_type: z.string().optional(),
  });

  const stepThreeSchema = z.object({
    respondent_name: z.string().optional(),
    respondent_campus_status: z.string().optional(),
    respondent_relation: z.string().optional(),
    respondent_details: z.string().optional(),
    witness_info: z.string().optional(),
    reporter_phone: z.string().optional(),
  });

  return stepOneSchema
    .merge(stepTwoSchema)
    .merge(stepThreeSchema)
    .superRefine((values, context) => {
      if (!values.incident_time || values.incident_date !== formatDateValue(new Date())) return;

      if (values.incident_time > formatTimeValue(new Date())) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          message: messages.timeFuture,
          path: ["incident_time"],
        });
      }
    });
}

function TextareaField({
  control,
  name,
  label,
  disabled,
}: {
  control: Control<WizardValues>;
  name: FieldPath<WizardValues>;
  label: string;
  disabled?: boolean;
}) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Textarea {...field} value={field.value ?? ""} disabled={disabled} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

function WizardProgress({ currentStep, steps }: { currentStep: WizardStep; steps: string[] }) {
  return (
    <div className="grid gap-3 sm:grid-cols-3">
      {steps.map((label, index) => {
        const stepNumber = (index + 1) as WizardStep;
        const isCurrent = currentStep === stepNumber;
        const isComplete = currentStep > stepNumber;

        return (
          <div key={label} className="flex items-center gap-3">
            <span
              className={cn(
                "flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm font-medium",
                isComplete && "border-primary bg-primary text-primary-foreground",
                isCurrent && "border-primary text-primary",
                !isComplete && !isCurrent && "border-muted-foreground/30 text-muted-foreground",
              )}
            >
              {isComplete ? <Check className="h-4 w-4" /> : stepNumber}
            </span>
            <span className={cn("text-sm font-medium", isCurrent ? "text-foreground" : "text-muted-foreground")}>{label}</span>
          </div>
        );
      })}
    </div>
  );
}

function stepForLaravelErrors(error: unknown): WizardStep | null {
  if (!(error instanceof ApiError) || !error.errors) return null;

  const errorKeys = Object.keys(error.errors);
  if (hasAny(errorKeys, ["report_type", "category_code"])) return 1;
  if (hasAny(errorKeys, ["chronology", "incident_date", "incident_time", "incident_location", "location_type"])) return 2;
  if (hasAny(errorKeys, ["respondent_name", "respondent_campus_status", "respondent_relation", "respondent_details", "witness_info", "reporter_phone"])) return 3;

  return null;
}

function hasAny(values: string[], candidates: string[]) {
  return candidates.some((candidate) => values.includes(candidate));
}

function hasLaravelMessage(error: unknown, field: string, message: string) {
  if (!(error instanceof ApiError) || !error.errors?.[field]) return false;
  return error.errors[field].some((item) => item.includes(message));
}

function toReportPayload(values: z.infer<ReturnType<typeof createWizardSchema>>): ReportSubmissionPayload {
  return {
    ...values,
    incident_time: nullifyEmpty(values.incident_time),
    location_type: nullifyEmpty(values.location_type),
    respondent_name: nullifyEmpty(values.respondent_name),
    respondent_campus_status: nullifyEmpty(values.respondent_campus_status),
    respondent_relation: nullifyEmpty(values.respondent_relation),
    respondent_details: nullifyEmpty(values.respondent_details),
    witness_info: nullifyEmpty(values.witness_info),
    reporter_phone: values.report_type === "confidential" ? nullifyEmpty(values.reporter_phone) : null,
  };
}

function nullifyEmpty(value?: string | null) {
  return value || null;
}

function normalizeReportTypeValue(value: string) {
  return REPORT_TYPE_VALUES.has(value) ? value : null;
}

function toMasterDataOptions(items: Array<{ code: string; name: string }> | undefined): SelectOption[] {
  return (items ?? []).map((item) => ({ value: item.code, label: item.name }));
}

function toLocationTypeOptions(items: Array<{ code: string; name: string }> | undefined, t: Parameters<typeof formatLocationType>[0]): SelectOption[] {
  return (items ?? []).map((item) => ({ value: item.code, label: formatLocationType(t, item.name) }));
}

function withOptionalOption(options: SelectOption[], label: string) {
  return [{ value: "", label }, ...options];
}

function formatDateValue(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function formatTimeValue(date: Date) {
  const hour = String(date.getHours()).padStart(2, "0");
  const minute = String(date.getMinutes()).padStart(2, "0");
  return `${hour}:${minute}`;
}

function InfoRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 rounded-md border bg-muted/40 p-3 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-mono font-medium">{value}</span>
    </div>
  );
}
