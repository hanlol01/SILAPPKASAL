import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, ClipboardPenLine, FileText, GitBranch, Loader2, Pencil } from "lucide-react";
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
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Textarea } from "@/components/ui/textarea";
import { formatDateTime } from "@/lib/format";
import { ApiError } from "@/lib/api-client";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  createCaseMinute,
  createCaseMinuteRevision,
  finalizeCaseMinute,
  getCaseMinutes,
  operationsQueryKeys,
  updateCaseMinute,
} from "@/lib/operations-api";
import type { CaseMinuteInternal } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

function minuteSchema(t: ReturnType<typeof useTranslation>["t"]) {
  const optional = z.string().trim().max(10000, t("dashboard:workflow.max10000")).optional();

  return z.object({
    occurred_at: z.string().min(1, t("dashboard:workflow.required")),
    internal_summary: optional,
    anonymized_summary: optional,
    outcome: optional,
    follow_up: optional,
  });
}

type MinuteValues = z.infer<ReturnType<typeof minuteSchema>>;

export function CaseMinuteCard({ caseId, language }: { caseId: number | string; language: string }) {
  const { t } = useTranslation(["dashboard"]);
  const queryClient = useQueryClient();
  const [editorOpen, setEditorOpen] = useState(false);
  const [finalizeOpen, setFinalizeOpen] = useState(false);
  const minutesQuery = useQuery({
    queryKey: operationsQueryKeys.caseMinutes(caseId),
    queryFn: () => getCaseMinutes(caseId),
  });
  const minutes = minutesQuery.data?.items ?? [];
  const draft = minutes.find(isInternalDraft) ?? null;
  const canOpenEditor = minutesQuery.data?.capabilities.create === true || draft?.capabilities.update === true;
  const form = useForm<MinuteValues>({
    resolver: zodResolver(minuteSchema(t)),
    defaultValues: emptyMinuteValues(),
  });

  useEffect(() => {
    if (!editorOpen) return;
    form.reset(draft ? minuteValues(draft) : emptyMinuteValues());
  }, [draft, editorOpen, form]);

  const refresh = async () => {
    await synchronizeWorkflowCaches(queryClient, {
      caseId,
      exactKeys: [operationsQueryKeys.caseMinutes(caseId)],
    });
  };

  const handleMutationError = (error: unknown, fallback: string, applyFormErrors = false) => {
    if (applyFormErrors) applyLaravelErrors(form, error);

    if (error instanceof ApiError && error.status === 409) {
      toast.error(t("dashboard:workflow.staleError"));
      void refresh();
      return;
    }

    toast.error(apiErrorMessage(error, fallback));
  };

  const saveMutation = useMutation({
    mutationFn: (values: MinuteValues) => {
      const payload = nullifyMinuteValues(values);
      return draft
        ? updateCaseMinute(draft.public_id, { ...payload, lock_version: draft.lock_version })
        : createCaseMinute(caseId, payload);
    },
    onSuccess: async () => {
      await refresh();
      setEditorOpen(false);
      toast.success(t("dashboard:workflow.caseMinuteSaved"));
    },
    onError: (error) => handleMutationError(error, t("dashboard:workflow.caseMinuteSaveError"), true),
  });
  const revisionMutation = useMutation({
    mutationFn: (publicId: string) => createCaseMinuteRevision(publicId),
    onSuccess: async () => {
      await refresh();
      toast.success(t("dashboard:workflow.caseMinuteRevisionCreated"));
    },
    onError: (error) => handleMutationError(error, t("dashboard:workflow.caseMinuteRevisionError")),
  });
  const finalizeMutation = useMutation({
    mutationFn: (minute: CaseMinuteInternal) => finalizeCaseMinute(minute.public_id, minute.lock_version),
    onSuccess: async () => {
      await refresh();
      setFinalizeOpen(false);
      toast.success(t("dashboard:workflow.caseMinuteFinalized"));
    },
    onError: (error) => handleMutationError(error, t("dashboard:workflow.caseMinuteFinalizeError")),
  });

  if (minutesQuery.isError) {
    return null;
  }

  return (
    <CollapsibleDataCard
      icon={FileText}
      title={t("dashboard:workflow.caseMinuteTitle")}
      description={t("dashboard:workflow.caseMinuteDescription")}
      contentClassName="space-y-4"
    >
      {minutesQuery.isLoading && <p className="text-sm text-muted-foreground">{t("dashboard:common.loading")}</p>}
      {!minutesQuery.isLoading && minutes.length === 0 && (
        <p className="text-sm text-muted-foreground">{t("dashboard:workflow.caseMinuteEmpty")}</p>
      )}
      <div className="space-y-3">
        {minutes.map((minute) => (
          <div key={minute.public_id} className="min-w-0 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p className="font-medium">{t("dashboard:workflow.caseMinuteVersion", { version: minute.version })}</p>
                <p className="text-xs text-muted-foreground">
                  {t(`dashboard:workflow.caseMinuteStatuses.${minute.status}`)} · {formatDateTime(minute.occurred_at, language)}
                </p>
              </div>
              {minute.finalized_at && (
                <p className="text-xs text-muted-foreground">
                  {t("dashboard:workflow.caseMinuteFinalizedAt", { date: formatDateTime(minute.finalized_at, language) })}
                </p>
              )}
            </div>
            {minute.projection === "internal" ? (
              <div className="mt-4 grid gap-4">
                <MinuteField label={t("dashboard:workflow.caseMinuteInternalSummary")} value={minute.internal_summary} />
                <MinuteField label={t("dashboard:workflow.caseMinuteAnonymizedSummary")} value={minute.anonymized_summary} />
                <div className="grid gap-4 sm:grid-cols-2">
                  <MinuteField label={t("dashboard:workflow.caseMinuteOutcome")} value={minute.outcome} />
                  <MinuteField label={t("dashboard:workflow.caseMinuteFollowUp")} value={minute.follow_up} />
                </div>
                {minute.supersedes && (
                  <p className="text-xs text-muted-foreground">
                    {t("dashboard:workflow.caseMinuteSupersedes", { version: minute.supersedes.version })}
                  </p>
                )}
                <div className="flex flex-wrap gap-2">
                  {minute.capabilities.create_revision && (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={revisionMutation.isPending}
                      onClick={() => revisionMutation.mutate(minute.public_id)}
                    >
                      {revisionMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <GitBranch className="mr-2 h-4 w-4" />}
                      {t("dashboard:workflow.caseMinuteCreateRevision")}
                    </Button>
                  )}
                </div>
              </div>
            ) : (
              <p className="mt-3 text-sm text-muted-foreground">{t("dashboard:workflow.caseMinuteMetadataOnly")}</p>
            )}
          </div>
        ))}
      </div>

      {canOpenEditor && (
        <Button className="w-full" variant="outline" onClick={() => setEditorOpen(true)}>
          {draft ? <Pencil className="mr-2 h-4 w-4" /> : <ClipboardPenLine className="mr-2 h-4 w-4" />}
          {draft ? t("dashboard:workflow.caseMinuteEditDraft") : t("dashboard:workflow.caseMinuteCreateDraft")}
        </Button>
      )}
      {draft?.capabilities.finalize && (
        <Button className="w-full" disabled={finalizeMutation.isPending} onClick={() => setFinalizeOpen(true)}>
          {finalizeMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
          {t("dashboard:workflow.caseMinuteFinalize")}
        </Button>
      )}

      <Dialog open={editorOpen} onOpenChange={setEditorOpen}>
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{draft ? t("dashboard:workflow.caseMinuteEditDraft") : t("dashboard:workflow.caseMinuteCreateDraft")}</DialogTitle>
            <DialogDescription>{t("dashboard:workflow.caseMinuteAnonymizationNotice")}</DialogDescription>
          </DialogHeader>
          <Form {...form}>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => !saveMutation.isPending && saveMutation.mutate(values))}>
              <FormField control={form.control} name="occurred_at" render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.caseMinuteOccurredAt")}</FormLabel>
                  <FormControl><DatePicker value={field.value} onChange={field.onChange} placeholder={t("dashboard:workflow.caseMinuteOccurredAt")} /></FormControl>
                  <FormMessage />
                </FormItem>
              )} />
              {minuteFields(t).map((item) => (
                <FormField key={item.name} control={form.control} name={item.name} render={({ field }) => (
                  <FormItem>
                    <FormLabel>{item.label}</FormLabel>
                    <FormControl><Textarea className="min-h-28" {...field} value={field.value ?? ""} /></FormControl>
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

      <AlertDialog open={finalizeOpen} onOpenChange={setFinalizeOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("dashboard:workflow.caseMinuteFinalize")}</AlertDialogTitle>
            <AlertDialogDescription>{t("dashboard:workflow.caseMinuteFinalizeConfirmation")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("dashboard:common.cancel")}</AlertDialogCancel>
          <AlertDialogAction
            disabled={finalizeMutation.isPending}
            onClick={() => !finalizeMutation.isPending && draft && finalizeMutation.mutate(draft)}
          >
              {t("dashboard:workflow.caseMinuteFinalize")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </CollapsibleDataCard>
  );
}

function isInternalDraft(minute: Awaited<ReturnType<typeof getCaseMinutes>>["items"][number]): minute is CaseMinuteInternal {
  return minute.projection === "internal" && minute.status === "draft";
}

function minuteFields(t: ReturnType<typeof useTranslation>["t"]) {
  return [
    { name: "internal_summary", label: t("dashboard:workflow.caseMinuteInternalSummary") },
    { name: "anonymized_summary", label: t("dashboard:workflow.caseMinuteAnonymizedSummary") },
    { name: "outcome", label: t("dashboard:workflow.caseMinuteOutcome") },
    { name: "follow_up", label: t("dashboard:workflow.caseMinuteFollowUp") },
  ] as const;
}

function emptyMinuteValues(): MinuteValues {
  return {
    occurred_at: currentDateValue(),
    internal_summary: "",
    anonymized_summary: "",
    outcome: "",
    follow_up: "",
  };
}

function minuteValues(minute: CaseMinuteInternal): MinuteValues {
  return {
    occurred_at: minute.occurred_at.slice(0, 10),
    internal_summary: minute.internal_summary ?? "",
    anonymized_summary: minute.anonymized_summary ?? "",
    outcome: minute.outcome ?? "",
    follow_up: minute.follow_up ?? "",
  };
}

function nullifyMinuteValues(values: MinuteValues) {
  return {
    ...values,
    internal_summary: values.internal_summary || null,
    anonymized_summary: values.anonymized_summary || null,
    outcome: values.outcome || null,
    follow_up: values.follow_up || null,
  };
}

function currentDateValue(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function MinuteField({ label, value }: { label: string; value: string | null }) {
  if (!value) return null;

  return <div className="min-w-0"><div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div><div className="mt-1 whitespace-pre-wrap break-words text-sm [overflow-wrap:anywhere]">{value}</div></div>;
}
