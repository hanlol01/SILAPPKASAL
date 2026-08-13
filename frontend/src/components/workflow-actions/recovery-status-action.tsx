import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, History, Loader2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
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
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { formatRecoveryStatus } from "@/lib/format-labels";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  getRecoveryStatusOptions,
  operationsQueryKeys,
  updateRecoveryStatus,
} from "@/lib/operations-api";
import type { Recovery } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

type RecoveryStatusValues = { status: string; discontinuation_reason?: string };

export function RecoveryStatusAction({
  recovery,
  caseId,
}: {
  recovery: Recovery;
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const optionsQuery = useQuery({
    queryKey: operationsQueryKeys.recoveryStatusOptions(recovery.id),
    queryFn: () => getRecoveryStatusOptions(recovery.id),
    enabled: open,
    staleTime: 30_000,
  });
  const form = useForm<RecoveryStatusValues>({
    resolver: zodResolver(z.object({
      status: z.string().min(1, t("dashboard:workflow.required")),
      discontinuation_reason: z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional(),
    })),
    defaultValues: { status: "", discontinuation_reason: "" },
  });

  const options = useMemo(
    () => optionsQuery.data?.valid_transitions ?? [],
    [optionsQuery.data],
  );
  useEffect(() => {
    if (!optionsQuery.isSuccess) return;
    const selected = form.getValues("status");
    if (selected && !options.some((option) => option.code === selected || option.name === selected)) {
      form.resetField("status");
    }
  }, [form, options, optionsQuery.isSuccess]);

  async function openWhenAvailable() {
    if (optionsQuery.isFetching) return;

    const result = await optionsQuery.refetch();
    if (result.isError) {
      toast.error(t("dashboard:workflow.statusOptionsError"));
      return;
    }

    if ((result.data?.valid_transitions.length ?? 0) > 0) {
      setOpen(true);
    }
  }

  const selectedStatus = form.watch("status");
  const selectedOption = options.find((option) => option.code === selectedStatus || option.name === selectedStatus);
  const mutation = useMutation({
    mutationFn: (values: RecoveryStatusValues) => updateRecoveryStatus(recovery.id, values),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.recoveries(recovery.decision_id),
          operationsQueryKeys.recovery(recovery.id),
          operationsQueryKeys.recoveryStatusOptions(recovery.id),
        ],
      });
      form.reset({ status: "", discontinuation_reason: "" });
      setOpen(false);
      toast.success(t("dashboard:workflow.recoveryStatusUpdated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recoveryStatusError")));
    },
  });

  if (optionsQuery.isSuccess && options.length === 0 && !open) return null;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button
          size="sm"
          variant="default"
          disabled={optionsQuery.isFetching}
          onClick={(event) => {
            event.preventDefault();
            void openWhenAvailable();
          }}
        >
          {optionsQuery.isFetching ? (
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
          ) : (
            <History className="mr-2 h-4 w-4" />
          )}
          {optionsQuery.isFetching
            ? t("dashboard:workflow.loadingStatuses")
            : t("dashboard:workflow.status")}
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.updateRecoveryStatus")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:workflow.recoveryStatusUpdateDesc")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (selectedOption?.name === "discontinued" && !values.discontinuation_reason?.trim()) {
              form.setError("discontinuation_reason", { message: t("dashboard:workflow.required") });
              return;
            }
            if (!mutation.isPending) mutation.mutate({
              status: values.status,
              discontinuation_reason: selectedOption?.name === "discontinued"
                ? values.discontinuation_reason?.trim()
                : undefined,
            });
          })}>
            <FormField
              control={form.control}
              name="status"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.status")}</FormLabel>
                  <Select
                    onValueChange={field.onChange}
                    value={field.value}
                    disabled={mutation.isPending || optionsQuery.isLoading || options.length === 0}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue
                          placeholder={
                            optionsQuery.isLoading
                              ? t("dashboard:workflow.loadingStatuses")
                              : t("dashboard:workflow.selectStatus")
                          }
                        />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {options.map((option) => (
                        <SelectItem key={option.code} value={option.code} disabled={option.allowed === false}>
                          {formatRecoveryStatus(t, option.name)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {optionsQuery.isSuccess && options.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                      {t("dashboard:common.noValidNextStatus")}
                    </p>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />

            {options.some((option) => option.allowed === false && option.reason_code) && (
              <div className="space-y-1 text-xs text-muted-foreground">
                {options.filter((option) => option.allowed === false && option.reason_code).map((option) => (
                  <p key={option.code}>
                    {formatRecoveryStatus(t, option.name)}: {t(`dashboard:workflow.reasons.${option.reason_code}`, {
                      defaultValue: t("dashboard:workflow.reasons.action_unavailable"),
                    })}
                  </p>
                ))}
              </div>
            )}

            {selectedOption?.name === "discontinued" && (
              <>
                <FormField
                  control={form.control}
                  name="discontinuation_reason"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("dashboard:workflow.discontinuationReason")}</FormLabel>
                      <FormControl>
                        <Textarea className="min-h-28" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <Alert className="border-amber-200 bg-amber-50 text-amber-950">
                  <AlertTriangle className="h-4 w-4" />
                  <AlertTitle>{t("dashboard:workflow.recoveryTerminalTitle")}</AlertTitle>
                  <AlertDescription>{t("dashboard:workflow.recoveryTerminalDesc")}</AlertDescription>
                </Alert>
              </>
            )}

            {selectedOption?.soft_warning && (
              <Alert className="border-amber-200 bg-amber-50 text-amber-950">
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle>{t("dashboard:workflow.advisoryWarning")}</AlertTitle>
                <AlertDescription>{selectedOption.soft_warning}</AlertDescription>
              </Alert>
            )}

            <DialogFooter>
              <Button
                type="submit"
                disabled={mutation.isPending || options.length === 0 || selectedOption?.allowed === false}
              >
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending
                  ? t("dashboard:common.saving")
                  : t("dashboard:workflow.saveStatus")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
