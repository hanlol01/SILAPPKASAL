import { useMutation, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Clock, Loader2, ShieldAlert, ShieldX, XCircle } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { formatDateTime } from "@/lib/format";
import { apiErrorMessage } from "@/lib/form-errors";
import {
  approveBreakGlass,
  denyBreakGlass,
  revokeBreakGlass,
} from "@/lib/break-glass-api";
import type { BreakGlassRequest } from "@/lib/break-glass-types";

interface BreakGlassPendingListProps {
  requests: BreakGlassRequest[];
  showActions?: boolean;
  emptyMessage?: string;
}

export function BreakGlassPendingList({
  requests,
  showActions = true,
  emptyMessage,
}: BreakGlassPendingListProps) {
  const queryClient = useQueryClient();
  const { t, i18n } = useTranslation(["dashboard", "common"]);
  const [denyTarget, setDenyTarget] = useState<BreakGlassRequest | null>(null);
  const [denialReason, setDenialReason] = useState("");
  const [revokeTarget, setRevokeTarget] = useState<BreakGlassRequest | null>(null);
  const [revocationReason, setRevocationReason] = useState("");
  const fallbackEmpty = emptyMessage ?? t("dashboard:breakGlass.pending.empty");

  const approveMutation = useMutation({
    mutationFn: (id: number) => approveBreakGlass(id),
    onSuccess: () => {
      toast.success(t("dashboard:breakGlass.success.approved"));
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, t("dashboard:breakGlass.errors.approvalFailed"))),
  });

  const denyMutation = useMutation({
    mutationFn: ({ id, denial_reason }: { id: number; denial_reason: string }) =>
      denyBreakGlass(id, { denial_reason }),
    onSuccess: () => {
      toast.success(t("dashboard:breakGlass.success.denied"));
      setDenyTarget(null);
      setDenialReason("");
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, t("dashboard:breakGlass.errors.denialFailed"))),
  });

  const revokeMutation = useMutation({
    mutationFn: ({ id, revocation_reason }: { id: number; revocation_reason: string }) =>
      revokeBreakGlass(id, { revocation_reason }),
    onSuccess: () => {
      toast.success(t("dashboard:breakGlass.success.revoked"));
      setRevokeTarget(null);
      setRevocationReason("");
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, t("dashboard:breakGlass.errors.revocationFailed"))),
  });

  function submitDenial() {
    if (!denyTarget) return;

    if (!denialReason.trim()) {
      toast.error(t("common:validation.required"));
      return;
    }

    denyMutation.mutate({
      id: denyTarget.id,
      denial_reason: denialReason.trim(),
    });
  }

  function submitRevocation() {
    if (!revokeTarget) return;

    if (!revocationReason.trim()) {
      toast.error(t("common:validation.required"));
      return;
    }

    revokeMutation.mutate({
      id: revokeTarget.id,
      revocation_reason: revocationReason.trim(),
    });
  }

  return (
    <div className="space-y-4">
      <div className="overflow-x-auto rounded-lg border">
        <Table className="min-w-[64rem]">
          <TableHeader>
            <TableRow>
              <TableHead>{t("dashboard:breakGlass.table.report")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.requestor")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.reason")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.status")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.requested")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.grant")}</TableHead>
              {showActions && <TableHead className="text-right">{t("dashboard:breakGlass.table.actions")}</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {requests.map((request) => (
              <TableRow key={request.id}>
                <TableCell>
                  <div className="font-mono text-xs">
                    {request.report?.registration_number ?? t("dashboard:breakGlass.table.unknownReport")}
                  </div>
                  <div className="text-xs text-muted-foreground">
                    {request.report?.report_type ?? t("dashboard:breakGlass.table.metadataUnavailable")}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="font-medium">{request.requestor?.name ?? t("dashboard:breakGlass.table.unknownRequestor")}</div>
                  <div className="text-xs text-muted-foreground">
                    {request.requestor?.role?.name ?? request.requestor?.role?.code ?? t("dashboard:common.notAvailable")}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    {t(`dashboard:breakGlass.reasonCategories.${request.reason_category}`, {
                      defaultValue: t("dashboard:breakGlass.reasonCategories.other"),
                    })}
                  </div>
                  <div className="mt-1 line-clamp-2 max-w-md text-xs text-muted-foreground">
                    {request.reason}
                  </div>
                </TableCell>
                <TableCell>
                  <BreakGlassStatusBadge status={request.status} t={t} />
                </TableCell>
                <TableCell className="text-muted-foreground">
                  {formatDateTime(request.requested_at, i18n.language)}
                </TableCell>
                <TableCell className="text-muted-foreground">
                  <div>{t(`dashboard:breakGlass.duration.${request.requested_duration_minutes}`, {
                    defaultValue: t("dashboard:breakGlass.duration.minutes", {
                      count: request.requested_duration_minutes,
                    }),
                  })}</div>
                  {request.expires_at && (
                    <div className="mt-1 text-xs">
                      {t("dashboard:breakGlass.table.expires", {
                        value: formatDateTime(request.expires_at, i18n.language),
                      })}
                    </div>
                  )}
                </TableCell>
                {showActions && (
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      {request.status === "pending" && (
                        <>
                          <Button
                            size="sm"
                            onClick={() => approveMutation.mutate(request.id)}
                            disabled={approveMutation.isPending || denyMutation.isPending}
                          >
                            {approveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {t("dashboard:breakGlass.actions.approve")}
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setDenyTarget(request)}
                            disabled={approveMutation.isPending || denyMutation.isPending}
                          >
                            {t("dashboard:breakGlass.actions.deny")}
                          </Button>
                        </>
                      )}
                      {request.can_revoke && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setRevokeTarget(request)}
                          disabled={revokeMutation.isPending}
                        >
                          <ShieldX className="mr-2 h-4 w-4" />
                          {t("dashboard:breakGlass.actions.revoke")}
                        </Button>
                      )}
                    </div>
                  </TableCell>
                )}
              </TableRow>
            ))}

            {requests.length === 0 && (
              <TableRow>
                <TableCell
                  colSpan={showActions ? 7 : 6}
                  className="py-10 text-center text-sm text-muted-foreground"
                >
                  {fallbackEmpty}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={Boolean(denyTarget)} onOpenChange={(open) => !open && setDenyTarget(null)}>
        <DialogTrigger asChild>
          <span hidden />
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("dashboard:breakGlass.deny.title")}</DialogTitle>
            <DialogDescription>
              {t("dashboard:breakGlass.deny.description")}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="denial-reason">{t("dashboard:breakGlass.deny.reasonLabel")}</Label>
            <Textarea
              id="denial-reason"
              value={denialReason}
              onChange={(event) => setDenialReason(event.target.value)}
              className="min-h-28"
              disabled={denyMutation.isPending}
            />
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDenyTarget(null)}
              disabled={denyMutation.isPending}
            >
              {t("common:cancel")}
            </Button>
            <Button onClick={submitDenial} disabled={denyMutation.isPending}>
              {denyMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {t("dashboard:breakGlass.deny.submit")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog
        open={Boolean(revokeTarget)}
        onOpenChange={(open) => {
          if (!open && !revokeMutation.isPending) {
            setRevokeTarget(null);
            setRevocationReason("");
          }
        }}
      >
        <DialogTrigger asChild>
          <span hidden />
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("dashboard:breakGlass.revoke.title")}</DialogTitle>
            <DialogDescription>
              {t("dashboard:breakGlass.revoke.description")}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="revocation-reason">
              {t("dashboard:breakGlass.revoke.reasonLabel")}
            </Label>
            <Textarea
              id="revocation-reason"
              value={revocationReason}
              onChange={(event) => setRevocationReason(event.target.value)}
              className="min-h-28"
              disabled={revokeMutation.isPending}
            />
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setRevokeTarget(null)}
              disabled={revokeMutation.isPending}
            >
              {t("common:cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={submitRevocation}
              disabled={revokeMutation.isPending}
            >
              {revokeMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {t("dashboard:breakGlass.revoke.submit")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function invalidateBreakGlass(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["break-glass"] });
}

function errorMessage(error: unknown, fallback: string) {
  return apiErrorMessage(error, fallback);
}

function translateStatus(t: TFunction, value: string) {
  return t(`dashboard:breakGlass.status.${value}`, {
    defaultValue: prettifyToken(value),
  });
}

function BreakGlassStatusBadge({ status, t }: { status: string; t: TFunction }) {
  const visual =
    status === "approved" || status === "viewed"
      ? { Icon: CheckCircle2, className: "bg-success/15 text-success border-success/30" }
      : status === "denied" || status === "expired" || status === "revoked"
        ? { Icon: XCircle, className: "bg-destructive/15 text-destructive border-destructive/30" }
        : status === "pending"
          ? { Icon: Clock, className: "bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning" }
          : { Icon: ShieldAlert, className: "bg-muted text-muted-foreground border-border" };
  const { Icon } = visual;

  return (
    <Badge variant="outline" className={`gap-1 font-medium ${visual.className}`}>
      <Icon className="h-3 w-3" aria-hidden="true" />
      {translateStatus(t, status)}
    </Badge>
  );
}

function prettifyToken(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
