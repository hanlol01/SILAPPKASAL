import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { ArrowLeft, CheckCircle2, FileSearch, Loader2, ShieldAlert, XCircle } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { AccessDenied } from "@/components/access-denied";
import { QueryErrorState } from "@/components/query-state";
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
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import { formatDateTime } from "@/lib/format";
import {
  approveReportWithdrawal,
  getReportWithdrawalReview,
  operationsQueryKeys,
  previewReportWithdrawalDocument,
  rejectReportWithdrawal,
} from "@/lib/operations-api";
import type { ReportWithdrawalStatus } from "@/lib/operations-types";

export const Route = createFileRoute("/dashboard/report-withdrawals/$publicId")({
  component: ReportWithdrawalDetailPage,
  head: () => ({ meta: [{ title: "Withdrawal review detail - SILAPPKASAL" }] }),
});

function ReportWithdrawalDetailPage() {
  const { publicId } = Route.useParams();
  const { roleCode, user } = useAuth();
  const { t, i18n } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectionReason, setRejectionReason] = useState("");
  const [resubmissionAllowed, setResubmissionAllowed] = useState(false);
  const [preview, setPreview] = useState<{ url: string; mime: string } | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const previewRequestRef = useRef<AbortController | null>(null);
  const canAccess =
    (roleCode === "admin" && user?.permissions?.includes("reports.withdraw.review.own_campus")) ||
    (roleCode === "super_admin" && user?.permissions?.includes("reports.read.all"));
  const reviewQuery = useQuery({
    queryKey: operationsQueryKeys.withdrawalReview(publicId),
    queryFn: () => getReportWithdrawalReview(publicId),
    enabled: Boolean(canAccess),
  });

  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview.url);
  }, [preview]);
  useEffect(() => () => previewRequestRef.current?.abort(), []);

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.withdrawalReview(publicId) }),
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.withdrawalReviewsRoot() }),
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.reportsRoot() }),
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.casesRoot() }),
      queryClient.invalidateQueries({ queryKey: ["dashboard"] }),
    ]);
  };
  const approveMutation = useMutation({
    mutationFn: () => {
      const lockVersion = reviewQuery.data?.lock_version;
      if (lockVersion === undefined) throw new Error(t("dashboard:withdrawals.decisionError"));
      return approveReportWithdrawal(publicId, lockVersion);
    },
    onSuccess: async () => {
      toast.success(t("dashboard:withdrawals.approveSuccess"));
      await refresh();
    },
    onError: async (error) => {
      toast.error(reviewError(error, t("dashboard:withdrawals.decisionError"), t("dashboard:withdrawals.staleError")));
      await refresh();
    },
  });
  const rejectMutation = useMutation({
    mutationFn: () => {
      const lockVersion = reviewQuery.data?.lock_version;
      if (lockVersion === undefined) throw new Error(t("dashboard:withdrawals.decisionError"));
      return rejectReportWithdrawal(publicId, {
        lock_version: lockVersion,
        rejection_reason: rejectionReason.trim(),
        resubmission_allowed: resubmissionAllowed,
      });
    },
    onSuccess: async () => {
      setRejectOpen(false);
      setRejectionReason("");
      setResubmissionAllowed(false);
      toast.success(t("dashboard:withdrawals.rejectSuccess"));
      await refresh();
    },
    onError: async (error) => {
      toast.error(reviewError(error, t("dashboard:withdrawals.decisionError"), t("dashboard:withdrawals.staleError")));
      await refresh();
    },
  });

  if (!canAccess) return <AccessDenied />;
  if (reviewQuery.isError || (!reviewQuery.isLoading && !reviewQuery.data)) {
    return <QueryErrorState message={t("dashboard:withdrawals.loadError")} onRetry={() => reviewQuery.refetch()} />;
  }
  if (!reviewQuery.data) {
    return <div className="flex items-center gap-2 py-16 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" />{t("dashboard:common.loading")}</div>;
  }

  const item = reviewQuery.data;
  const latestAttachment = item.attachments?.at(-1);
  const reasonValid = rejectionReason.trim().length >= 20 && rejectionReason.trim().length <= 2000;
  const busy = approveMutation.isPending || rejectMutation.isPending;

  const openPreview = async () => {
    if (!latestAttachment) return;
    previewRequestRef.current?.abort();
    if (preview) {
      URL.revokeObjectURL(preview.url);
      setPreview(null);
    }
    const controller = new AbortController();
    previewRequestRef.current = controller;
    setPreviewLoading(true);
    try {
      const response = await previewReportWithdrawalDocument(
        publicId,
        latestAttachment.attachment_reference,
        controller.signal,
      );
      const url = URL.createObjectURL(response.blob);
      if (controller.signal.aborted) {
        URL.revokeObjectURL(url);
        return;
      }
      setPreview({ url, mime: response.contentType });
    } catch (error) {
      if (controller.signal.aborted) return;
      toast.error(reviewError(error, t("dashboard:withdrawals.documentError"), t("dashboard:withdrawals.staleError")));
    } finally {
      if (previewRequestRef.current === controller) {
        previewRequestRef.current = null;
        setPreviewLoading(false);
      }
    }
  };

  return (
    <div className="space-y-6">
      <Button variant="ghost" asChild><Link to="/dashboard/report-withdrawals"><ArrowLeft className="h-4 w-4" />{t("dashboard:withdrawals.backToQueue")}</Link></Button>
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="font-mono text-xl font-semibold">{item.registration_number}</h1>
        <WithdrawalBadge status={item.status} />
      </div>

      {roleCode === "super_admin" && (
        <div className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm">
          <div className="flex items-center gap-2 font-medium"><ShieldAlert className="h-4 w-4" />{t("dashboard:withdrawals.monitoringOnly")}</div>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t("dashboard:withdrawals.requestDetail")}</CardTitle></CardHeader>
          <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
            <Field label={t("dashboard:common.status")}><WithdrawalBadge status={item.status} /></Field>
            <Field label={t("dashboard:withdrawals.campus")}>{item.campus?.name ?? t("dashboard:common.metadataUnavailable")}</Field>
            <Field label={t("dashboard:common.submitted")}>{formatDateTime(item.submitted_at, i18n.language)}</Field>
            <Field label={t("dashboard:withdrawals.reviewedAt")}>{formatDateTime(item.reviewed_at, i18n.language)}</Field>
            {roleCode === "admin" && (
              <>
                <Field label={t("dashboard:withdrawals.reportStatus")}>{item.report_status}</Field>
                <Field label={t("dashboard:withdrawals.caseStatus")}>{item.case_status ?? t("dashboard:common.notAvailable")}</Field>
              </>
            )}
            {item.reason !== undefined && <div className="sm:col-span-2"><Field label={t("dashboard:withdrawals.reporterReason")}><p className="whitespace-pre-wrap break-words">{item.reason}</p></Field></div>}
            {item.rejection_reason && <div className="sm:col-span-2"><Field label={t("dashboard:withdrawals.rejectionReason")}><p className="whitespace-pre-wrap break-words">{item.rejection_reason}</p></Field></div>}
          </CardContent>
        </Card>

        {roleCode === "admin" && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("dashboard:withdrawals.signedDocument")}</CardTitle>
              <CardDescription>{t("dashboard:withdrawals.privateDocument")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {latestAttachment ? (
                <>
                  <p className="text-sm">{t("dashboard:withdrawals.documentVersion", { version: latestAttachment.version })}</p>
                  <Button variant="outline" onClick={() => void openPreview()} disabled={!item.capabilities.can_view_signed_document || previewLoading}>
                    {previewLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileSearch className="h-4 w-4" />}
                    {t("dashboard:withdrawals.previewDocument")}
                  </Button>
                  {preview && (
                    <div className="aspect-[4/3] overflow-hidden rounded-lg border bg-muted">
                      {preview.mime === "application/pdf" ? (
                        <iframe
                          src={preview.url}
                          title={t("dashboard:withdrawals.signedDocument")}
                          sandbox=""
                          className="h-full w-full"
                        />
                      ) : (
                        <img src={preview.url} alt={t("dashboard:withdrawals.signedDocument")} className="h-full w-full object-contain" />
                      )}
                    </div>
                  )}
                </>
              ) : <p className="text-sm text-muted-foreground">{t("dashboard:withdrawals.noDocument")}</p>}
            </CardContent>
          </Card>
        )}
      </div>

      {roleCode === "admin" && item.capabilities.can_review && (
        <Card>
          <CardHeader><CardTitle className="text-base">{t("dashboard:withdrawals.reviewAction")}</CardTitle><CardDescription>{t("dashboard:withdrawals.reviewActionDescription")}</CardDescription></CardHeader>
          <CardContent className="flex flex-wrap gap-3">
            <AlertDialog>
              <AlertDialogTrigger asChild><Button disabled={!item.capabilities.can_approve || busy}><CheckCircle2 className="h-4 w-4" />{t("dashboard:withdrawals.approve")}</Button></AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader><AlertDialogTitle>{t("dashboard:withdrawals.approveTitle")}</AlertDialogTitle><AlertDialogDescription>{t("dashboard:withdrawals.approveDescription")}</AlertDialogDescription></AlertDialogHeader>
                <AlertDialogFooter><AlertDialogCancel disabled={busy}>{t("dashboard:common.cancel")}</AlertDialogCancel><AlertDialogAction disabled={busy} onClick={() => approveMutation.mutate()}>{approveMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}{t("dashboard:withdrawals.confirmApprove")}</AlertDialogAction></AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
            <Button variant="destructive" onClick={() => setRejectOpen(true)} disabled={!item.capabilities.can_reject || busy}><XCircle className="h-4 w-4" />{t("dashboard:withdrawals.reject")}</Button>
          </CardContent>
        </Card>
      )}

      <Dialog open={rejectOpen} onOpenChange={(open) => !busy && setRejectOpen(open)}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t("dashboard:withdrawals.rejectTitle")}</DialogTitle><DialogDescription>{t("dashboard:withdrawals.rejectDescription")}</DialogDescription></DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="withdrawal-rejection-reason">{t("dashboard:withdrawals.rejectionReason")}</Label>
              <Textarea id="withdrawal-rejection-reason" rows={7} value={rejectionReason} onChange={(event) => setRejectionReason(event.target.value)} maxLength={2000} disabled={busy} />
              <p className="text-xs text-muted-foreground">{rejectionReason.trim().length}/2.000 · {t("dashboard:withdrawals.reasonMinimum")}</p>
            </div>
            <label className="flex items-start gap-3 text-sm">
              <Checkbox checked={resubmissionAllowed} onCheckedChange={(checked) => setResubmissionAllowed(checked === true)} disabled={busy} aria-describedby="resubmit-help" />
              <span id="resubmit-help">{t("dashboard:withdrawals.allowResubmit")}</span>
            </label>
          </div>
          <DialogFooter><Button variant="outline" onClick={() => setRejectOpen(false)} disabled={busy}>{t("dashboard:common.cancel")}</Button><Button variant="destructive" onClick={() => rejectMutation.mutate()} disabled={!reasonValid || busy}>{rejectMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}{t("dashboard:withdrawals.confirmReject")}</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <div><div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div><div className="mt-1">{children}</div></div>;
}

function WithdrawalBadge({ status }: { status: ReportWithdrawalStatus }) {
  const { t } = useTranslation(["dashboard"]);
  return <Badge variant={status === "approved" ? "default" : status === "rejected" ? "destructive" : "outline"}>{t(`dashboard:withdrawals.status.${status}`)}</Badge>;
}

function reviewError(error: unknown, fallback: string, stale: string) {
  return error instanceof ApiError && error.status === 409 ? stale : error instanceof Error ? error.message : fallback;
}
