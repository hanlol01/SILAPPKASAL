import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Loader2, OctagonX } from "lucide-react";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Textarea } from "@/components/ui/textarea";
import { apiErrorMessage, applyLaravelErrors } from "@/lib/form-errors";
import { cancelPortalReport, portalQueryKeys } from "@/lib/portal-api";

function cancellationSchema(messages: {
  reasonRequired: string;
  reasonMin: string;
  reasonMax: string;
  confirmationRequired: string;
}) {
  return z.object({
    reason: z
      .string()
      .trim()
      .min(1, messages.reasonRequired)
      .min(20, messages.reasonMin)
      .max(2000, messages.reasonMax),
    confirmed: z.boolean().refine((value) => value, messages.confirmationRequired),
  });
}

type CancellationValues = z.infer<ReturnType<typeof cancellationSchema>>;

export function CancelComplaintDialog({ registrationNumber }: { registrationNumber: string }) {
  const { t } = useTranslation(["portal", "common"]);
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const schema = useMemo(
    () =>
      cancellationSchema({
        reasonRequired: t("cancellation.validation.reasonRequired"),
        reasonMin: t("cancellation.validation.reasonMin"),
        reasonMax: t("cancellation.validation.reasonMax"),
        confirmationRequired: t("cancellation.validation.confirmationRequired"),
      }),
    [t],
  );
  const form = useForm<CancellationValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      reason: "",
      confirmed: false,
    },
  });
  const mutation = useMutation({
    mutationFn: (values: CancellationValues) =>
      cancelPortalReport(registrationNumber, { reason: values.reason.trim() }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: portalQueryKeys.report(registrationNumber) }),
        queryClient.invalidateQueries({ queryKey: portalQueryKeys.reportsRoot() }),
        queryClient.invalidateQueries({ queryKey: portalQueryKeys.summary() }),
        queryClient.invalidateQueries({
          queryKey: portalQueryKeys.reportTimeline(registrationNumber),
        }),
        queryClient.invalidateQueries({
          queryKey: portalQueryKeys.reportHandlingProgress(registrationNumber),
        }),
        queryClient.invalidateQueries({
          queryKey: portalQueryKeys.reportEvidenceFiles(registrationNumber),
        }),
      ]);
      form.reset();
      setOpen(false);
      toast.success(t("cancellation.success"));
    },
    onError: (error) => {
      applyLaravelErrors(form, error);
    },
  });

  const handleOpenChange = (nextOpen: boolean) => {
    if (mutation.isPending) return;
    setOpen(nextOpen);
    if (!nextOpen) {
      form.reset();
      mutation.reset();
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button type="button" variant="destructive" className="min-h-11">
          <OctagonX className="h-4 w-4" aria-hidden="true" />
          {t("cancellation.trigger")}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t("cancellation.title")}</DialogTitle>
          <DialogDescription>{t("cancellation.description")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            className="space-y-5"
            onSubmit={form.handleSubmit((values) => {
              if (!mutation.isPending) mutation.mutate(values);
            })}
          >
            <p className="rounded-md border border-warning/30 bg-warning/10 p-3 text-sm text-foreground">
              {t("cancellation.historyNotice")}
            </p>
            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("cancellation.reasonLabel")}</FormLabel>
                  <FormControl>
                    <Textarea
                      {...field}
                      rows={6}
                      maxLength={2000}
                      disabled={mutation.isPending}
                      placeholder={t("cancellation.reasonPlaceholder")}
                    />
                  </FormControl>
                  <FormDescription>
                    {t("cancellation.reasonHelp", { count: field.value.length })}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="confirmed"
              render={({ field }) => (
                <FormItem className="rounded-md border p-3">
                  <div className="flex items-start gap-3">
                    <FormControl>
                      <Checkbox
                        checked={field.value}
                        onCheckedChange={(checked) => field.onChange(checked === true)}
                        disabled={mutation.isPending}
                      />
                    </FormControl>
                    <div className="space-y-1">
                      <FormLabel className="font-normal">
                        {t("cancellation.confirmationLabel")}
                      </FormLabel>
                      <FormMessage />
                    </div>
                  </div>
                </FormItem>
              )}
            />
            {mutation.isError && (
              <p role="alert" className="text-sm text-destructive">
                {apiErrorMessage(mutation.error, t("cancellation.error"))}
              </p>
            )}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                disabled={mutation.isPending}
                onClick={() => handleOpenChange(false)}
              >
                {t("common:cancel")}
              </Button>
              <Button type="submit" variant="destructive" disabled={mutation.isPending}>
                {mutation.isPending && (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                )}
                {mutation.isPending
                  ? t("cancellation.submitting")
                  : t("cancellation.confirmAction")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
