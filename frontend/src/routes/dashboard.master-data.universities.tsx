import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

import {
  campusAdminQueryKeys,
  createCampusUniversity,
  getCampusUniversities,
  toggleCampusUniversity,
  updateCampusUniversity,
  type CampusUniversity,
  type UniversityPayload,
} from "@/lib/campus-admin-api";
import { ApiError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export const Route = createFileRoute("/dashboard/master-data/universities")({
  component: UniversitiesPage,
});

const universityTypes = ["universitas", "institut", "sekolah_tinggi", "politeknik", "akademi"];

function UniversitiesPage() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<CampusUniversity | null>(null);
  const [creating, setCreating] = useState(false);
  const query = useMemo(() => ({ search: search || undefined, per_page: 50 }), [search]);
  const universitiesQuery = useQuery({
    queryKey: campusAdminQueryKeys.universities(query),
    queryFn: () => getCampusUniversities(query),
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["campus-admin", "universities"] });
  const toggleMutation = useMutation({
    mutationFn: toggleCampusUniversity,
    onSuccess: () => {
      toast.success("University status updated");
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Unable to update university status"),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-3">
        <Input className="max-w-sm" placeholder="Search universities" value={search} onChange={(event) => setSearch(event.target.value)} />
        <Button onClick={() => setCreating(true)}>Create University</Button>
      </div>
      <Card>
        <CardContent className="overflow-x-auto p-0">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="p-3">Code</th>
                <th className="p-3">Name</th>
                <th className="p-3">Type</th>
                <th className="p-3">Faculties</th>
                <th className="p-3">Status</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {(universitiesQuery.data?.data ?? []).map((item) => (
                <tr key={item.id} className="border-t">
                  <td className="p-3 font-mono text-xs">{item.code}</td>
                  <td className="p-3">
                    <div className="font-medium">{item.name}</div>
                    <div className="text-xs text-muted-foreground">{item.abbreviation ?? "-"}</div>
                  </td>
                  <td className="p-3 capitalize">{item.type?.replace("_", " ")}</td>
                  <td className="p-3">{item.has_faculties ? "Yes" : "No"}</td>
                  <td className="p-3"><StatusBadge active={item.is_active} /></td>
                  <td className="p-3 text-right">
                    <div className="flex justify-end gap-2">
                      <Button variant="outline" size="sm" onClick={() => setEditing(item)}>Edit</Button>
                      <Button variant="outline" size="sm" onClick={() => toggleMutation.mutate(item.id)}>
                        {item.is_active ? "Deactivate" : "Activate"}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {universitiesQuery.isSuccess && universitiesQuery.data.data.length === 0 && (
                <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">No universities found.</td></tr>
              )}
              {universitiesQuery.isLoading && (
                <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">Loading universities...</td></tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <UniversityDialog
        open={creating || Boolean(editing)}
        university={editing}
        onOpenChange={(open) => {
          if (!open) {
            setCreating(false);
            setEditing(null);
          }
        }}
        onSaved={() => {
          setCreating(false);
          setEditing(null);
          invalidate();
        }}
      />
    </div>
  );
}

function UniversityDialog({
  open,
  university,
  onOpenChange,
  onSaved,
}: {
  open: boolean;
  university: CampusUniversity | null;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}) {
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [form, setForm] = useState<UniversityPayload>(() => toPayload(university));
  const mutation = useMutation({
    mutationFn: () => university ? updateCampusUniversity(university.id, form) : createCampusUniversity(form),
    onSuccess: () => {
      toast.success(university ? "University updated" : "University created");
      setErrors({});
      onSaved();
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? {});
        toast.error(error.message);
      }
    },
  });

  useEffect(() => setForm(toPayload(university)), [university, open]);

  const update = <K extends keyof UniversityPayload>(key: K, value: UniversityPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: [] }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader><DialogTitle>{university ? "Edit University" : "Create University"}</DialogTitle></DialogHeader>
        <form className="grid gap-3 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
          <Field label="Code" error={errors.code?.[0]}><Input value={form.code} onChange={(event) => update("code", event.target.value)} required /></Field>
          <Field label="Name" error={errors.name?.[0]}><Input value={form.name} onChange={(event) => update("name", event.target.value)} required /></Field>
          <Field label="Abbreviation"><Input value={form.abbreviation ?? ""} onChange={(event) => update("abbreviation", event.target.value || null)} /></Field>
          <Field label="Type" error={errors.type?.[0]}>
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.type} onChange={(event) => update("type", event.target.value)} required>
              {universityTypes.map((type) => <option key={type} value={type}>{type.replace("_", " ")}</option>)}
            </select>
          </Field>
          <Field label="Website" error={errors.website?.[0]}><Input value={form.website ?? ""} onChange={(event) => update("website", event.target.value || null)} /></Field>
          <Field label="Email" error={errors.email?.[0]}><Input value={form.email ?? ""} onChange={(event) => update("email", event.target.value || null)} /></Field>
          <Field label="Hotline" error={errors.hotline?.[0]}><Input value={form.hotline ?? ""} onChange={(event) => update("hotline", event.target.value || null)} /></Field>
          <Field label="Sort order"><Input type="number" value={form.sort_order ?? 0} onChange={(event) => update("sort_order", Number(event.target.value))} /></Field>
          <Field label="Has faculties">
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={String(form.has_faculties)} onChange={(event) => update("has_faculties", event.target.value === "true")}>
              <option value="true">Yes</option>
              <option value="false">No</option>
            </select>
          </Field>
          <Field label="Address"><Input value={form.address ?? ""} onChange={(event) => update("address", event.target.value || null)} /></Field>
          <div className="md:col-span-2"><Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? "Saving..." : "Save"}</Button></div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function toPayload(university: CampusUniversity | null): UniversityPayload {
  return {
    code: university?.code ?? "",
    name: university?.name ?? "",
    abbreviation: university?.abbreviation ?? null,
    address: university?.address ?? null,
    website: university?.website ?? null,
    email: university?.email ?? null,
    hotline: university?.hotline ?? null,
    type: university?.type ?? "universitas",
    has_faculties: university?.has_faculties ?? true,
    sort_order: university?.sort_order ?? 0,
  };
}

function StatusBadge({ active }: { active: boolean }) {
  return <Badge variant={active ? "default" : "outline"}>{active ? "Active" : "Inactive"}</Badge>;
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
