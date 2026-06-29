import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Eye, Loader2 } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import type { TFunction } from "i18next";
import { toast } from "sonner";
import { BreakGlassRevealView } from "@/components/admin/break-glass-reveal-view";
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
import { ApiError } from "@/lib/api-client";
import { formatDateTime } from "@/lib/format";
import {
  approveBreakGlass,
  denyBreakGlass,
  revealIdentity,
} from "@/lib/break-glass-api";
import type { BreakGlassRequest, BreakGlassReveal } from "@/lib/break-glass-types";

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
  const [reveal, setReveal] = useState<BreakGlassReveal | null>(null);
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

  const revealMutation = useMutation({
    mutationFn: (id: number) => revealIdentity(id),
    onSuccess: (data) => {
      setReveal(data);
      invalidateBreakGlass(queryClient);
    },
    onError: (error) => toast.error(errorMessage(error, t("dashboard:breakGlass.errors.revealFailed"))),
  });

  function submitDenial() {
    if (!denyTarget) return;

    if (denialReason.trim().length < 10) {
      toast.error(t("dashboard:breakGlass.errors.denialReasonMin"));
      return;
    }

    denyMutation.mutate({
      id: denyTarget.id,
      denial_reason: denialReason.trim(),
    });
  }

  return (
    <div className="space-y-4">
      {reveal && <BreakGlassRevealView reveal={reveal} />}

      <div className="overflow-hidden rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t("dashboard:breakGlass.table.report")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.requestor")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.reason")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.status")}</TableHead>
              <TableHead>{t("dashboard:breakGlass.table.requested")}</TableHead>
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
                  <div className="text-sm">{prettifyToken(request.reason_category)}</div>
                  <div className="mt-1 line-clamp-2 max-w-md text-xs text-muted-foreground">
                    {request.reason}
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline">{translateStatus(t, request.status)}</Badge>
                </TableCell>
                <TableCell className="text-muted-foreground">
                  {formatDateTime(request.requested_at, i18n.language)}
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
                      {request.is_viewable && request.status !== "pending" && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => revealMutation.mutate(request.id)}
                          disabled={revealMutation.isPending}
                        >
                          {revealMutation.isPending ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          ) : (
                            <Eye className="mr-2 h-4 w-4" />
                          )}
                          {t("dashboard:breakGlass.actions.reveal")}
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
                  colSpan={showActions ? 6 : 5}
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
    </div>
  );
}

function invalidateBreakGlass(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: ["break-glass"] });
}

function errorMessage(error: unknown, fallback: string) {
  return error instanceof ApiError ? error.message : fallback;
}

function translateStatus(t: TFunction, value: string) {
  return t(`dashboard:breakGlass.status.${value}`, {
    defaultValue: prettifyToken(value),
  });
}

function prettifyToken(value: string) {
  return value.replace(/[-_]/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
