import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, UserRoundSearch } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { apiErrorMessage, laravelFieldErrors } from "@/lib/form-errors";
import {
  assignCaseSatgas,
  forwardReportToCase,
  lookupUsers,
  operationsQueryKeys,
} from "@/lib/operations-api";
import type { SatgasAssignmentPayload, UserLookupItem } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

type SatgasAssignmentActionProps = {
  mode: "forward-report" | "assign-case";
  targetId: string | number;
  currentSatgasIds?: number[];
  currentLeadSatgasId?: number | null;
  reportId?: string | number;
};

export function SatgasAssignmentAction({
  mode,
  targetId,
  currentSatgasIds = [],
  currentLeadSatgasId = null,
  reportId,
}: SatgasAssignmentActionProps) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>(currentSatgasIds);
  const [leadId, setLeadId] = useState<number | null>(currentLeadSatgasId);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const queryClient = useQueryClient();

  const usersQuery = useQuery({
    queryKey: operationsQueryKeys.userLookup("satgas_ppks"),
    queryFn: () => lookupUsers("satgas_ppks"),
    enabled: open,
    retry: false,
  });

  const satgasUsers = useMemo(() => usersQuery.data ?? [], [usersQuery.data]);
  const selectedUsers = useMemo(
    () => satgasUsers.filter((user) => selectedIds.includes(user.id)),
    [satgasUsers, selectedIds],
  );
  const currentSatgasKey = currentSatgasIds.join(",");

  useEffect(() => {
    if (!open) return;

    setSelectedIds(currentSatgasKey === "" ? [] : currentSatgasKey.split(",").map(Number));
    setLeadId(currentLeadSatgasId);
    setFieldErrors({});
  }, [currentLeadSatgasId, currentSatgasKey, open]);

  const mutation = useMutation<unknown, Error, SatgasAssignmentPayload>({
    mutationFn: (payload: SatgasAssignmentPayload) =>
      mode === "forward-report"
        ? forwardReportToCase(targetId, payload)
        : assignCaseSatgas(targetId, payload),
    onSuccess: async (result) => {
      const caseId = mode === "forward-report"
        ? forwardedCaseId(result)
        : targetId;

      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        reportId: reportId ?? (mode === "forward-report" ? targetId : undefined),
        includeReports: true,
      });

      setOpen(false);
      setFieldErrors({});
      toast.success(
        mode === "forward-report"
          ? t("dashboard:workflow.assignment.forwardSuccess")
          : t("dashboard:workflow.assignment.assignSuccess"),
      );
    },
    onError: (error) => {
      const errors = laravelErrors(error);
      setFieldErrors(errors);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.assignment.actionError")));
    },
  });

  function toggleSatgas(user: UserLookupItem, checked: boolean) {
    setFieldErrors({});
    setSelectedIds((current) => {
      const next = checked
        ? [...current, user.id]
        : current.filter((id) => id !== user.id);

      if (!next.includes(leadId ?? -1)) {
        setLeadId(null);
      }

      return next;
    });
  }

  function submit() {
    if (mutation.isPending) return;

    const errors = validateSelection(selectedIds, leadId, t);
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0 || leadId === null) return;

    mutation.mutate({
      satgas_ids: selectedIds,
      lead_satgas_id: leadId,
    });
  }

  const copy = actionCopy(mode, currentSatgasIds.length > 0, t);

  return (
    <Dialog open={open} onOpenChange={(nextOpen) => {
      if (!mutation.isPending) setOpen(nextOpen);
    }}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline">
          <UserRoundSearch className="mr-2 h-4 w-4" /> {copy.trigger}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{copy.title}</DialogTitle>
          <DialogDescription>{copy.description}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {usersQuery.isLoading && (
            <div className="flex items-center gap-2 rounded-lg border p-4 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" /> {t("dashboard:workflow.assignment.loadingSatgas")}
            </div>
          )}

          {usersQuery.isError && (
            <div className="rounded-lg border border-destructive/40 p-4 text-sm text-destructive">
              {t("dashboard:workflow.assignment.lookupError")}
            </div>
          )}

          {usersQuery.isSuccess && satgasUsers.length === 0 && (
            <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
              {t("dashboard:workflow.assignment.noActiveSatgas")}
            </div>
          )}

          {satgasUsers.length > 0 && (
            <>
              <div className="space-y-2">
                <Label>{t("dashboard:workflow.assignment.satgasMembers")}</Label>
                <div className="max-h-60 space-y-2 overflow-y-auto rounded-lg border p-3">
                  {satgasUsers.map((user) => (
                    <label key={user.id} className="flex items-center gap-3 rounded-md p-2 text-sm hover:bg-muted">
                      <Checkbox
                        checked={selectedIds.includes(user.id)}
                        onCheckedChange={(checked) => toggleSatgas(user, checked === true)}
                        disabled={mutation.isPending}
                      />
                      <span className="font-medium">{user.name}</span>
                    </label>
                  ))}
                </div>
                {fieldErrors.satgas_ids && (
                  <p className="text-xs text-destructive">{fieldErrors.satgas_ids}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label>{t("dashboard:workflow.assignment.leadSatgas")}</Label>
                <Select
                  value={leadId === null ? "" : String(leadId)}
                  onValueChange={(value) => {
                    setFieldErrors({});
                    setLeadId(Number(value));
                  }}
                  disabled={selectedUsers.length === 0 || mutation.isPending}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t("dashboard:workflow.assignment.leadPlaceholder")} />
                  </SelectTrigger>
                  <SelectContent>
                    {selectedUsers.map((user) => (
                      <SelectItem key={user.id} value={String(user.id)}>
                        {user.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {fieldErrors.lead_satgas_id && (
                  <p className="text-xs text-destructive">{fieldErrors.lead_satgas_id}</p>
                )}
              </div>
            </>
          )}
        </div>

        <DialogFooter>
          <Button
            type="button"
            onClick={submit}
            disabled={mutation.isPending || usersQuery.isLoading || satgasUsers.length === 0}
          >
            {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {mutation.isPending ? t("dashboard:common.saving") : copy.submit}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function actionCopy(
  mode: SatgasAssignmentActionProps["mode"],
  hasCurrentAssignments: boolean,
  t: (key: string) => string,
) {
  if (mode === "forward-report") {
    return {
      trigger: t("dashboard:workflow.assignment.forwardTrigger"),
      title: t("dashboard:workflow.assignment.forwardTitle"),
      description: t("dashboard:workflow.assignment.forwardDescription"),
      submit: t("dashboard:workflow.assignment.forwardSubmit"),
    };
  }

  return {
    trigger: t(
      hasCurrentAssignments
        ? "dashboard:workflow.assignment.changeTrigger"
        : "dashboard:workflow.assignment.assignTrigger",
    ),
    title: t(
      hasCurrentAssignments
        ? "dashboard:workflow.assignment.changeTitle"
        : "dashboard:workflow.assignment.assignTitle",
    ),
    description: t("dashboard:workflow.assignment.assignDescription"),
    submit: t("dashboard:workflow.assignment.assignSubmit"),
  };
}

function validateSelection(
  selectedIds: number[],
  leadId: number | null,
  t: (key: string) => string,
) {
  const errors: Record<string, string> = {};

  if (selectedIds.length === 0) {
    errors.satgas_ids = t("dashboard:workflow.assignment.selectAtLeastOne");
  }

  if (leadId === null) {
    errors.lead_satgas_id = t("dashboard:workflow.assignment.selectLead");
  } else if (!selectedIds.includes(leadId)) {
    errors.lead_satgas_id = t("dashboard:workflow.assignment.leadFromSelected");
  }

  return errors;
}

function laravelErrors(error: unknown) {
  return laravelFieldErrors(error);
}

function forwardedCaseId(result: unknown) {
  if (
    result
    && typeof result === "object"
    && "case" in result
    && result.case
    && typeof result.case === "object"
    && "id" in result.case
    && (typeof result.case.id === "string" || typeof result.case.id === "number")
  ) {
    return result.case.id;
  }

  return undefined;
}
