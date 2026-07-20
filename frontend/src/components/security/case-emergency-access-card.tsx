import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Eye, Loader2, LockKeyhole, ShieldAlert } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { BreakGlassRequestDialog } from "@/components/admin/break-glass-request-dialog";
import { QueryErrorState } from "@/components/query-state";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import {
  breakGlassQueryKeys,
  getOwnBreakGlassRequests,
  revealIdentity,
} from "@/lib/break-glass-api";
import type { BreakGlassRequest, BreakGlassReveal } from "@/lib/break-glass-types";
import { formatDateTime } from "@/lib/format";
import { apiErrorMessage } from "@/lib/form-errors";

interface CaseEmergencyAccessCardProps {
  caseId: number;
  registrationNumber: string;
}

export function CaseEmergencyAccessCard({
  caseId,
  registrationNumber,
}: CaseEmergencyAccessCardProps) {
  const { t, i18n } = useTranslation(["dashboard", "common"]);
  const queryClient = useQueryClient();
  const [revealTarget, setRevealTarget] = useState<BreakGlassRequest | null>(null);
  const [identity, setIdentity] = useState<BreakGlassReveal | null>(null);
  const [isRevealing, setIsRevealing] = useState(false);

  const requestsQuery = useQuery({
    queryKey: breakGlassQueryKeys.mine(caseId),
    queryFn: () => getOwnBreakGlassRequests(caseId),
    retry: false,
  });

  const requests = requestsQuery.data?.data ?? [];
  const latestRequest = requests[0] ?? null;
  const hasOpenRequest = requests.some((item) =>
    item.status === "pending" || item.can_reveal,
  );

  function closeReveal() {
    setRevealTarget(null);
    setIdentity(null);
  }

  async function reveal() {
    if (!revealTarget || isRevealing) return;

    setIsRevealing(true);
    try {
      const result = await revealIdentity(revealTarget.id);
      setIdentity(result);
      await queryClient.invalidateQueries({ queryKey: ["break-glass"] });
      toast.success(t("dashboard:breakGlass.reveal.success"));
    } catch (error) {
      toast.error(apiErrorMessage(error, t("dashboard:breakGlass.errors.revealFailed")));
    } finally {
      setIsRevealing(false);
    }
  }

  return (
    <Card className="min-w-0 border-amber-500/30">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <LockKeyhole className="h-4 w-4 text-amber-600" />
          {t("dashboard:breakGlass.caseCard.title")}
        </CardTitle>
        <CardDescription>{t("dashboard:breakGlass.caseCard.description")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <Alert>
          <ShieldAlert className="h-4 w-4" />
          <AlertDescription>{t("dashboard:breakGlass.caseCard.privacyNotice")}</AlertDescription>
        </Alert>

        {requestsQuery.isLoading && (
          <div className="space-y-2" aria-busy="true">
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-16 w-full" />
          </div>
        )}
        {requestsQuery.isError && (
          <QueryErrorState
            message={t("dashboard:breakGlass.errors.ownLoad")}
            onRetry={() => requestsQuery.refetch()}
          />
        )}

        {latestRequest && (
          <div className="min-w-0 rounded-lg border p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span className="font-medium">{t("dashboard:breakGlass.caseCard.latestRequest")}</span>
              <StatusBadge status={latestRequest.status} />
            </div>
            <dl className="mt-3 grid min-w-0 gap-2 text-xs sm:grid-cols-2">
              <Metadata
                label={t("dashboard:breakGlass.table.requested")}
                value={formatDateTime(latestRequest.requested_at, i18n.language)}
              />
              <Metadata
                label={t("dashboard:breakGlass.request.duration")}
                value={t(`dashboard:breakGlass.duration.${latestRequest.requested_duration_minutes}`, {
                  defaultValue: t("dashboard:breakGlass.duration.minutes", {
                    count: latestRequest.requested_duration_minutes,
                  }),
                })}
              />
              {latestRequest.expires_at && (
                <Metadata
                  label={t("dashboard:breakGlass.caseCard.expiresAt")}
                  value={formatDateTime(latestRequest.expires_at, i18n.language)}
                />
              )}
              <Metadata
                label={t("dashboard:breakGlass.caseCard.viewCount")}
                value={String(latestRequest.view_count)}
              />
            </dl>
            {latestRequest.denial_reason && (
              <p className="mt-3 break-words text-xs text-destructive [overflow-wrap:anywhere]">
                {t("dashboard:breakGlass.caseCard.denialReason", {
                  reason: latestRequest.denial_reason,
                })}
              </p>
            )}
            {latestRequest.revocation_reason && (
              <p className="mt-3 break-words text-xs text-destructive [overflow-wrap:anywhere]">
                {t("dashboard:breakGlass.caseCard.revocationReason", {
                  reason: latestRequest.revocation_reason,
                })}
              </p>
            )}
          </div>
        )}

        {!requestsQuery.isLoading && !requestsQuery.isError && !hasOpenRequest && (
          <BreakGlassRequestDialog
            caseId={caseId}
            registrationNumber={registrationNumber}
          />
        )}

        {latestRequest?.status === "pending" && (
          <p className="text-sm text-muted-foreground">
            {t("dashboard:breakGlass.caseCard.pendingHint")}
          </p>
        )}

        {latestRequest?.can_reveal && (
          <Button
            className="w-full"
            onClick={() => {
              setIdentity(null);
              setRevealTarget(latestRequest);
            }}
          >
            <Eye className="mr-2 h-4 w-4" />
            {t("dashboard:breakGlass.actions.reveal")}
          </Button>
        )}
      </CardContent>

      <Dialog
        open={Boolean(revealTarget)}
        onOpenChange={(open) => {
          if (!open && !isRevealing) closeReveal();
        }}
      >
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{t("dashboard:breakGlass.reveal.title")}</DialogTitle>
            <DialogDescription>{t("dashboard:breakGlass.reveal.protectedDescription")}</DialogDescription>
          </DialogHeader>

          <Alert className="border-amber-500/30 bg-amber-500/5">
            <ShieldAlert className="h-4 w-4" />
            <AlertDescription>{t("dashboard:breakGlass.reveal.auditNotice")}</AlertDescription>
          </Alert>

          {!identity ? (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">
                {t("dashboard:breakGlass.reveal.confirmation")}
              </p>
              <Button className="w-full" onClick={reveal} disabled={isRevealing}>
                {isRevealing ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                  <Eye className="mr-2 h-4 w-4" />
                )}
                {t("dashboard:breakGlass.reveal.confirm")}
              </Button>
            </div>
          ) : (
            <div className="grid min-w-0 gap-3 text-sm sm:grid-cols-2">
              <IdentityField label={t("dashboard:breakGlass.reveal.name")} value={identity.name} />
              <IdentityField label={t("dashboard:breakGlass.reveal.nim")} value={identity.nim} />
              <IdentityField label={t("dashboard:breakGlass.reveal.email")} value={identity.email} />
              <IdentityField label={t("dashboard:breakGlass.reveal.phone")} value={identity.phone_number} />
              <IdentityField label={t("dashboard:breakGlass.reveal.faculty")} value={referenceLabel(identity.faculty)} />
              <IdentityField label={t("dashboard:breakGlass.reveal.studyProgram")} value={referenceLabel(identity.study_program)} />
              <IdentityField label={t("dashboard:breakGlass.reveal.university")} value={referenceLabel(identity.university)} />
            </div>
          )}

          <DialogFooter>
            <Button variant="outline" onClick={closeReveal} disabled={isRevealing}>
              {t("dashboard:breakGlass.reveal.close")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}

function StatusBadge({ status }: { status: string }) {
  const { t } = useTranslation(["dashboard"]);
  return (
    <Badge variant="outline">
      {t(`dashboard:breakGlass.status.${status}`, { defaultValue: status })}
    </Badge>
  );
}

function Metadata({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="mt-0.5 break-words font-medium [overflow-wrap:anywhere]">{value}</dd>
    </div>
  );
}

function IdentityField({ label, value }: { label: string; value: string | null }) {
  const { t } = useTranslation(["dashboard"]);
  return (
    <div className="min-w-0 rounded-lg border p-3">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 break-words font-medium [overflow-wrap:anywhere]">
        {value || t("dashboard:common.notAvailable")}
      </p>
    </div>
  );
}

function referenceLabel(reference: { code: string | null; name: string | null } | null) {
  if (!reference) return null;
  return reference.name ?? reference.code;
}
