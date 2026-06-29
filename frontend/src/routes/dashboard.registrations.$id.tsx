import { createFileRoute, Link, Navigate, useNavigate } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import {
  approveReporterRegistration,
  getReporterRegistration,
  registrationQueryKeys,
  rejectReporterRegistration,
} from "@/lib/registration-api";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { formatRegistrationStatus } from "@/lib/format-labels";

export const Route = createFileRoute("/dashboard/registrations/$id")({
  component: RegistrationDetailPage,
});

function RegistrationDetailPage() {
  const { t } = useTranslation(["dashboard"]);
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
      toast.success(t("dashboard:registrations.approveSuccess"));
      queryClient.invalidateQueries({ queryKey: ["reporter-registrations"] });
      navigate({ to: "/dashboard/registrations" });
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:registrations.approveError")),
  });

  const rejectMutation = useMutation({
    mutationFn: () => rejectReporterRegistration(id, reason),
    onSuccess: () => {
      toast.success(t("dashboard:registrations.rejectSuccess"));
      queryClient.invalidateQueries({ queryKey: ["reporter-registrations"] });
      navigate({ to: "/dashboard/registrations" });
    },
    onError: (error) => toast.error(error instanceof ApiError ? error.message : t("dashboard:registrations.rejectError")),
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;

  const item = detailQuery.data;

  return (
    <div className="space-y-6">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/dashboard">{t("dashboard:nav.overview")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/dashboard/registrations">{t("dashboard:registrations.title")}</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>{item?.registration_number ?? t("dashboard:registrations.detailTitle")}</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>
      <Button asChild variant="ghost" size="sm"><Link to="/dashboard/registrations">{t("dashboard:common.back")}</Link></Button>
      <Card>
        <CardHeader>
          <CardTitle>{t("dashboard:registrations.detailTitle")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-5">
          {detailQuery.isLoading && (
            <div className="grid gap-3 md:grid-cols-2">
              {Array.from({ length: 10 }).map((_, i) => (
                <div key={i} className="rounded-md border bg-muted/20 p-3 space-y-2">
                  <Skeleton className="h-3 w-20" />
                  <Skeleton className="h-4 w-40" />
                </div>
              ))}
            </div>
          )}
          {item && (
            <>
              <div className="grid gap-3 md:grid-cols-2">
                <Info label={t("dashboard:registrations.registrationNumber")} value={item.registration_number} />
                <Info label={t("dashboard:common.status")} value={<Badge variant="outline">{formatRegistrationStatus(t, item.status)}</Badge>} />
                <Info label={t("dashboard:registrations.name")} value={item.name} />
                <Info label={t("dashboard:registrations.email")} value={item.email} />
                <Info label={t("dashboard:registrations.nim")} value={item.nim} />
                <Info label={t("dashboard:registrations.phone")} value={item.phone_number} />
                <Info label={t("dashboard:registrations.university")} value={item.university?.name ?? "-"} />
                <Info label={t("dashboard:registrations.faculty")} value={item.faculty?.name ?? "-"} />
                <Info label={t("dashboard:registrations.studyProgram")} value={item.study_program?.name ?? "-"} />
                <Info label={t("dashboard:registrations.rejectReason")} value={item.rejection_reason ?? "-"} />
              </div>
              {item.status === "pending" && (
                <div className="space-y-3 rounded-md border p-4">
                  <div className="flex flex-wrap gap-2">
                    <Button onClick={() => approveMutation.mutate()} disabled={approveMutation.isPending}>
                      {t("dashboard:registrations.approve")}
                    </Button>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <Button
                          variant="destructive"
                          disabled={rejectMutation.isPending || reason.trim().length < 10}
                        >
                          {t("dashboard:registrations.reject")}
                        </Button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>{t("dashboard:registrations.rejectConfirmTitle", { name: item.name })}</AlertDialogTitle>
                          <AlertDialogDescription>
                            {t("dashboard:registrations.rejectConfirmDescription")}
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <div className="rounded-md border bg-muted/30 p-3">
                          <div className="text-xs font-medium text-muted-foreground">
                            {t("dashboard:registrations.rejectReason")}
                          </div>
                          <p className="mt-2 whitespace-pre-wrap text-sm">{reason.trim()}</p>
                        </div>
                        <AlertDialogFooter>
                          <AlertDialogCancel>{t("dashboard:common.cancel")}</AlertDialogCancel>
                          <AlertDialogAction
                            variant="destructive"
                            disabled={rejectMutation.isPending}
                            onClick={() => rejectMutation.mutate()}
                          >
                            {t("dashboard:registrations.reject")}
                          </AlertDialogAction>
                        </AlertDialogFooter>
                      </AlertDialogContent>
                    </AlertDialog>
                  </div>
                  <Textarea
                    placeholder={t("dashboard:registrations.rejectionReasonPlaceholder")}
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
