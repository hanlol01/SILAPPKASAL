import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BriefcaseMedical, Loader2 } from "lucide-react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { getMasterData, masterDataQueryKeys } from "@/lib/master-data-api";
import { createRecovery, operationsQueryKeys } from "@/lib/operations-api";
import type { Decision } from "@/lib/operations-types";
import { synchronizeWorkflowCaches } from "@/lib/workflow-cache-sync";

function createRecoverySchema(t: ReturnType<typeof useTranslation>["t"]) {
  const requiredText = z.string().trim()
    .min(1, t("dashboard:workflow.required"))
    .max(10000, t("dashboard:workflow.max10000"));
  const optionalText = z.string().trim()
    .max(10000, t("dashboard:workflow.max10000"))
    .optional();

  return z.object({
    recovery_type_code: z.string().min(1, t("dashboard:workflow.required")),
    recovery_plan: requiredText,
    support_needs: optionalText,
    notes: optionalText,
  });
}

type RecoveryCreateValues = z.infer<ReturnType<typeof createRecoverySchema>>;

export function RecoveryCreateAction({
  decision,
  caseId,
}: {
  decision: Decision;
  caseId: number | string;
}) {
  const { t } = useTranslation(["dashboard"]);
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const recoveryTypesQuery = useQuery({
    queryKey: masterDataQueryKeys.list("recovery-types"),
    queryFn: () => getMasterData("recovery-types"),
    enabled: open,
  });
  const form = useForm<RecoveryCreateValues>({
    resolver: zodResolver(createRecoverySchema(t)),
    defaultValues: {
      recovery_type_code: "",
      recovery_plan: "",
      support_needs: "",
      notes: "",
    },
  });

  const mutation = useMutation({
    mutationFn: (values: RecoveryCreateValues) => createRecovery(decision.id, nullifyEmpty(values)),
    onSuccess: async () => {
      await synchronizeWorkflowCaches(queryClient, {
        caseId,
        exactKeys: [
          operationsQueryKeys.decisions(decision.recommendation_id),
          operationsQueryKeys.recoveries(decision.id),
        ],
      });
      form.reset();
      setOpen(false);
      toast.success(t("dashboard:workflow.recoveryCreated"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
      toast.error(apiErrorMessage(error, t("dashboard:workflow.recoveryCreateError")));
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="w-full" variant="outline">
          <BriefcaseMedical className="mr-2 h-4 w-4" /> {t("dashboard:workflow.createRecovery")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("dashboard:workflow.createRecovery")}</DialogTitle>
          <DialogDescription>
            {t("dashboard:workflow.recoveryCreateDesc")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="space-y-4" onSubmit={form.handleSubmit((values) => {
            if (!mutation.isPending) mutation.mutate(values);
          })}>
            <FormField
              control={form.control}
              name="recovery_type_code"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("dashboard:workflow.recoveryType")}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value} disabled={recoveryTypesQuery.isLoading}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue
                          placeholder={
                            recoveryTypesQuery.isLoading
                              ? t("dashboard:workflow.loadingRecoveryTypes")
                              : t("dashboard:workflow.selectRecoveryType")
                          }
                        />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {(recoveryTypesQuery.data ?? []).map((item) => (
                        <SelectItem key={item.code} value={item.code}>
                          {item.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <TextareaField form={form} name="recovery_plan" label={t("dashboard:workflow.recoveryPlan")} />
            <TextareaField form={form} name="support_needs" label={t("dashboard:workflow.supportNeeds")} />
            <TextareaField form={form} name="notes" label={t("dashboard:workflow.notes")} />

            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {mutation.isPending
                  ? t("dashboard:common.saving")
                  : t("dashboard:workflow.createRecovery")}
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
