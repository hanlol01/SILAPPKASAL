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
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { createInvestigation, operationsQueryKeys } from "@/lib/operations-api";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

function createInvestigationCreateSchema(messages: {
  planSummaryRequired: string;
  planSummaryMax: string;
}) {
  return z.object({
    plan_summary: z
      .string()
      .trim()
      .min(1, messages.planSummaryRequired)
      .max(5000, messages.planSummaryMax),
  });
}

type InvestigationCreateValues = z.infer<ReturnType<typeof createInvestigationCreateSchema>>;

export function InvestigationCreateAction({
  caseId,
}: {
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const investigationCreateSchema = useMemo(
    () =>
      createInvestigationCreateSchema({
        planSummaryRequired: t("dashboard:workflow.planSummaryRequired"),
        planSummaryMax: t("dashboard:workflow.planSummaryMax"),
      }),
    [t],
  );
  const form = useForm<InvestigationCreateValues>({
    resolver: zodResolver(investigationCreateSchema),
    defaultValues: {
      plan_summary: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: InvestigationCreateValues) =>
      createInvestigation(caseId, {
        plan_summary: values.plan_summary.trim(),
    }),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [operationsQueryKeys.investigations(caseId)],
      });
      form.reset({
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
        <Button className="w-full" variant="outline">
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
              name="plan_summary"
              render={({ field }) => {
                const length = (field.value ?? "").length;
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
                      <p className="text-muted-foreground">
                        {t("dashboard:workflow.planSummaryHelp")}
                      </p>
                      <span
                        aria-live="polite"
                        className="tabular-nums text-muted-foreground"
                      >
                        {length}/5000
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
