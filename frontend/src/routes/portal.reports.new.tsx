import { createFileRoute, Navigate, Link } from "@tanstack/react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { ApiError } from "@/lib/api-client";
import { getMasterData, masterDataQueryKeys } from "@/lib/master-data-api";
import { submitReport } from "@/lib/portal-api";
import { useAuth } from "@/hooks/use-auth";
import { hasPortalAccess } from "@/lib/auth-roles";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

export const Route = createFileRoute("/portal/reports/new")({
  component: NewPortalReportPage,
});

function NewPortalReportPage() {
  const { t } = useTranslation(["portal", "common"]);
  const { roleCode } = useAuth();
  const [step, setStep] = useState(1);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [success, setSuccess] = useState<{ registration_number: string; tracking_code?: string | null } | null>(null);
  const [form, setForm] = useState({
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
  });

  const reportTypesQuery = useQuery({ queryKey: masterDataQueryKeys.list("report-types"), queryFn: () => getMasterData("report-types") });
  const categoriesQuery = useQuery({ queryKey: masterDataQueryKeys.list("report-categories"), queryFn: () => getMasterData("report-categories") });
  const locationTypesQuery = useQuery({ queryKey: masterDataQueryKeys.list("location-types"), queryFn: () => getMasterData("location-types") });
  const campusStatusesQuery = useQuery({ queryKey: masterDataQueryKeys.list("campus-statuses"), queryFn: () => getMasterData("campus-statuses") });
  const relationsQuery = useQuery({ queryKey: masterDataQueryKeys.list("relations"), queryFn: () => getMasterData("relations") });

  const mutation = useMutation({
    mutationFn: () =>
      submitReport({
        ...form,
        incident_time: form.incident_time || null,
        location_type: form.location_type || null,
        respondent_name: form.respondent_name || null,
        respondent_campus_status: form.respondent_campus_status || null,
        respondent_relation: form.respondent_relation || null,
        respondent_details: form.respondent_details || null,
        witness_info: form.witness_info || null,
        reporter_phone: form.report_type === "confidential" ? form.reporter_phone || null : null,
      }),
    onSuccess: (data) => {
      setSuccess(data);
      setErrors({});
      toast.success(t("reportSubmittedSuccess"));
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? {});
        toast.error(error.message);
      }
    },
  });

  if (!hasPortalAccess(roleCode)) return <Navigate to="/login" replace />;

  const update = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  if (success) {
    return (
      <div className="mx-auto max-w-xl">
        <Card>
          <CardHeader>
            <CardTitle>{t("reportSubmittedTitle")}</CardTitle>
            <p className="text-sm text-muted-foreground">{t("reportSubmittedBody")}</p>
          </CardHeader>
          <CardContent className="space-y-4">
            <InfoRow label={t("registrationNumber")} value={success.registration_number} />
            {success.tracking_code && (
              <div className="rounded-md border border-primary/30 bg-primary/10 p-3">
                <div className="text-xs text-muted-foreground">{t("trackingCode")}</div>
                <div className="font-mono text-lg font-semibold">{success.tracking_code}</div>
                <p className="mt-2 text-xs text-muted-foreground">{t("saveTrackingCode")}</p>
              </div>
            )}
            <Button asChild className="w-full"><Link to="/portal/reports">{t("myReports")}</Link></Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("newReport")}</h1>
        <p className="text-sm text-muted-foreground">{t("newReportSubtitle")}</p>
      </div>
      <div className="rounded-md border bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        {t("reportContentWarning")}
      </div>
      <Card>
        <CardHeader>
          <CardTitle>{t("stepLabel", { step })}</CardTitle>
        </CardHeader>
        <CardContent>
          <form
            className="space-y-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (step < 3) {
                setStep(step + 1);
                return;
              }
              mutation.mutate();
            }}
          >
            {step === 1 && (
              <div className="grid gap-4 md:grid-cols-2">
                <SelectField label={t("reportType")} value={form.report_type} onChange={(value) => update("report_type", value)} error={errors.report_type?.[0]}>
                  {(reportTypesQuery.data?.length ? reportTypesQuery.data : [
                    { code: "open", name: "Open" },
                    { code: "confidential", name: "Confidential" },
                    { code: "anonymous", name: "Anonymous" },
                  ]).map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
                </SelectField>
                <SelectField label={t("category")} value={form.category_code} onChange={(value) => update("category_code", value)} error={errors.category_code?.[0]}>
                  <option value="">{t("selectCategory")}</option>
                  {(categoriesQuery.data ?? []).map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
                </SelectField>
              </div>
            )}

            {step === 2 && (
              <div className="grid gap-4">
                <Field label={t("chronology")} error={errors.chronology?.[0]}>
                  <Textarea value={form.chronology} onChange={(e) => update("chronology", e.target.value)} minLength={50} maxLength={10000} required />
                  <p className="text-xs text-muted-foreground">{form.chronology.length}/10000</p>
                </Field>
                <div className="grid gap-4 md:grid-cols-2">
                  <Field label={t("incidentDate")} error={errors.incident_date?.[0]}>
                    <Input type="date" value={form.incident_date} onChange={(e) => update("incident_date", e.target.value)} required />
                  </Field>
                  <Field label={t("incidentTime")} error={errors.incident_time?.[0]}>
                    <Input type="time" value={form.incident_time} onChange={(e) => update("incident_time", e.target.value)} />
                  </Field>
                </div>
                <Field label={t("incidentLocation")} error={errors.incident_location?.[0]}>
                  <Input value={form.incident_location} onChange={(e) => update("incident_location", e.target.value)} required />
                </Field>
                <SelectField label={t("locationType")} value={form.location_type} onChange={(value) => update("location_type", value)} error={errors.location_type?.[0]}>
                  <option value="">{t("optional")}</option>
                  {(locationTypesQuery.data ?? []).map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
                </SelectField>
              </div>
            )}

            {step === 3 && (
              <div className="grid gap-4">
                <Field label={t("respondentName")} error={errors.respondent_name?.[0]}>
                  <Input value={form.respondent_name} onChange={(e) => update("respondent_name", e.target.value)} />
                </Field>
                <div className="grid gap-4 md:grid-cols-2">
                  <SelectField label={t("respondentCampusStatus")} value={form.respondent_campus_status} onChange={(value) => update("respondent_campus_status", value)} error={errors.respondent_campus_status?.[0]}>
                    <option value="">{t("optional")}</option>
                    {(campusStatusesQuery.data ?? []).map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
                  </SelectField>
                  <SelectField label={t("respondentRelation")} value={form.respondent_relation} onChange={(value) => update("respondent_relation", value)} error={errors.respondent_relation?.[0]}>
                    <option value="">{t("optional")}</option>
                    {(relationsQuery.data ?? []).map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
                  </SelectField>
                </div>
                <Field label={t("respondentDetails")} error={errors.respondent_details?.[0]}>
                  <Textarea value={form.respondent_details} onChange={(e) => update("respondent_details", e.target.value)} />
                </Field>
                <Field label={t("witnessInfo")} error={errors.witness_info?.[0]}>
                  <Textarea value={form.witness_info} onChange={(e) => update("witness_info", e.target.value)} />
                </Field>
                {form.report_type === "confidential" && (
                  <Field label={t("reporterPhone")} error={errors.reporter_phone?.[0]}>
                    <Input value={form.reporter_phone} onChange={(e) => update("reporter_phone", e.target.value)} />
                  </Field>
                )}
              </div>
            )}

            <div className="flex justify-between">
              <Button type="button" variant="outline" disabled={step === 1} onClick={() => setStep(step - 1)}>
                {t("common:back")}
              </Button>
              <Button type="submit" disabled={mutation.isPending}>
                {step < 3 ? t("common:next") : mutation.isPending ? t("submittingReport") : t("submitReport")}
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

function SelectField({
  label,
  value,
  onChange,
  error,
  children,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <Field label={label} error={error}>
      <select className="h-10 rounded-md border bg-background px-3 text-sm" value={value} onChange={(event) => onChange(event.target.value)}>
        {children}
      </select>
    </Field>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-4 rounded-md border bg-muted/40 p-3 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-mono font-medium">{value}</span>
    </div>
  );
}
