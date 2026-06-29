import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, History, Loader2 } from "lucide-react";
import { useState } from "react";
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
import { formatRecoveryStatus } from "@/lib/format-labels";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import {
  getRecoveryStatusOptions,
  operationsQueryKeys,
  updateRecoveryStatus,
} from "@/lib/operations-api";
import type { Recovery } from "@/lib/operations-types";

const recoveryStatusSchema = z.object({
  status: z.string().min(1, "Required"),
});

type RecoveryStatusValues = z.infer<typeof recoveryStatusSchema>;

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
  });
  const form = useForm<RecoveryStatusValues>({
    resolver: zodResolver(recoveryStatusSchema),
    defaultValues: { status: "" },
  });

  const options = optionsQuery.data?.valid_transitions ?? [];
  const selectedStatus = form.watch("status");
  const selectedOption = options.find((option) => option.code === selectedStatus || option.name === selectedStatus);
  const mutation = useMutation({
    mutationFn: (values: RecoveryStatusValues) => updateRecoveryStatus(recovery.id, values),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.recoveryStatusUpdated"));
      setOpen(false);
      form.reset({ status: "" });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.case(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recoveries(recovery.decision_id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recovery(recovery.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recoveryStatusOptions(recovery.id) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recoveryStatusError")));
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
          <DialogTitle>{t("dashboard:workflow.updateRecoveryStatus")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:workflow.recoveryStatusUpdateDesc")}
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
                        <SelectItem key={option.code} value={option.code}>
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

            {selectedOption?.soft_warning && (
              <Alert className="border-amber-200 bg-amber-50 text-amber-950">
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle>{t("dashboard:workflow.advisoryWarning")}</AlertTitle>
                <AlertDescription>{selectedOption.soft_warning}</AlertDescription>
              </Alert>
            )}

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
