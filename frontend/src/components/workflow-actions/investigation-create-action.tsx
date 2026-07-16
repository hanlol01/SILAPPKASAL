import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { FileSearch, Loader2 } from "lucide-react";
import { useMemo, useState } from "react";
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
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { createInvestigation, operationsQueryKeys } from "@/lib/operations-api";
import type { CaseAssignment } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

function createInvestigationCreateSchema(messages: {
  required: string;
  planSummaryRequired: string;
  planSummaryMin: string;
  planSummaryMax: string;
}) {
  return z.object({
    lead_investigator_id: z.string().min(1, messages.required),
    plan_summary: z
      .string()
      .trim()
      .min(1, messages.planSummaryRequired)
      .min(50, messages.planSummaryMin)
      .max(5000, messages.planSummaryMax),
  });
}

type InvestigationCreateValues = z.infer<ReturnType<typeof createInvestigationCreateSchema>>;

export function InvestigationCreateAction({
  caseId,
  assignments,
}: {
  caseId: number | string;
  assignments: CaseAssignment[];
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const investigationCreateSchema = useMemo(
    () =>
      createInvestigationCreateSchema({
        required: t("dashboard:workflow.required"),
        planSummaryRequired: t("dashboard:workflow.planSummaryRequired"),
        planSummaryMin: t("dashboard:workflow.planSummaryMin"),
        planSummaryMax: t("dashboard:workflow.planSummaryMax"),
      }),
    [t],
  );
  const activeAssignments = useMemo(
    () => assignments.filter((assignment) => assignment.is_active),
    [assignments],
  );

  const form = useForm<InvestigationCreateValues>({
    resolver: zodResolver(investigationCreateSchema),
    defaultValues: {
      lead_investigator_id: activeAssignments[0]?.satgas_id ? String(activeAssignments[0].satgas_id) : "",
      plan_summary: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: InvestigationCreateValues) =>
      createInvestigation(caseId, {
        lead_investigator_id: Number(values.lead_investigator_id),
        plan_summary: values.plan_summary.trim(),
    }),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [operationsQueryKeys.investigations(caseId)],
      });
      form.reset({
        lead_investigator_id: activeAssignments[0]?.satgas_id ? String(activeAssignments[0].satgas_id) : "",
        plan_summary: "",
      });
      setOpen(false);
      toast.success(t("dashboard:workflow.investigationCreated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.investigationCreateError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline" disabled={activeAssignments.length === 0}>
          <FileSearch className="mr-2 h-4 w-4" /> {t("dashboard:workflow.createInvestigation")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.createInvestigation")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:workflow.createInvestigationDesc")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <FormField
              control={form.control}
              name="lead_investigator_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.leadInvestigator")}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={t("dashboard:workflow.selectAssignedSatgas")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {activeAssignments.map((assignment) => (
                        <SelectItem key={assignment.id} value={String(assignment.satgas_id)}>
                          {assignment.satgas_name ?? `Satgas #${assignment.satgas_id}`}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="plan_summary"
              render={({ field }) => {
                const length = (field.value ?? "").length;
                const belowMinimum = length < 50;

                return (
                  <FormItem>
                    <FormLabel>{t("dashboard:workflow.planSummary")}</FormLabel>
                    <FormControl>
                      <Textarea
                        {...field}
                        className="min-h-32"
                        placeholder={t("dashboard:workflow.planSummaryPlaceholder")}
                      />
                    </FormControl>
                    <div className="flex items-center justify-between gap-2 text-xs">
                      <p className={belowMinimum ? "text-warning" : "text-muted-foreground"}>
                        {t("dashboard:workflow.planSummaryHelp")}
                      </p>
                      <span
                        aria-live="polite"
                        className={belowMinimum ? "tabular-nums text-warning" : "tabular-nums text-muted-foreground"}
                      >
                        {belowMinimum ? `${length}/50` : `${length}/5000`}
                      </span>
                    </div>
                    <FormMessage />
                  </FormItem>
                );
              }}
            />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending
                  ? t("dashboard:common.saving")
                  : t("dashboard:workflow.createInvestigation")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
