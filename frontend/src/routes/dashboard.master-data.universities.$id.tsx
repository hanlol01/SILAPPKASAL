import { createFileRoute, Link } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { getCampusUniversity, updateCampusUniversity, type UniversityPayload } from "@/lib/campus-admin-api";
import { ApiError } from "@/lib/api-client";
import { apiErrorMessage } from "@/lib/form-errors";
import { formatCampusType } from "@/lib/format-labels";
import { StaffManagement } from "@/components/staff-management";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { SelectInput } from "@/components/form-fields";

export const Route = createFileRoute("/dashboard/master-data/universities/$id")({
  component: UniversityDetailPage,
});

const universityTypes = ["universitas", "institut", "sekolah_tinggi", "politeknik", "akademi"];

function UniversityDetailPage() {
  const { id } = Route.useParams();
  const { t } = useTranslation(["dashboard"]);
  const universityQuery = useQuery({
    queryKey: ["campus-admin", "university", id],
    queryFn: () => getCampusUniversity(id),
  });

  if (universityQuery.isLoading) return <Skeleton className="h-96 w-full" />;
  if (!universityQuery.data) return null;

  const university = universityQuery.data;

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[
        { label: t("dashboard:masterData.title"), link: <Link to="/dashboard/master-data/universities">{t("dashboard:masterData.title")}</Link> },
        { label: university.name },
      ]} />
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{university.name}</h1>
        <p className="text-sm text-muted-foreground">{t("dashboard:masterData.universityDetailSubtitle")}</p>
      </div>
      <UniversityProfile universityId={university.id} />
      <StaffManagement universityId={university.id} universityName={university.name} canManageAdministrators />
    </div>
  );
}

function UniversityProfile({ universityId }: { universityId: number }) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const universityQuery = useQuery({
    queryKey: ["campus-admin", "university", universityId],
    queryFn: () => getCampusUniversity(universityId),
  });
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState<UniversityPayload | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const mutation = useMutation({
    mutationFn: () => updateCampusUniversity(universityId, form!),
    onSuccess: () => {
      toast.success(t("dashboard:masterData.universityUpdated"));
      queryClient.invalidateQueries({ queryKey: ["campus-admin", "university", universityId] });
      queryClient.invalidateQueries({ queryKey: ["campus-admin", "universities"] });
      setEditing(false);
      setErrors({});
    },
    onError: (error) => {
      if (error instanceof ApiError) setErrors(error.errors ?? {});
      toast.error(apiErrorMessage(error, t("dashboard:masterData.universitySaveError")));
    },
  });

  useEffect(() => {
    const university = universityQuery.data;
    if (university) setForm({
      code: university.code,
      name: university.name,
      abbreviation: university.abbreviation,
      address: university.address,
      website: university.website,
      email: university.email,
      hotline: university.hotline,
      type: university.type ?? "universitas",
      has_faculties: university.has_faculties,
      sort_order: university.sort_order,
    });
  }, [universityQuery.data]);

  if (!universityQuery.data || !form) return <Skeleton className="h-64 w-full" />;
  const university = universityQuery.data;
  const update = <K extends keyof UniversityPayload>(key: K, value: UniversityPayload[K]) => {
    setForm((current) => current ? { ...current, [key]: value } : current);
    setErrors((current) => ({ ...current, [key]: [] }));
  };

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
        <div className="space-y-1"><CardTitle>{t("dashboard:masterData.universityProfile")}</CardTitle><p className="text-sm text-muted-foreground">{t("dashboard:masterData.universityProfileDescription")}</p></div>
        <Button variant="outline" onClick={() => setEditing((current) => !current)}>{editing ? t("dashboard:common.cancel") : t("dashboard:common.edit")}</Button>
      </CardHeader>
      <CardContent>
        {editing ? (
          <form className="grid gap-3 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
            <Field label={t("dashboard:masterData.code")} error={errors.code?.[0]}><Input value={form.code} onChange={(event) => update("code", event.target.value)} required /></Field>
            <Field label={t("dashboard:masterData.name")} error={errors.name?.[0]}><Input value={form.name} onChange={(event) => update("name", event.target.value)} required /></Field>
            <Field label={t("dashboard:masterData.abbreviation")}><Input value={form.abbreviation ?? ""} onChange={(event) => update("abbreviation", event.target.value || null)} /></Field>
            <Field label={t("dashboard:masterData.type")} error={errors.type?.[0]}><SelectInput value={form.type} onValueChange={(value) => update("type", value)} options={universityTypes.map((type) => ({ value: type, label: formatCampusType(t, type) }))} placeholder={t("dashboard:masterData.type")} /></Field>
            <Field label={t("dashboard:masterData.website")} error={errors.website?.[0]}><Input value={form.website ?? ""} onChange={(event) => update("website", event.target.value || null)} /></Field>
            <Field label={`${t("dashboard:masterData.email")} (${t("dashboard:masterData.optional")})`} error={errors.email?.[0]}><Input type="email" value={form.email ?? ""} onChange={(event) => update("email", event.target.value || null)} /></Field>
            <Field label={t("dashboard:masterData.hotline")} error={errors.hotline?.[0]}><Input value={form.hotline ?? ""} onChange={(event) => update("hotline", event.target.value || null)} /></Field>
            <Field label={t("dashboard:masterData.sortOrder")}><Input type="number" min={0} value={form.sort_order ?? 0} onChange={(event) => update("sort_order", Number(event.target.value))} /></Field>
            <Field label={t("dashboard:masterData.hasFaculties")}><SelectInput value={String(form.has_faculties)} onValueChange={(value) => update("has_faculties", value === "true")} options={[{ value: "true", label: t("dashboard:masterData.yes") }, { value: "false", label: t("dashboard:masterData.no") }]} placeholder={t("dashboard:masterData.hasFaculties")} /></Field>
            <div className="md:col-span-2"><Field label={t("dashboard:masterData.address")} error={errors.address?.[0]}><Textarea value={form.address ?? ""} onChange={(event) => update("address", event.target.value)} required /></Field></div>
            <div className="md:col-span-2 flex justify-end"><Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? t("dashboard:common.saving") : t("dashboard:common.save")}</Button></div>
          </form>
        ) : (
          <dl className="grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <Detail label={t("dashboard:masterData.code")} value={university.code} />
            <Detail label={t("dashboard:masterData.abbreviation")} value={university.abbreviation} />
            <Detail label={t("dashboard:masterData.type")} value={formatCampusType(t, university.type)} />
            <Detail label={t("dashboard:masterData.address")} value={university.address} />
            <Detail label={t("dashboard:masterData.email")} value={university.email} />
            <Detail label={t("dashboard:masterData.hotline")} value={university.hotline} />
          </dl>
        )}
      </CardContent>
    </Card>
  );
}

function Detail({ label, value }: { label: string; value?: string | null }) {
  return <div className="min-w-0"><dt className="text-xs font-medium uppercase text-muted-foreground">{label}</dt><dd className="mt-1 break-words">{value || "-"}</dd></div>;
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return <div className="grid gap-2"><Label>{label}</Label>{children}{error && <p className="text-xs text-destructive">{error}</p>}</div>;
}
