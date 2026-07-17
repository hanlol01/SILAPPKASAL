import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ClipboardList, Loader2 } from "lucide-react";
import { useState } from "react";
import { useForm, type FieldValues, type Path, type UseFormReturn } from "react-hook-form";
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
import { formatDateTime } from "@/lib/format";
import { createRecommendation, operationsQueryKeys } from "@/lib/operations-api";
import type { Investigation } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

function createRecommendationSchema(t: ReturnType<typeof useTranslation>["t"]) {
  const requiredText = z.string().trim()
    .min(1, t("dashboard:workflow.required"))
    .max(10000, t("dashboard:workflow.max10000"));
  const optionalText = z.string().trim()
    .max(10000, t("dashboard:workflow.max10000"))
    .optional();

  return z.object({
    conclusion: requiredText,
    recommended_actions: requiredText,
    sanction_recommendation: optionalText,
    recovery_recommendation: optionalText,
    prevention_recommendation: optionalText,
  });
}

type RecommendationCreateValues = z.infer<ReturnType<typeof createRecommendationSchema>>;

export function RecommendationCreateAction({
  caseId,
  investigation,
}: {
  caseId: number | string;
  investigation: Investigation;
}) {
  const { t, i18n } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const form = useForm<RecommendationCreateValues>({
    resolver: zodResolver(createRecommendationSchema(t)),
    defaultValues: {
      conclusion: "",
      recommended_actions: "",
      sanction_recommendation: "",
      recovery_recommendation: "",
      prevention_recommendation: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: RecommendationCreateValues) =>
      createRecommendation(caseId, {
        investigation_id: investigation.id,
        ...nullifyEmpty(values),
      }),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.recommendations(caseId),
          operationsQueryKeys.investigations(caseId),
        ],
      });
      form.reset();
      setOpen(false);
      toast.success(t("dashboard:workflow.recommendationCreated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recommendationCreateError")));
    },
  });

  const usingInvestigationCopy = investigation.completed_at
    ? t("dashboard:workflow.recommendationCreateUsingInvestigationCompleted", {
        completedAt: formatDateTime(investigation.completed_at, i18n.language),
      })
    : t("dashboard:workflow.recommendationCreateUsingInvestigation");

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline">
          <ClipboardList className="mr-2 h-4 w-4" /> {t("dashboard:workflow.createRecommendation")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.createRecommendation")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:workflow.recommendationCreateDesc")}
          </DialogDescription>
        </DialogHeader>

        <div className="rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
          {usingInvestigationCopy}
        </div>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <TextareaField form={form} name="conclusion" label={t("dashboard:sections.conclusion")} />
            <TextareaField form={form} name="recommended_actions" label={t("dashboard:sections.recommendedActions")} />
            <TextareaField form={form} name="sanction_recommendation" label={t("dashboard:workflow.sanctionRecommendation")} />
            <TextareaField form={form} name="recovery_recommendation" label={t("dashboard:workflow.recoveryRecommendation")} />
            <TextareaField form={form} name="prevention_recommendation" label={t("dashboard:workflow.preventionRecommendation")} />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending
                  ? t("dashboard:common.saving")
                  : t("dashboard:workflow.createRecommendation")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function TextareaField<T extends FieldValues>({
  form,
  name,
  label,
}: {
  form: UseFormReturn<T>;
  name: Path<T>;
  label: string;
}) {
  return (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Textarea {...field} value={(field.value as string | undefined) ?? ""} className="min-h-28" />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

function nullifyEmpty<T extends Record<string, unknown>>(values: T): T {
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [key, value === "" ? null : value]),
  ) as T;
}
