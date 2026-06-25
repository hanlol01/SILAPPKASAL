import { createFileRoute, Link, Navigate, Outlet, useRouterState } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import {
  campusQueryKeys,
  getReporterRegistrations,
  getUniversities,
  registrationQueryKeys,
} from "@/lib/registration-api";
import { useAuth } from "@/hooks/use-auth";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";

export const Route = createFileRoute("/dashboard/registrations")({
  component: RegistrationsPage,
});

function RegistrationsPage() {
  const { roleCode } = useAuth();
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [universityId, setUniversityId] = useState("");
  const canAccess = roleCode === "admin" || roleCode === "super_admin";
  const query = useMemo(
    () => ({
      status: status || undefined,
      search: search || undefined,
      university_id: universityId || undefined,
      per_page: 50,
    }),
    [status, search, universityId],
  );

  const registrationsQuery = useQuery({
    queryKey: registrationQueryKeys.list(query),
    queryFn: () => getReporterRegistrations(query),
    enabled: canAccess,
  });
  const universitiesQuery = useQuery({
    queryKey: campusQueryKeys.universities(),
    queryFn: getUniversities,
    enabled: canAccess && roleCode === "super_admin",
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;
  if (pathname !== "/dashboard/registrations") return <Outlet />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Reporter Registrations</h1>
        <p className="text-sm text-muted-foreground">Review pending and rejected reporter account requests.</p>
      </div>
      <Card>
        <CardContent className="grid gap-3 p-4 md:grid-cols-4">
          <Input placeholder="Search name, email, NIM" value={search} onChange={(e) => setSearch(e.target.value)} />
          <select className="h-10 rounded-md border bg-background px-3 text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          {roleCode === "super_admin" && (
            <select className="h-10 rounded-md border bg-background px-3 text-sm" value={universityId} onChange={(e) => setUniversityId(e.target.value)}>
              <option value="">All universities</option>
              {(universitiesQuery.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          )}
        </CardContent>
      </Card>
      <div className="overflow-hidden rounded-md border bg-background">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left">
            <tr>
              <th className="p-3">Name</th>
              <th className="p-3">NIM</th>
              <th className="p-3">University</th>
              <th className="p-3">Study Program</th>
              <th className="p-3">Status</th>
              <th className="p-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            {(registrationsQuery.data?.data ?? []).map((item) => (
              <tr key={item.id} className="border-t">
                <td className="p-3">
                  <div className="font-medium">{item.name}</div>
                  <div className="text-xs text-muted-foreground">{item.email}</div>
                </td>
                <td className="p-3">{item.nim}</td>
                <td className="p-3">{item.university?.name ?? "-"}</td>
                <td className="p-3">{item.study_program?.name ?? "-"}</td>
                <td className="p-3"><Badge variant="outline">{item.status}</Badge></td>
                <td className="p-3 text-right">
                  <Button asChild size="sm" variant="outline">
                    <Link to="/dashboard/registrations/$id" params={{ id: String(item.id) }}>Review</Link>
                  </Button>
                </td>
              </tr>
            ))}
            {registrationsQuery.isSuccess && registrationsQuery.data.data.length === 0 && (
              <tr><td colSpan={6} className="p-8 text-center text-muted-foreground">No registrations found.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
