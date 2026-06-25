import { createFileRoute } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

import {
  campusAdminQueryKeys,
  createCampusFaculty,
  getCampusFaculties,
  getCampusUniversities,
  toggleCampusFaculty,
  updateCampusFaculty,
  type CampusFaculty,
  type FacultyPayload,
} from "@/lib/campus-admin-api";
import { ApiError } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export const Route = createFileRoute("/dashboard/master-data/faculties")({
  component: FacultiesPage,
});

function FacultiesPage() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [universityId, setUniversityId] = useState("");
  const [editing, setEditing] = useState<CampusFaculty | null>(null);
  const [creating, setCreating] = useState(false);
  const query = useMemo(() => ({ search: search || undefined, university_id: universityId || undefined, per_page: 50 }), [search, universityId]);
  const facultiesQuery = useQuery({ queryKey: campusAdminQueryKeys.faculties(query), queryFn: () => getCampusFaculties(query) });
  const universitiesQuery = useQuery({ queryKey: campusAdminQueryKeys.universities({ per_page: 50 }), queryFn: () => getCampusUniversities({ per_page: 50 }) });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["campus-admin", "faculties"] });
  const toggleMutation = useMutation({
    mutationFn: toggleCampusFaculty,
    onSuccess: () => {
      toast.success("Faculty status updated");
      invalidate();
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Unable to update faculty status"),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-3">
        <Input className="max-w-sm" placeholder="Search faculties" value={search} onChange={(event) => setSearch(event.target.value)} />
        <select className="h-10 rounded-md border bg-background px-3 text-sm" value={universityId} onChange={(event) => setUniversityId(event.target.value)}>
          <option value="">All universities</option>
          {(universitiesQuery.data?.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select>
        <Button onClick={() => setCreating(true)}>Create Faculty</Button>
      </div>
      <Card>
        <CardContent className="overflow-x-auto p-0">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="p-3">Code</th>
                <th className="p-3">Name</th>
                <th className="p-3">University</th>
                <th className="p-3">Programs</th>
                <th className="p-3">Status</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {(facultiesQuery.data?.data ?? []).map((item) => (
                <tr key={item.id} className="border-t">
                  <td className="p-3 font-mono text-xs">{item.code}</td>
                  <td className="p-3 font-medium">{item.name}</td>
                  <td className="p-3">{item.university?.name ?? "-"}</td>
                  <td className="p-3">{item.study_programs_count ?? 0}</td>
                  <td className="p-3"><Badge variant={item.is_active ? "default" : "outline"}>{item.is_active ? "Active" : "Inactive"}</Badge></td>
                  <td className="p-3 text-right">
                    <div className="flex justify-end gap-2">
                      <Button size="sm" variant="outline" onClick={() => setEditing(item)}>Edit</Button>
                      <Button size="sm" variant="outline" onClick={() => toggleMutation.mutate(item.id)}>
                        {item.is_active ? "Deactivate" : "Activate"}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {facultiesQuery.isSuccess && facultiesQuery.data.data.length === 0 && (
                <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">No faculties found.</td></tr>
              )}
              {facultiesQuery.isLoading && (
                <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">Loading faculties...</td></tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <FacultyDialog
        open={creating || Boolean(editing)}
        faculty={editing}
        universities={universitiesQuery.data?.data ?? []}
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

function FacultyDialog({
  open,
  faculty,
  universities,
  onOpenChange,
  onSaved,
}: {
  open: boolean;
  faculty: CampusFaculty | null;
  universities: { id: number; name: string; has_faculties?: boolean; is_active?: boolean }[];
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}) {
  const [form, setForm] = useState<FacultyPayload>(() => toPayload(faculty));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const mutation = useMutation({
    mutationFn: () => faculty ? updateCampusFaculty(faculty.id, form) : createCampusFaculty(form),
    onSuccess: () => {
      toast.success(faculty ? "Faculty updated" : "Faculty created");
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

  useEffect(() => setForm(toPayload(faculty)), [faculty, open]);
  const selectableUniversities = universities.filter((item) => item.is_active !== false && item.has_faculties !== false);
  const update = <K extends keyof FacultyPayload>(key: K, value: FacultyPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [key]: [] }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>{faculty ? "Edit Faculty" : "Create Faculty"}</DialogTitle></DialogHeader>
        <form className="grid gap-3" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
          <Field label="University" error={errors.university_id?.[0]}>
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={form.university_id || ""} onChange={(event) => update("university_id", Number(event.target.value))} required>
              <option value="">Select university</option>
              {selectableUniversities.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </Field>
          <Field label="Code" error={errors.code?.[0]}><Input value={form.code} onChange={(event) => update("code", event.target.value)} required /></Field>
          <Field label="Name" error={errors.name?.[0]}><Input value={form.name} onChange={(event) => update("name", event.target.value)} required /></Field>
          <Field label="Sort order"><Input type="number" value={form.sort_order ?? 0} onChange={(event) => update("sort_order", Number(event.target.value))} /></Field>
          <Button type="submit" disabled={mutation.isPending}>{mutation.isPending ? "Saving..." : "Save"}</Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function toPayload(faculty: CampusFaculty | null): FacultyPayload {
  return {
    university_id: faculty?.university_id ?? 0,
    code: faculty?.code ?? "",
    name: faculty?.name ?? "",
    sort_order: faculty?.sort_order ?? 0,
  };
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
