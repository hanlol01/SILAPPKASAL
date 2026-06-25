import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { History, Loader2 } from "lucide-react";
import { useState } from "react";
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

const decisionStatusSchema = z.object({
  status: z.string().min(1, "Required"),
});

type DecisionStatusValues = z.infer<typeof decisionStatusSchema>;

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
  });
  const form = useForm<DecisionStatusValues>({
    resolver: zodResolver(decisionStatusSchema),
    defaultValues: { status: "" },
  });

  const options = optionsQuery.data?.valid_transitions ?? [];
  const mutation = useMutation({
    mutationFn: (values: DecisionStatusValues) => updateDecisionStatus(decision.id, values),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.decisionStatusUpdated"));
      setOpen(false);
      form.reset({ status: "" });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.decisions(decision.recommendation_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.decision(decision.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.decisionStatusOptions(decision.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.decisionStatusError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <History className="mr-2 h-4 w-4" /> {t("dashboard:workflow.status")}
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
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => mutation.mutate(values))}>
            <FormField
              control={form.control}
              name="status"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.status")}</FormLabel>
                  <Select
                    onValueChange={field.onChange}
                    value={field.value}
                    disabled={optionsQuery.isLoading || options.length === 0}
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

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending || options.length === 0}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t("dashboard:workflow.saveStatus")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
