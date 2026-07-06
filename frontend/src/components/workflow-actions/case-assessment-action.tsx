import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, ShieldAlert } from "lucide-react";
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
import { formatPriorityLevel, formatRiskLevel } from "@/lib/format-labels";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { getMasterData, masterDataQueryKeys } from "@/lib/master-data-api";
import { operationsQueryKeys, recordCaseAssessment } from "@/lib/operations-api";

function createAssessmentSchema(messages: { riskRequired: string; priorityRequired: string }) {
  return z.object({
    risk_level_code: z.string().min(1, messages.riskRequired),
    priority_level_code: z.string().min(1, messages.priorityRequired),
  });
}

type AssessmentValues = z.infer<ReturnType<typeof createAssessmentSchema>>;

export function CaseAssessmentAction({
  caseId,
  currentRiskCode,
  currentPriorityCode,
}: {
  caseId: number | string;
  currentRiskCode?: string | null;
  currentPriorityCode?: string | null;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const riskLevelsQuery = useQuery({
    queryKey: masterDataQueryKeys.list("risk-levels"),
    queryFn: () => getMasterData("risk-levels"),
  });
  const priorityLevelsQuery = useQuery({
    queryKey: masterDataQueryKeys.list("priority-levels"),
    queryFn: () => getMasterData("priority-levels"),
  });
  const assessmentSchema = useMemo(
    () =>
      createAssessmentSchema({
        riskRequired: t("dashboard:workflow.assessment.riskRequired"),
        priorityRequired: t("dashboard:workflow.assessment.priorityRequired"),
      }),
    [t],
  );
  const form = useForm<AssessmentValues>({
    resolver: zodResolver(assessmentSchema),
    defaultValues: {
      risk_level_code: currentRiskCode ?? "",
      priority_level_code: currentPriorityCode ?? "",
    },
  });
  const mutation = useMutation({
    mutationFn: (values: AssessmentValues) => recordCaseAssessment(caseId, values),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.assessment.success"));
      setOpen(false);
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: ["operations", "cases"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.assessment.error")));
    },
  });

  const optionsLoading = riskLevelsQuery.isLoading || priorityLevelsQuery.isLoading;
  const optionsFailed = riskLevelsQuery.isError || priorityLevelsQuery.isError;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline" disabled={optionsLoading}>
          <ShieldAlert className="mr-2 h-4 w-4" /> {t("dashboard:workflow.assessment.trigger")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.assessment.title")}</DialogTitle>
          <DialogDescription>{t("dashboard:workflow.assessment.desc")}</DialogDescription>
        </DialogHeader>
        {optionsFailed ? (
          <p className="text-sm text-destructive">{t("dashboard:workflow.assessment.optionsError")}</p>
        ) : (
          <Form {...form}>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
              <FormField
                control={form.control}
                name="risk_level_code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("dashboard:workflow.assessment.riskLabel")}</FormLabel>
                    <Select onValueChange={field.onChange} value={field.value}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={t("dashboard:workflow.assessment.selectRisk")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {(riskLevelsQuery.data ?? []).map((item) => (
                          <SelectItem key={item.code} value={item.code}>
                            {formatRiskLevel(t, item.name)}
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
                name="priority_level_code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("dashboard:workflow.assessment.priorityLabel")}</FormLabel>
                    <Select onValueChange={field.onChange} value={field.value}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={t("dashboard:workflow.assessment.selectPriority")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {(priorityLevelsQuery.data ?? []).map((item) => (
                          <SelectItem key={item.code} value={item.code}>
                            {formatPriorityLevel(t, item.name)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <p className="text-xs text-muted-foreground">{t("dashboard:workflow.assessment.slaHint")}</p>

              <DialogFooter>
                <Button type="submit" disabled={mutation.isPending}>
                  {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                  {t("dashboard:workflow.assessment.submit")}
                </Button>
              </DialogFooter>
            </form>
          </Form>
        )}
      </DialogContent>
    </Dialog>
  );
}
