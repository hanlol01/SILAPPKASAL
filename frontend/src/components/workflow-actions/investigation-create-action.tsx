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

const investigationCreateSchema = z.object({
  lead_investigator_id: z.string().min(1, "Required"),
  plan_summary: z.string().trim().min(50, "Minimum 50 characters").max(5000, "Maximum 5000 characters"),
});

type InvestigationCreateValues = z.infer<typeof investigationCreateSchema>;

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
    onSuccess: () => {
      toast.success(t("dashboard:workflow.investigationCreated"));
      setOpen(false);
      form.reset({
        lead_investigator_id: activeAssignments[0]?.satgas_id ? String(activeAssignments[0].satgas_id) : "",
        plan_summary: "",
      });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigations(caseId) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
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
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
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
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.planSummary")}</FormLabel>
                  <FormControl>
                    <Textarea
                      {...field}
                      className="min-h-32"
                      placeholder={t("dashboard:workflow.planSummaryPlaceholder")}
                    />
                  </FormControl>
                  <p className="text-xs text-muted-foreground">{t("dashboard:workflow.planSummaryHelp")}</p>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t("dashboard:workflow.createInvestigation")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
