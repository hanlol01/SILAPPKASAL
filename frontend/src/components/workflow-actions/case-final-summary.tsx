import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, FileCheck2, Loader2, LockKeyhole } from "lucide-react";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { formatDate, formatDateTime } from "@/lib/format";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  createCaseFinalSummary,
  finalizeCaseClosure,
  getCaseFinalSummary,
  operationsQueryKeys,
  publishCaseFinalSummary,
  updateCaseFinalSummary,
} from "@/lib/operations-api";
import type { CaseFinalSummaryPayload, WorkflowActionCapability } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

const today = new Date().toISOString().slice(0, 10);

function summarySchema(t: ReturnType<typeof useTranslation>["t"]) {
  const required = z.string().trim().min(1, t("dashboard:workflow.required")).max(10000, t("dashboard:workflow.max10000"));
  const optional = z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional();

  return z.object({
    outcome_code: z.string().min(1, t("dashboard:workflow.required")),
    completion_date: z.string().min(1, t("dashboard:workflow.required")).refine((value) => value <= today, t("dashboard:workflow.dateFuture")),
    official_statement: required,
    investigation_summary: optional,
    recommendation_result: optional,
    decision_result: optional,
    recovery_result: optional,
    actions_completed: optional,
    actions_uncompleted: optional,
    follow_up_or_referral: optional,
    closing_explanation: required,
  });
}

type SummaryValues = z.infer<ReturnType<typeof summarySchema>>;

export function CaseFinalSummaryActions({
  caseId,
  createCapability,
  updateCapability,
  publishCapability,
}: {
  caseId: number | string;
  createCapability?: WorkflowActionCapability;
  updateCapability?: WorkflowActionCapability;
  publishCapability?: WorkflowActionCapability;
}) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const summaryQuery = useQuery({
    queryKey: operationsQueryKeys.caseFinalSummary(caseId),
    queryFn: () => getCaseFinalSummary(caseId),
  });
  const summary = summaryQuery.data?.summary ?? null;
  const canOpen = createCapability?.allowed === true || updateCapability?.allowed === true;
  const form = useForm<SummaryValues>({
    resolver: zodResolver(summarySchema(t)),
    defaultValues: emptySummaryValues(),
  });

  useEffect(() => {
    if (!open) return;
    form.reset(summary ? summaryValues(summary) : emptySummaryValues());
  }, [form, open, summary]);

  const saveMutation = useMutation({
    mutationFn: (values: SummaryValues) => {
      const payload = nullifyOptional(values);
      return summary
        ? updateCaseFinalSummary(caseId, payload)
        : createCaseFinalSummary(caseId, payload);
    },
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [operationsQueryKeys.caseFinalSummary(caseId)],
      });
      setOpen(false);
      toast.success(t("dashboard:workflow.finalSummarySaved"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.finalSummarySaveError")));
    },
  });
  const publishMutation = useMutation({
    mutationFn: () => publishCaseFinalSummary(caseId),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [operationsQueryKeys.caseFinalSummary(caseId)],
      });
      toast.success(t("dashboard:workflow.finalSummaryPublished"));
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("dashboard:workflow.finalSummaryPublishError"))),
  });

  if (summaryQuery.isLoading) {
    return <Button className="w-full" variant="outline" disabled><Loader2 className="mr-2 h-4 w-4 animate-spin" />{t("dashboard:common.loading")}</Button>;
  }

  return (
    <div className="space-y-2">
      {canOpen && (
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button className="w-full" variant="outline">
              <FileCheck2 className="mr-2 h-4 w-4" />
              {summary ? t("dashboard:workflow.editFinalSummary") : t("dashboard:workflow.createFinalSummary")}
            </Button>
          </DialogTrigger>
          <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
              <DialogTitle>{summary ? t("dashboard:workflow.editFinalSummary") : t("dashboard:workflow.createFinalSummary")}</DialogTitle>
              <DialogDescription>{t("dashboard:workflow.finalSummaryReporterWarning")}</DialogDescription>
            </DialogHeader>
            <Form {...form}>
              <form className="space-y-4" onSubmit={form.handleSubmit((values) => !saveMutation.isPending && saveMutation.mutate(values))}>
                <FormField control={form.control} name="outcome_code" render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("dashboard:workflow.finalOutcome")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl><SelectTrigger><SelectValue placeholder={t("dashboard:workflow.selectOutcome")} /></SelectTrigger></FormControl>
                      <SelectContent>
                        {(summaryQuery.data?.outcome_options ?? []).map((option) => (
                          <SelectItem key={option.code} value={option.code}>{option.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )} />
                <FormField control={form.control} name="completion_date" render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("dashboard:workflow.completionDate")}</FormLabel>
                    <FormControl><DatePicker value={field.value} onChange={field.onChange} disableFuture placeholder={t("dashboard:workflow.completionDate")} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )} />
                {summaryFields(t).map((item) => (
                  <FormField key={item.name} control={form.control} name={item.name} render={({ field }) => (
                    <FormItem>
                      <FormLabel>{item.label}</FormLabel>
                      <FormControl><Textarea className={item.required ? "min-h-28" : "min-h-20"} {...field} value={field.value ?? ""} /></FormControl>
                      <FormMessage />
                    </FormItem>
                  )} />
                ))}
                <DialogFooter>
                  <Button type="submit" disabled={saveMutation.isPending}>
                    {saveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {t("dashboard:common.save")}
                  </Button>
                </DialogFooter>
              </form>
            </Form>
          </DialogContent>
        </Dialog>
      )}
      {summary && !summary.is_published && publishCapability?.allowed === true && (
        <Button className="w-full" onClick={() => publishMutation.mutate()} disabled={publishMutation.isPending}>
          {publishMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
          {t("dashboard:workflow.publishFinalSummary")}
        </Button>
      )}
      {summary && !summary.is_published && publishCapability?.allowed === false && (
        <p className="text-xs text-muted-foreground">{reasonText(t, publishCapability.reason_code)}</p>
      )}
    </div>
  );
}

export function CaseFinalSummaryCard({ caseId, language }: { caseId: number | string; language: string }) {
  const { t } = useTranslation(["dashboard"]);
  const summaryQuery = useQuery({
    queryKey: operationsQueryKeys.caseFinalSummary(caseId),
    queryFn: () => getCaseFinalSummary(caseId),
  });
  const summary = summaryQuery.data?.summary;

  if (!summary && !summaryQuery.isLoading) return null;

  return (
    <CollapsibleDataCard
      icon={FileCheck2}
      title={t("dashboard:workflow.finalSummaryTitle")}
      description={summary?.is_published ? t("dashboard:workflow.finalSummaryPublishedState") : t("dashboard:workflow.finalSummaryDraftState")}
      contentClassName="space-y-3"
    >
      {summaryQuery.isLoading && <p className="text-sm text-muted-foreground">{t("dashboard:common.loading")}</p>}
      {summary && (
        <>
          <div className="grid gap-3 sm:grid-cols-2">
            <SummaryField label={t("dashboard:workflow.finalOutcome")} value={summary.outcome_label} />
            <SummaryField label={t("dashboard:workflow.completionDate")} value={formatDate(summary.completion_date, language)} />
          </div>
          {summaryFields(t).map((field) => (
            <SummaryField key={field.name} label={field.label} value={summary[field.name] ?? null} />
          ))}
          {summary.is_published && summary.published_at && (
            <p className="text-xs text-muted-foreground">{t("dashboard:workflow.publishedAt", { date: formatDateTime(summary.published_at, language) })}</p>
          )}
        </>
      )}
    </CollapsibleDataCard>
  );
}

export function CaseClosureAction({
  caseId,
  capability,
}: {
  caseId: number | string;
  capability?: WorkflowActionCapability;
}) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: () => finalizeCaseClosure(caseId),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, { caseId, exactKeys: [operationsQueryKeys.caseFinalSummary(caseId)] });
      toast.success(t("dashboard:workflow.caseClosureCompleted"));
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("dashboard:workflow.caseClosureError"))),
  });

  return (
    <div className="space-y-1">
      <Button className="w-full" disabled={!capability?.allowed || mutation.isPending} onClick={() => mutation.mutate()}>
        {mutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <LockKeyhole className="mr-2 h-4 w-4" />}
        {t("dashboard:workflow.finalizeCaseClosure")}
      </Button>
      {!capability?.allowed && <p className="text-xs text-muted-foreground">{reasonText(t, capability?.reason_code)}</p>}
    </div>
  );
}

function summaryFields(t: ReturnType<typeof useTranslation>["t"]) {
  return [
    { name: "official_statement", label: t("dashboard:workflow.officialStatement"), required: true },
    { name: "investigation_summary", label: t("dashboard:workflow.investigationSummary"), required: false },
    { name: "recommendation_result", label: t("dashboard:workflow.recommendationResult"), required: false },
    { name: "decision_result", label: t("dashboard:workflow.decisionResult"), required: false },
    { name: "recovery_result", label: t("dashboard:workflow.recoveryResult"), required: false },
    { name: "actions_completed", label: t("dashboard:workflow.actionsCompleted"), required: false },
    { name: "actions_uncompleted", label: t("dashboard:workflow.actionsUncompleted"), required: false },
    { name: "follow_up_or_referral", label: t("dashboard:workflow.followUpOrReferral"), required: false },
    { name: "closing_explanation", label: t("dashboard:workflow.closingExplanation"), required: true },
  ] as const;
}

function emptySummaryValues(): SummaryValues {
  return {
    outcome_code: "",
    completion_date: today,
    official_statement: "",
    investigation_summary: "",
    recommendation_result: "",
    decision_result: "",
    recovery_result: "",
    actions_completed: "",
    actions_uncompleted: "",
    follow_up_or_referral: "",
    closing_explanation: "",
  };
}

function summaryValues(summary: NonNullable<Awaited<ReturnType<typeof getCaseFinalSummary>>["summary"]>): SummaryValues {
  return {
    outcome_code: summary.outcome_code,
    completion_date: summary.completion_date,
    official_statement: summary.official_statement,
    investigation_summary: summary.investigation_summary ?? "",
    recommendation_result: summary.recommendation_result ?? "",
    decision_result: summary.decision_result ?? "",
    recovery_result: summary.recovery_result ?? "",
    actions_completed: summary.actions_completed ?? "",
    actions_uncompleted: summary.actions_uncompleted ?? "",
    follow_up_or_referral: summary.follow_up_or_referral ?? "",
    closing_explanation: summary.closing_explanation,
  };
}

function nullifyOptional(values: SummaryValues): CaseFinalSummaryPayload {
  return {
    ...values,
    investigation_summary: values.investigation_summary || null,
    recommendation_result: values.recommendation_result || null,
    decision_result: values.decision_result || null,
    recovery_result: values.recovery_result || null,
    actions_completed: values.actions_completed || null,
    actions_uncompleted: values.actions_uncompleted || null,
    follow_up_or_referral: values.follow_up_or_referral || null,
  };
}

function SummaryField({ label, value }: { label: string; value?: string | null }) {
  if (!value) return null;
  return <div className="min-w-0"><div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div><div className="mt-1 whitespace-pre-wrap break-words text-sm [overflow-wrap:anywhere]">{value}</div></div>;
}

function reasonText(t: ReturnType<typeof useTranslation>["t"], code?: string | null) {
  return t(`dashboard:workflow.reasons.${code ?? "action_unavailable"}`, {
    defaultValue: t("dashboard:workflow.reasons.action_unavailable"),
  });
}
