import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { History, Loader2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
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
import { formatDecisionStatus } from "@/lib/format-labels";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  getDecisionStatusOptions,
  operationsQueryKeys,
  updateDecisionStatus,
} from "@/lib/operations-api";
import type { Decision } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

type DecisionStatusValues = { status: string };

export function DecisionStatusAction({
  decision,
  caseId,
}: {
  decision: Decision;
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const optionsQuery = useQuery({
    queryKey: operationsQueryKeys.decisionStatusOptions(decision.id),
    queryFn: () => getDecisionStatusOptions(decision.id),
    enabled: open,
    staleTime: 30_000,
  });
  const form = useForm<DecisionStatusValues>({
    resolver: zodResolver(z.object({
      status: z.string().min(1, t("dashboard:workflow.required")),
    })),
    defaultValues: { status: "" },
  });

  const options = useMemo(
    () => optionsQuery.data?.valid_transitions ?? [],
    [optionsQuery.data],
  );
  const selectedStatus = form.watch("status");
  const isFinalizing = options.some(
    (option) =>
      (option.code === selectedStatus || option.name === selectedStatus)
      && option.name === "finalized",
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

  const mutation = useMutation({
    mutationFn: (values: DecisionStatusValues) => updateDecisionStatus(decision.id, values),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.decisions(decision.recommendation_id),
          operationsQueryKeys.recommendations(caseId),
          operationsQueryKeys.recommendation(decision.recommendation_id),
          operationsQueryKeys.recommendationStatusOptions(decision.recommendation_id),
          operationsQueryKeys.decision(decision.id),
          operationsQueryKeys.decisionStatusOptions(decision.id),
        ],
      });
      form.reset({ status: "" });
      setOpen(false);
      toast.success(t("dashboard:workflow.decisionStatusUpdated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      void synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.decisions(decision.recommendation_id),
          operationsQueryKeys.recommendations(caseId),
          operationsQueryKeys.recommendation(decision.recommendation_id),
          operationsQueryKeys.recommendationStatusOptions(decision.recommendation_id),
          operationsQueryKeys.decision(decision.id),
          operationsQueryKeys.decisionStatusOptions(decision.id),
        ],
      }).catch(() => undefined);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.decisionStatusError")));
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
          <DialogTitle>{t("dashboard:workflow.updateDecisionStatus")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:workflow.validTransitionsOnly")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
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
                        <SelectValue placeholder={optionsQuery.isLoading ? t("dashboard:workflow.loadingStatuses") : t("dashboard:workflow.selectStatus")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {options.map((option) => (
                        <SelectItem key={option.code} value={option.code}>
                          {formatDecisionStatus(t, option.name)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {optionsQuery.isSuccess && options.length === 0 && (
                    <p className="text-xs text-muted-foreground">{t("dashboard:common.noValidNextStatus")}</p>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />

            {isFinalizing && (
              <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                {t("dashboard:workflow.decisionFinalizeConfirmation")}
              </div>
            )}

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending || options.length === 0}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending
                  ? t("dashboard:common.saving")
                  : isFinalizing
                    ? t("dashboard:workflow.finalizeDecision")
                    : t("dashboard:workflow.saveStatus")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
