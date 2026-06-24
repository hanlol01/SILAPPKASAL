import { createFileRoute, Link, Navigate, useNavigate } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";

import {
  approveReporterRegistration,
  getReporterRegistration,
  registrationQueryKeys,
  rejectReporterRegistration,
} from "@/lib/registration-api";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";

export const Route = createFileRoute("/dashboard/registrations/$id")({
  component: RegistrationDetailPage,
});

function RegistrationDetailPage() {
  const { id } = Route.useParams();
  const { roleCode } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [reason, setReason] = useState("");
  const canAccess = roleCode === "admin" || roleCode === "super_admin";

  const detailQuery = useQuery({
    queryKey: registrationQueryKeys.detail(id),
    queryFn: () => getReporterRegistration(id),
    enabled: canAccess,
  });

  const approveMutation = useMutation({
    mutationFn: () => approveReporterRegistration(id),
    onSuccess: () => {
      toast.success("Registration approved.");
      queryClient.invalidateQueries({ queryKey: ["reporter-registrations"] });
      navigate({ to: "/dashboard/registrations" });
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Approve failed"),
  });

  const rejectMutation = useMutation({
    mutationFn: () => rejectReporterRegistration(id, reason),
    onSuccess: () => {
      toast.success("Registration rejected.");
      queryClient.invalidateQueries({ queryKey: ["reporter-registrations"] });
      navigate({ to: "/dashboard/registrations" });
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Reject failed"),
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;

  const item = detailQuery.data;

  return (
    <div className="space-y-6">
      <Button asChild variant="ghost" size="sm"><Link to="/dashboard/registrations">Back</Link></Button>
      <Card>
        <CardHeader>
          <CardTitle>Registration Detail</CardTitle>
        </CardHeader>
        <CardContent className="space-y-5">
          {detailQuery.isLoading && <p className="text-sm text-muted-foreground">Loading...</p>}
          {item && (
            <>
              <div className="grid gap-3 md:grid-cols-2">
                <Info label="Registration Number" value={item.registration_number} />
                <Info label="Status" value={<Badge variant="outline">{item.status}</Badge>} />
                <Info label="Name" value={item.name} />
                <Info label="Email" value={item.email} />
                <Info label="NIM" value={item.nim} />
                <Info label="Phone" value={item.phone_number} />
                <Info label="University" value={item.university?.name ?? "-"} />
                <Info label="Faculty" value={item.faculty?.name ?? "-"} />
                <Info label="Study Program" value={item.study_program?.name ?? "-"} />
                <Info label="Rejection Reason" value={item.rejection_reason ?? "-"} />
              </div>
              {item.status === "pending" && (
                <div className="space-y-3 rounded-md border p-4">
                  <div className="flex flex-wrap gap-2">
                    <Button onClick={() => approveMutation.mutate()} disabled={approveMutation.isPending}>
                      Approve
                    </Button>
                    <Button
                      variant="destructive"
                      onClick={() => rejectMutation.mutate()}
                      disabled={rejectMutation.isPending || reason.trim().length < 10}
                    >
                      Reject
                    </Button>
                  </div>
                  <Textarea
                    placeholder="Rejection reason, minimum 10 characters"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                  />
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function Info({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="rounded-md border bg-muted/20 p-3">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className="mt-1 text-sm font-medium">{value}</div>
    </div>
  );
}
