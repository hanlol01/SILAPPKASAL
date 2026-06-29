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
import { formatInvestigationStatus } from "@/lib/format-labels";
import {
  getInvestigationStatusOptions,
  operationsQueryKeys,
  updateInvestigationStatus,
} from "@/lib/operations-api";
import type { Investigation } from "@/lib/operations-types";

const investigationStatusSchema = z.object({
  status: z.string().min(1, "Required"),
});

type InvestigationStatusValues = z.infer<typeof investigationStatusSchema>;

export function InvestigationStatusAction({
  investigation,
  caseId,
}: {
  investigation: Investigation;
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const optionsQuery = useQuery({
    queryKey: operationsQueryKeys.investigationStatusOptions(investigation.id),
    queryFn: () => getInvestigationStatusOptions(investigation.id),
    enabled: open,
  });
  const form = useForm<InvestigationStatusValues>({
    resolver: zodResolver(investigationStatusSchema),
    defaultValues: { status: "" },
  });

  const options = optionsQuery.data?.valid_transitions ?? [];
  const mutation = useMutation({
    mutationFn: (values: InvestigationStatusValues) =>
      updateInvestigationStatus(investigation.id, values),
    onSuccess: () => {
      toast.success(t("dashboard:workflow.investigationStatusUpdated"));
      setOpen(false);
      form.reset({ status: "" });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigations(caseId) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigation(investigation.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.investigationStatusOptions(investigation.id) });
      queryClient.invalidateQueries({ queryKey: operationsQueryKeys.recommendations(caseId) });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["my-work"] });
    },
    onError: () => {
      toast.error(t("dashboard:workflow.investigationStatusError"));
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
          <DialogTitle>{t("dashboard:workflow.investigationStatusUpdateTitle")}</DialogTitle>
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
                          {formatInvestigationStatus(t, option.name)}
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
